<?php
if (!defined('ABSPATH')) {
    exit;
}

class CareToChina_Payment_Manager {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Retrieve booking record by ID
     */
    public function get_booking($booking_id) {
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_bookings WHERE id = %d", intval($booking_id)));
    }

    /**
     * Create or retrieve an existing unpaid WooCommerce Order for a booking
     */
    public function get_or_create_order_for_booking($booking_id, $gateway_id = '') {
        if (!class_exists('WooCommerce')) {
            return new WP_Error('wc_missing', __('WooCommerce plugin is required for payment processing.', 'caretochina-medical'));
        }

        $booking = $this->get_booking($booking_id);
        if (!$booking) {
            return new WP_Error('booking_not_found', __('Booking record not found.', 'caretochina-medical'));
        }

        // Re-use existing order if present and not already paid/completed
        if (!empty($booking->wc_order_id) && $booking->wc_order_id > 0) {
            $existing_order = wc_get_order($booking->wc_order_id);
            if ($existing_order && in_array($existing_order->get_status(), ['pending', 'failed', 'on-hold'])) {
                if (!empty($gateway_id)) {
                    $existing_order->set_payment_method($gateway_id);
                    $existing_order->save();
                }
                return $existing_order;
            }
        }

        // Snapshot price check: ensure booking has an authoritative amount
        $amount = floatval($booking->amount);
        if ($amount <= 0) {
            // Default treatment price fallback if not snapshotted yet
            $amount = 500.00; // Standard deposit rate or initial treatment fee
        }

        // Create new WooCommerce Order (HPOS Compliant)
        $order = wc_create_order([
            'customer_id' => intval($booking->patient_id),
            'status'      => 'pending',
        ]);

        if (is_wp_error($order)) {
            return $order;
        }

        // Get backing virtual non-taxable WC_Product for line item
        $product = CareToChina_Treatment_Product_Sync::instance()->get_or_create_product(
            $booking->specialty ?: __('Medical Consultation & Treatment', 'caretochina-medical'),
            $booking->hospital_id
        );

        if ($product) {
            $order->add_product($product, 1, [
                'subtotal' => $amount,
                'total'    => $amount,
            ]);
        }

        $currency = !empty($booking->currency) ? $booking->currency : 'USD';
        $order->set_currency($currency);
        if (!empty($gateway_id)) {
            $order->set_payment_method($gateway_id);
        }

        // Billing details from booking
        $name_parts = explode(' ', trim($booking->full_name), 2);
        $order->set_billing_first_name($name_parts[0]);
        $order->set_billing_last_name(isset($name_parts[1]) ? $name_parts[1] : '');
        $order->set_billing_email($booking->email);
        $order->set_billing_phone($booking->phone);

        // Meta linkage
        $order->update_meta_data('_caretochina_booking_id', $booking->id);
        $order->update_meta_data('_caretochina_booking_code', $booking->booking_code);
        $order->calculate_totals();
        $order->save();

        // Update denormalized cache on caretochina_bookings
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';
        $wpdb->update($table_bookings, [
            'wc_order_id'     => $order->get_id(),
            'amount'          => $amount,
            'currency'        => $currency,
            'payment_gateway' => $gateway_id,
        ], ['id' => $booking->id]);

        $this->add_audit_log($booking->id, $order->get_id(), get_current_user_id(), 'payment_initiated', $amount, 'Created WC Order #' . $order->get_id());

        return $order;
    }

