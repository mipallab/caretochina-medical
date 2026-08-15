<?php
if (!defined('ABSPATH')) {
    exit;
}

class CareToChina_Payment_Request_Manager {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Staff AJAX handlers
        add_action('wp_ajax_ctc_create_payment_request', [$this, 'handle_create_payment_request']);
        add_action('wp_ajax_ctc_cancel_payment_request', [$this, 'handle_cancel_payment_request']);

        // Patient AJAX handler
        add_action('wp_ajax_ctc_accept_payment_request', [$this, 'handle_accept_payment_request']);
    }

    /**
     * Staff creates a new payment request inside chat
     */
    public function handle_create_payment_request() {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-medical')]);
        }

        if (!current_user_can('caretochina_manage_bookings') && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized. Staff capability required.', 'caretochina-medical')]);
        }

        global $wpdb;
        $table_requests = $wpdb->prefix . 'caretochina_payment_requests';
        $table_messages = $wpdb->prefix . 'caretochina_messages';
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';

        $chat_thread_booking_id = intval($_POST['booking_id'] ?? 0);
        if ($chat_thread_booking_id <= 0) {
            wp_send_json_error(['message' => __('Invalid booking/thread context.', 'caretochina-medical')]);
        }

        $thread_booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_bookings WHERE id = %d", $chat_thread_booking_id));
        if (!$thread_booking) {
            wp_send_json_error(['message' => __('Chat thread booking not found.', 'caretochina-medical')]);
        }

        $patient_id = intval($thread_booking->patient_id);
        $current_user = wp_get_current_user();
        $created_by = $current_user->ID;

        $pricing_type = sanitize_text_field($_POST['pricing_type'] ?? '');
        $treatment_id = intval($_POST['treatment_id'] ?? 0);
        $pricing_plan_id = intval($_POST['pricing_plan_id'] ?? 0);
        $plan_name    = sanitize_text_field($_POST['plan_name'] ?? '');
        $custom_title = sanitize_text_field($_POST['custom_title'] ?? '');
        $custom_content = wp_kses_post($_POST['custom_content'] ?? '');
        $custom_amount = floatval($_POST['custom_amount'] ?? 0);
        $currency     = class_exists('CareToChina_Pricing_Plans') ? CareToChina_Pricing_Plans::get_store_currency() : get_option('ctc_payment_currency', 'USD');

        $final_amount = 0.00;
        $final_title = '';

        // Validation: Enforce EXACTLY ONE pricing source
        if ($pricing_type === 'treatment_plan') {
            if ($treatment_id <= 0) {
                wp_send_json_error(['message' => __('Please select a valid medical treatment specialty.', 'caretochina-medical')]);
            }

            $specialty_term = get_term($treatment_id, 'hospital_specialty');
            $treatment_label = ($specialty_term && !is_wp_error($specialty_term)) ? $specialty_term->name : __('Medical Specialty', 'caretochina-medical');

            // Look up price from Pricing Plans
            if ($pricing_plan_id > 0 && class_exists('CareToChina_Pricing_Plans')) {
                $plan = CareToChina_Pricing_Plans::instance()->get_plan($pricing_plan_id);
                if ($plan && $plan->is_active) {
                    $final_amount = floatval($plan->price);
                    $plan_name = $plan->name;
                    $currency = $plan->currency ?: $currency;
                }
            }

            if ($final_amount <= 0 && $custom_amount > 0) {
                $final_amount = $custom_amount;
            }

            if ($final_amount <= 0) {
                $final_amount = floatval($thread_booking->amount) > 0 ? floatval($thread_booking->amount) : 500.00;
            }

            $final_title = $treatment_label . (!empty($plan_name) ? ' (' . $plan_name . ')' : '');

        } elseif ($pricing_type === 'custom_amount') {
            if ($custom_amount <= 0) {
                wp_send_json_error(['message' => __('Please enter a valid positive custom amount.', 'caretochina-medical')]);
            }
            if (empty($custom_title)) {
                wp_send_json_error(['message' => __('Please enter a short description/label for this custom charge.', 'caretochina-medical')]);
            }
            $final_amount = $custom_amount;
            $final_title  = $custom_title;

        } elseif ($pricing_type === 'custom_treatment') {
            if (empty($custom_title)) {
                wp_send_json_error(['message' => __('Please enter a title for the custom treatment.', 'caretochina-medical')]);
            }
            if ($custom_amount <= 0) {
                wp_send_json_error(['message' => __('Please enter a valid positive price for this custom treatment.', 'caretochina-medical')]);
            }
            $final_amount = $custom_amount;
            $final_title  = $custom_title;

        } else {
            wp_send_json_error(['message' => __('Invalid pricing source selection. Exactly one pricing mode must be chosen.', 'caretochina-medical')]);
        }

        if ($final_amount <= 0) {
            wp_send_json_error(['message' => __('Payment request amount must be greater than zero.', 'caretochina-medical')]);
        }

        $request_code = 'PRQ-' . strtoupper(wp_generate_password(8, false, false));

        // Insert payment request
        $inserted = $wpdb->insert($table_requests, [
            'request_code'           => $request_code,
            'chat_thread_booking_id' => $chat_thread_booking_id,
            'converted_booking_id'   => 0,
            'patient_id'             => $patient_id,
            'created_by'             => $created_by,
            'pricing_type'           => $pricing_type,
            'treatment_id'           => $treatment_id,
            'pricing_plan_id'        => $pricing_plan_id,
            'plan_name'              => $plan_name,
            'custom_title'           => $final_title,
            'custom_content'         => $custom_content,
            'amount'                 => $final_amount,
            'currency'               => $currency,
            'status'                 => 'pending',
            'chat_message_id'        => 0,
            'created_at'             => current_time('mysql'),
        ]);

        if (!$inserted) {
            wp_send_json_error(['message' => __('Failed to create payment request record in database.', 'caretochina-medical')]);
        }

        $request_id = $wpdb->insert_id;
        $staff_name = $current_user->exists() ? 'Staff (' . $current_user->display_name . ')' : 'Staff (Coordinator)';

        // Insert chat message of type 'payment_request'
        $msg_text = sprintf(__('Payment Request: %s — %s %s', 'caretochina-medical'), $final_title, number_format($final_amount, 2), $currency);
        $wpdb->insert($table_messages, [
            'booking_id'         => $chat_thread_booking_id,
            'sender_type'        => 'coordinator',
            'sender_name'        => $staff_name,
            'message'            => $msg_text,
            'message_type'       => 'payment_request',
            'payment_request_id' => $request_id,
            'is_read'            => 0,
            'created_at'         => current_time('mysql'),
        ]);

        $message_id = $wpdb->insert_id;
        $wpdb->update($table_requests, ['chat_message_id' => $message_id], ['id' => $request_id]);

        wp_send_json_success([
            'message'    => __('Payment request sent to patient successfully.', 'caretochina-medical'),
            'request_id' => $request_id,
            'code'       => $request_code,
            'amount'     => $final_amount,
        ]);
    }

    /**
     * Staff cancels / withdraws a pending payment request
     */
    public function handle_cancel_payment_request() {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-medical')]);
        }

        if (!current_user_can('caretochina_manage_bookings') && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized. Staff capability required.', 'caretochina-medical')]);
        }

        global $wpdb;
        $table_requests = $wpdb->prefix . 'caretochina_payment_requests';
        $request_id = intval($_POST['request_id'] ?? 0);

        if ($request_id <= 0) {
            wp_send_json_error(['message' => __('Invalid payment request ID.', 'caretochina-medical')]);
        }

        $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_requests WHERE id = %d", $request_id));
        if (!$request) {
            wp_send_json_error(['message' => __('Payment request not found.', 'caretochina-medical')]);
        }

        // VALIDATION: Reject cancellation if already paid
        if ($request->status === 'accepted_paid') {
            wp_send_json_error(['message' => __('Paid payment requests cannot be cancelled directly. Please issue a refund through the refund management desk.', 'caretochina-medical')]);
        }

        // Atomic update status to cancelled
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE $table_requests SET status = 'cancelled' WHERE id = %d AND status IN ('pending', 'processing')",
            $request_id
        ));

        if ($updated !== false) {
            wp_send_json_success(['message' => __('Payment request withdrawn successfully.', 'caretochina-medical')]);
        }

        wp_send_json_error(['message' => __('Failed to cancel payment request or already finalized.', 'caretochina-medical')]);
    }

    /**
     * Patient clicks "Accept & Pay" on a payment request card
     */
    public function handle_accept_payment_request() {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_patient_nonce') && !wp_verify_nonce($nonce, 'careyou_patient_nonce') && !wp_verify_nonce($nonce, 'wp_rest')) {
            wp_send_json_error(['message' => __('Security verification failed.', 'caretochina-medical')]);
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Please log in as a patient to proceed with payment.', 'caretochina-medical')]);
        }

        global $wpdb;
        $table_requests = $wpdb->prefix . 'caretochina_payment_requests';
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';

        $request_id = intval($_POST['request_id'] ?? 0);
        if ($request_id <= 0) {
            wp_send_json_error(['message' => __('Invalid payment request ID.', 'caretochina-medical')]);
        }

        $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_requests WHERE id = %d", $request_id));
        if (!$request) {
            wp_send_json_error(['message' => __('Payment request not found.', 'caretochina-medical')]);
        }

        // SECURITY CHECK: Dual-layer patient ownership verification
        $current_patient_id = get_current_user_id();
        if (intval($request->patient_id) !== $current_patient_id) {
            wp_send_json_error(['message' => __('Access denied. This payment request belongs to another patient.', 'caretochina-medical')]);
        }

        if ($request->status === 'cancelled') {
            wp_send_json_error(['message' => __('This payment request has been cancelled or withdrawn by the clinic.', 'caretochina-medical')]);
        }

        if ($request->status === 'accepted_paid') {
            wp_send_json_error(['message' => __('This payment request has already been paid.', 'caretochina-medical')]);
        }

        // ATOMIC COMPARE-AND-SWAP DUPLICATE-ACCEPT IDEMPOTENCY LOCK
        $affected = $wpdb->query($wpdb->prepare(
            "UPDATE $table_requests SET status = 'processing' WHERE id = %d AND status = 'pending'",
            $request_id
        ));

        $converted_booking_id = intval($request->converted_booking_id);

        // If not already converted to a dedicated booking row, create one now
        if ($converted_booking_id <= 0) {
            $patient_user = wp_get_current_user();
            $booking_code = 'BK-' . strtoupper(wp_generate_password(8, false, false));

            $thread_booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_bookings WHERE id = %d", intval($request->chat_thread_booking_id)));

            $full_name = $patient_user->display_name ?: ($thread_booking ? $thread_booking->full_name : 'Patient');
            $email     = $patient_user->user_email ?: ($thread_booking ? $thread_booking->email : '');
            $phone     = get_user_meta($current_patient_id, 'patient_phone', true) ?: ($thread_booking ? $thread_booking->phone : '');

            $wpdb->insert($table_bookings, [
                'booking_code'     => $booking_code,
                'patient_id'       => $current_patient_id,
                'hospital_id'      => $thread_booking ? intval($thread_booking->hospital_id) : 0,
                'hospital_name'    => $thread_booking ? $thread_booking->hospital_name : 'CareToChina Medical Services',
                'specialty'        => sanitize_text_field($request->custom_title),
                'pricing_plan_id'  => intval($request->pricing_plan_id),
                'treatment_timing' => $thread_booking ? $thread_booking->treatment_timing : 'Flexible',
                'quote_details'    => wp_kses_post($request->custom_content),
                'country'          => $thread_booking ? $thread_booking->country : '',
                'full_name'        => $full_name,
                'email'            => $email,
                'phone'            => $phone,
                'status'           => 'pending',
                'timeline_stage'   => 1,
                'invoice_status'   => 'Payment In Progress',
                'amount'           => floatval($request->amount),
                'currency'         => sanitize_text_field($request->currency),
                'created_at'       => current_time('mysql'),
            ]);

            $converted_booking_id = $wpdb->insert_id;
            $wpdb->update($table_requests, ['converted_booking_id' => $converted_booking_id], ['id' => $request_id]);
        }

        wp_send_json_success([
            'booking_id' => $converted_booking_id,
            'request_id' => $request_id,
            'amount'     => floatval($request->amount),
            'currency'   => $request->currency,
            'title'      => $request->custom_title,
        ]);
    }

    /**
     * Render the payment request card HTML for chat threads
     */
    public static function render_card($request_id, $is_staff = false) {
        global $wpdb;
        $table_requests = $wpdb->prefix . 'caretochina_payment_requests';
        $req = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_requests WHERE id = %d", $request_id));

        if (!$req) {
            return '';
        }

        $currency_symbol = '$';
        if ($req->currency === 'EUR') $currency_symbol = '€';
        if ($req->currency === 'GBP') $currency_symbol = '£';
        if ($req->currency === 'CNY') $currency_symbol = '¥';

        $status = $req->status;
        $status_label = __('Pending Payment', 'caretochina-medical');
        $status_bg = '#FEF3C7';
        $status_color = '#92400E';

        if ($status === 'processing') {
            $status_label = __('Payment In Progress', 'caretochina-medical');
            $status_bg = '#E0F2FE';
            $status_color = '#0369A1';
        } elseif ($status === 'accepted_paid') {
            $status_label = __('Payment Confirmed ✓', 'caretochina-medical');
            $status_bg = '#D1FAE5';
            $status_color = '#065F46';
        } elseif ($status === 'cancelled') {
            $status_label = __('Withdrawn / Cancelled', 'caretochina-medical');
            $status_bg = '#FEE2E2';
            $status_color = '#991B1B';
        }

        $content_clean = wp_kses_post($req->custom_content);

        ob_start();
        ?>
        <div class="ctc-payment-request-card" data-request-id="<?php echo esc_attr($req->id); ?>" style="background:#FFFFFF; border:2px solid #0F766E; border-radius:14px; padding:18px; margin:12px 0; font-family:'Manrope', sans-serif; box-shadow:0 4px 12px rgba(15,118,110,0.08); max-width:420px; text-align:left;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <span style="font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:0.8px; color:#0F766E; background:#CCFBF1; padding:3px 8px; border-radius:6px;">
                    <i class="fa-solid fa-file-invoice-dollar"></i> <?php echo esc_html($req->request_code); ?>
                </span>
                <span style="font-size:11px; font-weight:700; background:<?php echo esc_attr($status_bg); ?>; color:<?php echo esc_attr($status_color); ?>; padding:3px 10px; border-radius:20px;">
                    <?php echo esc_html($status_label); ?>
                </span>
            </div>

            <h4 style="margin:0 0 6px 0; color:#0F172A; font-size:16px; font-weight:700;"><?php echo esc_html($req->custom_title); ?></h4>

            <?php if (!empty($content_clean)) : ?>
                <div style="font-size:12px; color:#64748B; margin-bottom:12px; line-height:1.5;">
                    <?php echo $content_clean; ?>
                </div>
            <?php endif; ?>

            <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:10px; padding:10px 14px; margin-bottom:14px; display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size:12px; color:#64748B; font-weight:600;"><?php _e('Authoritative Total:', 'caretochina-medical'); ?></span>
                <span style="font-size:18px; font-weight:800; color:#0F766E;"><?php echo esc_html($currency_symbol . number_format($req->amount, 2) . ' ' . $req->currency); ?></span>
            </div>

            <?php if (!$is_staff) : ?>
                <!-- PATIENT ACTIONS -->
                <?php if ($status === 'pending' || $status === 'processing') : ?>
                    <button type="button" class="ctc-btn-accept-pay" onclick="ctcAcceptPaymentRequest(<?php echo esc_attr($req->id); ?>)" style="width:100%; background:#0F766E; color:#FFFFFF; border:none; padding:12px 18px; border-radius:10px; font-weight:700; font-size:14px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; box-shadow:0 4px 10px rgba(15,118,110,0.25); transition:all 0.2s;">
                        <i class="fa-solid fa-lock"></i> <?php _e('Accept & Pay Online', 'caretochina-medical'); ?>
                    </button>
                <?php elseif ($status === 'accepted_paid') : ?>
                    <div style="text-align:center; font-size:13px; font-weight:700; color:#059669; padding:8px 0;">
                        <i class="fa-solid fa-circle-check"></i> <?php _e('Payment completed successfully.', 'caretochina-medical'); ?>
                    </div>
                <?php else : ?>
                    <div style="text-align:center; font-size:13px; color:#94A3B8; padding:8px 0;">
                        <i class="fa-solid fa-ban"></i> <?php _e('This request has been cancelled by medical staff.', 'caretochina-medical'); ?>
                    </div>
                <?php endif; ?>

            <?php else : ?>
                <!-- STAFF ACTIONS -->
                <?php if ($status === 'pending' || $status === 'processing') : ?>
                    <button type="button" class="ctc-btn-staff-cancel-req" onclick="ctcStaffCancelPaymentRequest(<?php echo esc_attr($req->id); ?>)" style="width:100%; background:#FFF; color:#DC2626; border:1px solid #FCA5A5; padding:8px 14px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer;">
                        <i class="fa-solid fa-xmark"></i> <?php _e('Cancel / Withdraw Request', 'caretochina-medical'); ?>
                    </button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
