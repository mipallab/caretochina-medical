<?php
if (!defined('ABSPATH')) {
    exit;
}

class CareToChina_Payment_Reconciliation {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('caretochina_payment_reconciliation_cron', [$this, 'run_reconciliation']);
        
        // Schedule event if not already scheduled
        if (!wp_next_scheduled('caretochina_payment_reconciliation_cron')) {
            wp_schedule_event(time() + 3600, 'hourly', 'caretochina_payment_reconciliation_cron');
        }
    }

    /**
     * Run periodic reconciliation check for stale pending orders
     */
    public function run_reconciliation() {
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Query pending orders older than 30 minutes
        $cutoff_time = gmdate('Y-m-d H:i:s', time() - 1800);

        $orders = wc_get_orders([
            'status'        => 'pending',
            'date_created'  => '<' . $cutoff_time,
            'limit'         => 20,
        ]);

        if (empty($orders)) {
            return;
        }

        foreach ($orders as $order) {
            $booking_id = intval($order->get_meta('_caretochina_booking_id'));
            if (!$booking_id) {
                continue;
            }

            $gateway = $order->get_payment_method();
            $transaction_id = $order->get_transaction_id();

            if ($gateway === 'stripe' && !empty($transaction_id)) {
                $stripe = new CareToChina_Stripe_Gateway();
                if ($stripe->is_available()) {
                    // Check Stripe PaymentIntent directly via API
                    // If paid, call confirm_booking_payment
                }
            }
        }
    }
}