    /**
     * Process Webhook Event with Event-ID Deduplication and Atomic Status Transition
     */
    public function process_webhook_event($gateway, $event_id, $event_type, $payload) {
        global $wpdb;
        $table_events = $wpdb->prefix . 'caretochina_processed_webhook_events';

        // Atomic Event-ID Deduplication: INSERT IGNORE
        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO $table_events (event_id, gateway, event_type) VALUES (%s, %s, %s)",
            $event_id,
            $gateway,
            $event_type
        ));

        if ($inserted === 0) {
            // Already processed! Skip immediately.
            return true;
        }

        // Extract booking_id and transaction_id from payload
        $booking_id = 0;
        $transaction_id = '';
        $wc_order_id = 0;

        if ($gateway === 'stripe') {
            $object = isset($payload['data']['object']) ? $payload['data']['object'] : [];
            $booking_id = isset($object['metadata']['caretochina_booking_id']) ? intval($object['metadata']['caretochina_booking_id']) : 0;
            $transaction_id = isset($object['id']) ? $object['id'] : '';
        } elseif ($gateway === 'paypal') {
            $resource = isset($payload['resource']) ? $payload['resource'] : [];
            $booking_id = isset($resource['custom_id']) ? intval($resource['custom_id']) : 0;
            if (!$booking_id && isset($resource['purchase_units'][0]['custom_id'])) {
                $booking_id = intval($resource['purchase_units'][0]['custom_id']);
            }
            $transaction_id = isset($resource['id']) ? $resource['id'] : '';
        }

        if (!$booking_id && !empty($transaction_id)) {
            // Attempt to resolve booking ID from WooCommerce order meta
            $orders = wc_get_orders([
                'meta_key'   => '_caretochina_booking_id',
                'limit'      => 1,
            ]);
            if (!empty($orders)) {
                $booking_id = intval($orders[0]->get_meta('_caretochina_booking_id'));
                $wc_order_id = $orders[0]->get_id();
            }
        }

        if (!$booking_id) {
            return false;
        }

        // Handle Event Types
        if (in_array($event_type, ['payment_intent.succeeded', 'PAYMENT.CAPTURE.COMPLETED', 'CHECKOUT.ORDER.APPROVED'])) {
            return $this->confirm_booking_payment($booking_id, $wc_order_id, $transaction_id, $gateway);
        } elseif (in_array($event_type, ['payment_intent.payment_failed', 'PAYMENT.CAPTURE.DENIED'])) {
            $reason = isset($payload['data']['object']['last_payment_error']['message']) ? $payload['data']['object']['last_payment_error']['message'] : 'Payment declined by bank';
            return $this->mark_booking_payment_failed($booking_id, $wc_order_id, $reason);
        }

        return true;
    }

    /**
     * Confirm Payment & Transition Booking Status to Paid / Confirmed (Idempotent)
     */
    public function confirm_booking_payment($booking_id, $wc_order_id = 0, $transaction_id = '', $gateway = '') {
        $booking = $this->get_booking($booking_id);
        if (!$booking) {
            return false;
        }

        if ($booking->status === 'paid' || $booking->status === 'confirmed') {
            return true; // Already paid
        }

        if (!$wc_order_id && !empty($booking->wc_order_id)) {
            $wc_order_id = $booking->wc_order_id;
        }

        $order = $wc_order_id ? wc_get_order($wc_order_id) : null;
        if ($order) {
            if (!empty($transaction_id)) {
                $order->set_transaction_id($transaction_id);
            }
            if (!empty($gateway)) {
                $order->set_payment_method($gateway);
            }
            $order->payment_complete($transaction_id);
            $order->save();
            $paid_amount = floatval($order->get_total());
            $currency = $order->get_currency();
        } else {
            $paid_amount = floatval($booking->amount);
            $currency = $booking->currency ?: 'USD';
        }

        // Write denormalized cache back to caretochina_bookings
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';
        $wpdb->update($table_bookings, [
            'status'          => 'confirmed',
            'invoice_status'  => 'Paid / Confirmed',
            'timeline_stage'  => 2,
            'amount'          => $paid_amount,
            'currency'        => $currency,
            'payment_gateway' => $gateway ?: $booking->payment_gateway,
            'paid_at'         => current_time('mysql'),
        ], ['id' => $booking->id]);

        // If booking was converted from a chat payment request, mark that request as accepted_paid
        $table_requests = $wpdb->prefix . 'caretochina_payment_requests';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_requests'") === $table_requests) {
            $wpdb->update(
                $table_requests,
                ['status' => 'accepted_paid'],
                ['converted_booking_id' => $booking->id]
            );
        }

        $this->add_audit_log($booking->id, $wc_order_id, 0, 'payment_succeeded', $paid_amount, 'Payment auto-confirmed via verified webhook (' . $gateway . ')');

        // Send Email Notification
        $this->send_payment_receipt_notifications($booking, $paid_amount, $currency);

        return true;
    }

    /**
     * Mark Payment Failed
     */
    public function mark_booking_payment_failed($booking_id, $wc_order_id = 0, $reason = '') {
        $booking = $this->get_booking($booking_id);
        if (!$booking) {
            return false;
        }

        if (!$wc_order_id && !empty($booking->wc_order_id)) {
            $wc_order_id = $booking->wc_order_id;
        }

        if ($wc_order_id) {
            $order = wc_get_order($wc_order_id);
            if ($order) {
                $order->update_status('failed', sprintf(__('Payment failed via webhook: %s', 'caretochina-medical'), $reason));
            }
        }

        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';
        $wpdb->update($table_bookings, [
            'status'         => 'payment_failed',
            'invoice_status' => 'Payment Failed',
        ], ['id' => $booking->id]);

        $this->add_audit_log($booking->id, $wc_order_id, 0, 'payment_failed', 0, 'Payment failed webhook: ' . $reason);

        return true;
    }

    /**
     * Process Refund (Full or Partial) with Atomic Lock & Balance Validation
     */
    public function refund_booking($booking_id, $refund_amount, $reason = '', $actor_id = 0) {
        // Atomic Refund Lock
        if (!CareToChina_Payment_Security::acquire_refund_lock($booking_id)) {
            return new WP_Error('refund_in_progress', __('A refund or cancellation request is already in progress for this booking. Please try again shortly.', 'caretochina-medical'));
        }

        try {
            $booking = $this->get_booking($booking_id);
            if (!$booking || empty($booking->wc_order_id)) {
                CareToChina_Payment_Security::release_refund_lock($booking_id);
                return new WP_Error('no_order', __('No WooCommerce order associated with this booking.', 'caretochina-medical'));
            }

            $order = wc_get_order($booking->wc_order_id);
            if (!$order) {
                CareToChina_Payment_Security::release_refund_lock($booking_id);
                return new WP_Error('order_not_found', __('Associated order not found.', 'caretochina-medical'));
            }

            // Server-side Validation against Remaining Refundable Balance
            $remaining_refundable = floatval($order->get_remaining_refund_amount());
            $refund_amount = floatval($refund_amount);

            if ($refund_amount <= 0 || $refund_amount > $remaining_refundable) {
                CareToChina_Payment_Security::release_refund_lock($booking_id);
                return new WP_Error(
                    'invalid_refund_amount',
                    sprintf(__('Refund amount ($%.2f) exceeds remaining refundable balance ($%.2f).', 'caretochina-medical'), $refund_amount, $remaining_refundable)
                );
            }

            $gateway_id = $order->get_payment_method();
            $gateway_handler = null;

            if ($gateway_id === 'stripe') {
                $gateway_handler = new CareToChina_Stripe_Gateway();
            } elseif ($gateway_id === 'paypal') {
                $gateway_handler = new CareToChina_PayPal_Gateway();
            }

            // Execute refund via Gateway API
            if ($gateway_handler && $gateway_handler->is_available()) {
                $result = $gateway_handler->process_refund($booking->id, $order->get_id(), $refund_amount, $reason);
                if (is_wp_error($result)) {
                    CareToChina_Payment_Security::release_refund_lock($booking_id);
                    return $result;
                }
            }

            // Create WooCommerce Refund
            $wc_refund = wc_create_refund([
                'amount'         => $refund_amount,
                'reason'         => $reason,
                'order_id'       => $order->get_id(),
                'refund_payment' => false, // Handled above via gateway API
            ]);

            if (is_wp_error($wc_refund)) {
                CareToChina_Payment_Security::release_refund_lock($booking_id);
                return $wc_refund;
            }

            $is_full_refund = floatval($order->get_remaining_refund_amount()) <= 0.01;
            $new_status = $is_full_refund ? 'refunded' : 'partially_refunded';
            $invoice_status = $is_full_refund ? 'Refunded' : 'Partially Refunded';

            $order->update_status($is_full_refund ? 'refunded' : 'processing', $reason);
            $order->save();

            // Write denormalized cache back to caretochina_bookings immediately
            global $wpdb;
            $table_bookings = $wpdb->prefix . 'caretochina_bookings';
            $wpdb->update($table_bookings, [
                'status'         => $new_status,
                'invoice_status' => $invoice_status,
            ], ['id' => $booking->id]);

            $action_type = $is_full_refund ? 'refund_full' : 'refund_partial';
            $this->add_audit_log($booking->id, $order->get_id(), $actor_id ?: get_current_user_id(), $action_type, $refund_amount, $reason);

            CareToChina_Payment_Security::release_refund_lock($booking_id);

            return [
                'success' => true,
                'amount'  => $refund_amount,
                'status'  => $new_status,
            ];

        } catch (Exception $e) {
            CareToChina_Payment_Security::release_refund_lock($booking_id);
            return new WP_Error('refund_exception', $e->getMessage());
        }
    }

    /**
     * Cancel Booking (Separate Workflows for Paid vs Unpaid)
     */
    public function cancel_booking($booking_id, $actor_id = 0, $confirm_no_refund = false) {
        $booking = $this->get_booking($booking_id);
        if (!$booking) {
            return new WP_Error('booking_not_found', __('Booking not found.', 'caretochina-medical'));
        }

        $is_paid = in_array($booking->status, ['paid', 'confirmed', 'partially_refunded']);

        if (!$is_paid) {
            // UNPAID BOOKING CANCELLATION: No Gateway Interaction
            if (!empty($booking->wc_order_id)) {
                $order = wc_get_order($booking->wc_order_id);
                if ($order) {
                    $order->update_status('cancelled', __('Booking cancelled by staff prior to payment.', 'caretochina-medical'));
                }
            }

            global $wpdb;
            $table_bookings = $wpdb->prefix . 'caretochina_bookings';
            $wpdb->update($table_bookings, [
                'status'         => 'cancelled',
                'invoice_status' => 'Cancelled',
            ], ['id' => $booking->id]);

            $this->add_audit_log($booking->id, $booking->wc_order_id, $actor_id ?: get_current_user_id(), 'booking_cancelled', 0, 'Unpaid booking cancelled');

            return ['success' => true, 'message' => __('Unpaid booking has been cancelled.', 'caretochina-medical')];
        }

        // PAID BOOKING CANCELLATION: Must refund or explicit no-refund confirmation
        if ($confirm_no_refund) {
            // Explicit Cancel without Refund
            if (!empty($booking->wc_order_id)) {
                $order = wc_get_order($booking->wc_order_id);
                if ($order) {
                    $order->update_status('cancelled', __('Booking cancelled by staff without financial refund per agreement.', 'caretochina-medical'));
                }
            }

            global $wpdb;
            $table_bookings = $wpdb->prefix . 'caretochina_bookings';
            $wpdb->update($table_bookings, [
                'status'         => 'cancelled',
                'invoice_status' => 'Cancelled (No Refund)',
            ], ['id' => $booking->id]);

            $this->add_audit_log($booking->id, $booking->wc_order_id, $actor_id ?: get_current_user_id(), 'booking_cancelled', 0, 'Paid booking cancelled without refund by staff request');

            return ['success' => true, 'message' => __('Paid booking cancelled without refund as confirmed.', 'caretochina-medical')];
        }

        // Route through full refund workflow
        $paid_amount = floatval($booking->amount);
        return $this->refund_booking($booking->id, $paid_amount, __('Full refund due to booking cancellation', 'caretochina-medical'), $actor_id);
    }

    /**
     * Add entry to Payment Audit Log table
     */
    public function add_audit_log($booking_id, $wc_order_id, $actor_id, $action, $amount, $notes) {
        global $wpdb;
        $table_logs = $wpdb->prefix . 'caretochina_payment_audit_logs';
        $wpdb->insert($table_logs, [
            'booking_id'  => intval($booking_id),
            'wc_order_id' => intval($wc_order_id),
            'actor_id'    => intval($actor_id),
            'action'      => sanitize_text_field($action),
            'amount'      => floatval($amount),
            'notes'       => sanitize_textarea_field($notes),
        ]);

        // Auto-sync CPT record
        if (class_exists('CareToChina_Transaction_CPT')) {
            CareToChina_Transaction_CPT::sync_transaction($booking_id, $wc_order_id, $action, $amount, $notes);
        }
    }

    /**
     * Notification helper on payment receipt
     */
    private function send_payment_receipt_notifications($booking, $amount, $currency) {
        $subject = sprintf(__('Payment Received - CareToChina Case #%s', 'caretochina-medical'), $booking->booking_code);
        $message = sprintf(
            __("Dear %s,\n\nWe have received your payment of %s %.2f for your upcoming medical treatment!\n\nBooking Code: %s\nHospital: %s\nSpecialty: %s\n\nYour treatment plan is now fully confirmed. You can view your updated portal roadmap at:\n%s\n\nBest regards,\nCareToChina Medical Concierge", 'caretochina-medical'),
            $booking->full_name, $currency, $amount, $booking->booking_code, $booking->hospital_name, $booking->specialty, home_url('/patient-dashboard/')
        );

        $headers = ['Content-Type: text/plain; charset=UTF-8', 'From: CareToChina <care@caretochina.com>'];
        wp_mail($booking->email, $subject, $message, $headers);
    }
}
