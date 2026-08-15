<?php
if (!defined('ABSPATH')) {
    exit;
}

class CareToChina_Transaction_CPT {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('init', [$this, 'register_cpt']);
        add_filter('manage_ctc_transaction_posts_columns', [$this, 'set_custom_columns']);
        add_action('manage_ctc_transaction_posts_custom_column', [$this, 'render_custom_column'], 10, 2);
        add_filter('post_row_actions', [$this, 'add_row_actions'], 10, 2);
    }

    public function register_cpt() {
        $labels = [
            'name'               => __('Payment Transactions', 'caretochina-medical'),
            'singular_name'      => __('Transaction', 'caretochina-medical'),
            'menu_name'          => __('Transactions', 'caretochina-medical'),
            'all_items'          => __('All Transactions', 'caretochina-medical'),
            'view_item'          => __('View Transaction', 'caretochina-medical'),
            'search_items'       => __('Search Transactions', 'caretochina-medical'),
            'not_found'          => __('No transactions found', 'caretochina-medical'),
        ];

        register_post_type('ctc_transaction', [
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => 'caretochina-staff-desk',
            'menu_icon'           => 'dashicons-money-alt',
            'supports'            => ['title', 'custom-fields'],
            'capability_type'     => ['ctc_transaction', 'ctc_transactions'],
            'map_meta_cap'        => true,
            'capabilities'        => [
                // Meta capabilities (mapped to post IDs by map_meta_cap)
                'edit_post'              => 'edit_ctc_transaction',
                'read_post'              => 'read_ctc_transaction',
                'delete_post'            => 'delete_ctc_transaction',
                // Primitive capabilities (checked against user role/capabilities)
                'edit_posts'             => 'caretochina_manage_bookings',
                'edit_others_posts'      => 'caretochina_manage_bookings',
                'publish_posts'          => 'caretochina_manage_bookings',
                'read_private_posts'     => 'caretochina_manage_bookings',
                'read'                   => 'caretochina_manage_bookings',
                'delete_posts'           => 'caretochina_manage_bookings',
                'delete_others_posts'    => 'caretochina_manage_bookings',
                'delete_private_posts'   => 'caretochina_manage_bookings',
                'delete_published_posts' => 'caretochina_manage_bookings',
                'create_posts'           => 'caretochina_manage_bookings',
            ],
        ]);
    }

    public function set_custom_columns($columns) {
        $new_cols = [];
        $new_cols['cb']          = $columns['cb'];
        $new_cols['title']       = __('Transaction Ref', 'caretochina-medical');
        $new_cols['patient']     = __('Patient', 'caretochina-medical');
        $new_cols['description'] = __('Service / Description', 'caretochina-medical');
        $new_cols['amount']      = __('Amount', 'caretochina-medical');
        $new_cols['gateway']     = __('Gateway', 'caretochina-medical');
        $new_cols['status']      = __('Financial Status', 'caretochina-medical');
        $new_cols['staff']       = __('Initiator / Staff', 'caretochina-medical');
        $new_cols['date']        = __('Date', 'caretochina-medical');
        return $new_cols;
    }

    public function render_custom_column($column, $post_id) {
        switch ($column) {
            case 'patient':
                $patient_name  = get_post_meta($post_id, '_ctc_patient_name', true);
                $patient_email = get_post_meta($post_id, '_ctc_patient_email', true);
                echo '<strong>' . esc_html($patient_name ?: __('Unknown Patient', 'caretochina-medical')) . '</strong>';
                if (!empty($patient_email)) {
                    echo '<br><span style="color:#64748B; font-size:12px;">' . esc_html($patient_email) . '</span>';
                }
                break;

            case 'description':
                $desc = get_post_meta($post_id, '_ctc_description', true);
                $bcode = get_post_meta($post_id, '_ctc_booking_code', true);
                echo esc_html($desc ?: '—');
                if (!empty($bcode)) {
                    echo '<br><code style="font-size:11px; color:#0F766E;">' . esc_html($bcode) . '</code>';
                }
                break;

            case 'amount':
                $amount   = floatval(get_post_meta($post_id, '_ctc_amount', true));
                $currency = get_post_meta($post_id, '_ctc_currency', true) ?: 'USD';
                echo '<strong style="color:#0F766E; font-size:14px;">' . esc_html(number_format($amount, 2) . ' ' . $currency) . '</strong>';
                break;

            case 'gateway':
                $gateway = get_post_meta($post_id, '_ctc_gateway', true);
                if (strtolower($gateway) === 'stripe') {
                    echo '<span style="color:#635BFF; font-weight:700;"><i class="dashicons dashicons-credit-card"></i> Stripe</span>';
                } elseif (strtolower($gateway) === 'paypal') {
                    echo '<span style="color:#003087; font-weight:700;"><i class="dashicons dashicons-money"></i> PayPal</span>';
                } else {
                    echo esc_html($gateway ?: '—');
                }
                break;

            case 'status':
                $status = get_post_meta($post_id, '_ctc_status', true);
                $bg = '#E2E8F0';
                $color = '#334155';
                $label = ucfirst($status);

                if ($status === 'paid') {
                    $bg = '#D1FAE5'; $color = '#065F46'; $label = 'Paid';
                } elseif ($status === 'refund_full' || $status === 'refunded') {
                    $bg = '#FEE2E2'; $color = '#991B1B'; $label = 'Refunded (Full)';
                } elseif ($status === 'refund_partial') {
                    $bg = '#FEF3C7'; $color = '#92400E'; $label = 'Partially Refunded';
                } elseif ($status === 'payment_failed') {
                    $bg = '#FEE2E2'; $color = '#991B1B'; $label = 'Failed';
                } elseif ($status === 'cancelled') {
                    $bg = '#F1F5F9'; $color = '#64748B'; $label = 'Cancelled';
                }

                echo sprintf(
                    '<span style="background:%s; color:%s; font-size:11px; font-weight:700; padding:4px 8px; border-radius:12px; display:inline-block;">%s</span>',
                    esc_attr($bg),
                    esc_attr($color),
                    esc_html($label)
                );
                break;

            case 'staff':
                $staff_name = get_post_meta($post_id, '_ctc_staff_name', true);
                echo esc_html($staff_name ?: __('System / Online', 'caretochina-medical'));
                break;
        }
    }

    public function add_row_actions($actions, $post) {
        if ($post->post_type !== 'ctc_transaction') {
            return $actions;
        }

        if (!current_user_can('caretochina_manage_bookings')) {
            return $actions;
        }

        unset($actions['inline hide-if-no-js']); // Hide quick edit

        $booking_id = intval(get_post_meta($post->ID, '_ctc_booking_id', true));
        $status     = get_post_meta($post->ID, '_ctc_status', true);

        if ($booking_id > 0 && in_array($status, ['paid', 'refund_partial'])) {
            $actions['ctc_refund'] = sprintf(
                '<a href="%s" style="color:#DC2626; font-weight:700;"><span class="dashicons dashicons-undo" style="font-size:14px; vertical-align:middle;"></span> %s</a>',
                admin_url('admin.php?page=caretochina-staff-desk&action=refund&booking_id=' . $booking_id),
                __('Issue Refund', 'caretochina-medical')
            );
        }

        return $actions;
    }

    /**
     * Auto-sync CPT record from Payment Manager lifecycle events
     */
    public static function sync_transaction($booking_id, $wc_order_id, $action, $amount, $notes = '') {
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_bookings WHERE id = %d", $booking_id));

        if (!$booking) {
            return;
        }

        // Check if transaction post already exists for this booking/order
        $existing_posts = get_posts([
            'post_type'      => 'ctc_transaction',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'meta_key'       => '_ctc_booking_id',
            'meta_value'     => $booking_id,
        ]);

        $status = $booking->status;
        if ($action === 'refund_full') $status = 'refund_full';
        if ($action === 'refund_partial') $status = 'refund_partial';
        if ($action === 'payment_failed') $status = 'payment_failed';
        if ($action === 'booking_cancelled') $status = 'cancelled';

        $post_title = sprintf('TRX #%s (%s)', $booking->booking_code, $booking->full_name);

        if (!empty($existing_posts)) {
            $post_id = $existing_posts[0]->ID;
            wp_update_post([
                'ID'         => $post_id,
                'post_title' => $post_title,
            ]);
        } else {
            $post_id = wp_insert_post([
                'post_type'   => 'ctc_transaction',
                'post_title'  => $post_title,
                'post_status' => 'publish',
            ]);
        }

        if ($post_id && !is_wp_error($post_id)) {
            update_post_meta($post_id, '_ctc_booking_id', $booking_id);
            update_post_meta($post_id, '_ctc_booking_code', $booking->booking_code);
            update_post_meta($post_id, '_ctc_wc_order_id', $wc_order_id ?: intval($booking->wc_order_id));
            update_post_meta($post_id, '_ctc_patient_id', intval($booking->patient_id));
            update_post_meta($post_id, '_ctc_patient_name', sanitize_text_field($booking->full_name));
            update_post_meta($post_id, '_ctc_patient_email', sanitize_email($booking->email));
            update_post_meta($post_id, '_ctc_description', sanitize_text_field($booking->specialty));
            update_post_meta($post_id, '_ctc_amount', floatval($booking->amount));
            update_post_meta($post_id, '_ctc_currency', sanitize_text_field($booking->currency));
            update_post_meta($post_id, '_ctc_gateway', sanitize_text_field($booking->payment_gateway));
            update_post_meta($post_id, '_ctc_status', sanitize_text_field($status));
            update_post_meta($post_id, '_ctc_last_action', sanitize_text_field($action));
            if (!empty($notes)) {
                update_post_meta($post_id, '_ctc_audit_notes', sanitize_textarea_field($notes));
            }
        }
    }
}
