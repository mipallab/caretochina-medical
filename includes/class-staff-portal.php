<?php
if (!defined('ABSPATH')) {
    exit;
}

class CareToChina_Staff_Portal {
    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_shortcode('caretochina_staff_portal', [$this, 'render_staff_portal']);
        add_shortcode('careyou_staff_portal', [$this, 'render_staff_portal']); // Backward compatibility alias

        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this, 'restrict_staff_admin_access']);
        add_action('admin_bar_menu', [$this, 'add_admin_bar_notification_node'], 99);
        add_filter('show_admin_bar', [$this, 'hide_admin_bar_on_staff_portal'], 999);

        // AJAX Action Handlers
        add_action('wp_ajax_caretochina_staff_update_booking_status', [$this, 'handle_update_booking_status']);
        add_action('wp_ajax_caretochina_staff_send_chat', [$this, 'handle_send_chat']);
        add_action('wp_ajax_caretochina_staff_get_chat', [$this, 'handle_get_staff_chat']);
        add_action('wp_ajax_caretochina_staff_update_timeline', [$this, 'handle_update_timeline']);
        add_action('wp_ajax_caretochina_staff_update_invoice', [$this, 'handle_update_invoice']);
        add_action('wp_ajax_caretochina_admin_create_staff', [$this, 'handle_create_staff_account']);
        add_action('wp_ajax_caretochina_staff_login', [$this, 'handle_staff_login']);
        add_action('wp_ajax_nopriv_caretochina_staff_login', [$this, 'handle_staff_login']);
        add_action('wp_ajax_caretochina_staff_get_booking_details', [$this, 'handle_get_booking_details']);
        add_action('wp_ajax_caretochina_staff_toggle_restrict', [$this, 'handle_toggle_restrict']);

        // Real-Time dashboard updates & Typing indicators
        add_action('wp_ajax_caretochina_staff_get_bookings', [$this, 'handle_get_bookings']);
        add_action('wp_ajax_caretochina_staff_check_new_bookings', [$this, 'handle_check_new_bookings']);
        add_action('wp_ajax_caretochina_staff_send_typing', [$this, 'handle_send_typing']);

        // Payment & Refund Staff Actions
        add_action('wp_ajax_caretochina_staff_cancel_booking', [$this, 'handle_staff_cancel_booking']);
        add_action('wp_ajax_caretochina_staff_refund_booking', [$this, 'handle_staff_refund_booking']);
        add_action('wp_ajax_caretochina_staff_get_payment_audit_logs', [$this, 'handle_get_payment_audit_logs']);

        // Backward compatibility AJAX aliases
        add_action('wp_ajax_careyou_staff_update_booking_status', [$this, 'handle_update_booking_status']);
        add_action('wp_ajax_careyou_staff_send_chat', [$this, 'handle_send_chat']);
        add_action('wp_ajax_careyou_staff_get_chat', [$this, 'handle_get_staff_chat']);
        add_action('wp_ajax_careyou_staff_update_timeline', [$this, 'handle_update_timeline']);
        add_action('wp_ajax_careyou_staff_update_invoice', [$this, 'handle_update_invoice']);
        add_action('wp_ajax_careyou_admin_create_staff', [$this, 'handle_create_staff_account']);
        add_action('wp_ajax_careyou_staff_login', [$this, 'handle_staff_login']);
        add_action('wp_ajax_nopriv_careyou_staff_login', [$this, 'handle_staff_login']);
        add_action('wp_ajax_caretochina_staff_verify_booking', [$this, 'handle_verify_booking']);
        add_action('wp_ajax_careyou_staff_verify_booking', [$this, 'handle_verify_booking']);
        add_action('wp_ajax_caretochina_admin_get_staff', [$this, 'handle_get_staff_list']);
        add_action('wp_ajax_caretochina_admin_delete_staff', [$this, 'handle_delete_staff_account']);
        add_action('wp_ajax_careyou_admin_get_staff', [$this, 'handle_get_staff_list']);
        add_action('wp_ajax_careyou_admin_delete_staff', [$this, 'handle_delete_staff_account']);
        add_action('wp_ajax_caretochina_admin_delete_patient_data', [$this, 'handle_admin_delete_patient_data']);
        add_action('wp_ajax_careyou_admin_delete_patient_data', [$this, 'handle_admin_delete_patient_data']);
        add_action('wp_ajax_caretochina_staff_check_unread_updates', [$this, 'handle_staff_check_unread_updates']);
    }

    public function get_unread_notifications_count() {
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';
        $table_messages = $wpdb->prefix . 'caretochina_messages';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $pending_bookings = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}caretochina_bookings WHERE status = %s", 'pending')));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $unread_messages = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}caretochina_messages WHERE sender_type = %s AND is_read = %d", 'patient', 0)));

        return $pending_bookings + $unread_messages;
    }

    public function register_admin_menu() {
        $unread_count = $this->get_unread_notifications_count();
        $menu_title = __('Care Staff Desk', 'caretochina-medical');
        if ($unread_count > 0) {
            $menu_title .= sprintf(' <span class="awaiting-mod update-plugins count-%d"><span class="plugin-count">%d</span></span>', $unread_count, $unread_count);
        }

        add_menu_page(
            'Care Staff Portal',
            $menu_title,
            'edit_posts',
            'caretochina-staff-desk',
            [$this, 'render_admin_staff_desk'],
            'dashicons-admin-users',
            26
        );

        // Alias redirection menu page
        add_submenu_page(
            null,
            'CareYou Staff Desk Alias',
            'CareYou Staff Desk Alias',
            'edit_posts',
            'careyou-staff-desk',
            [$this, 'render_admin_staff_desk']
        );
    }

    public function render_staff_portal() {
        if (!wp_style_is('font-awesome', 'registered') && !wp_style_is('font-awesome', 'enqueued')) {
            wp_register_style('font-awesome', CARETOCHINA_MEDICAL_URL . 'assets/vendor/font-awesome/css/all.min.css', [], '6.4.0');
        }
        wp_enqueue_style('font-awesome');
        wp_enqueue_style('caretochina-staff-style');
        wp_enqueue_script('caretochina-staff-script');

        ob_start();
        $current_user = wp_get_current_user();
        $is_staff = is_user_logged_in() && (current_user_can('edit_posts') || in_array('medical_staff', (array)$current_user->roles));

        if (!$is_staff) {
            $this->render_staff_login_ui();
        } else {
            $this->render_portal_ui();
        }
        return ob_get_clean();
    }

    public function render_admin_staff_desk() {
        echo '<div class="wrap caretochina-admin-staff-wrap" style="padding:10px 0; margin:10px 20px 0 2px; font-family:\'Inter\', sans-serif; max-width:100%; box-sizing:border-box;">';
        echo '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">';
        echo '<h1 class="wp-heading-inline" style="margin:0;"><i class="fa-solid fa-user-doctor text-teal"></i> ' . esc_html__('Medical Technical Person Portal', 'caretochina-medical') . '</h1>';
        if (current_user_can('manage_options')) {
            echo '<button type="button" onclick="jQuery(\'#admin-create-staff-modal\').css(\'display\', \'flex\')" class="button button-primary" style="background:#0F766E; border-color:#0F766E; font-weight:700;"><i class="fa-solid fa-user-plus"></i> + ' . esc_html__('Create New Staff Account', 'caretochina-medical') . '</button>';
        }
        echo '</div>';

        $this->render_portal_ui();

        if (current_user_can('manage_options')) {
            ?>
            <div id="admin-create-staff-modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(15, 23, 42, 0.65); backdrop-filter:blur(6px); z-index:100000; align-items:center; justify-content:center;">
                <div style="background:#FFFFFF; border-radius:24px; width:550px; max-width:90%; padding:32px; box-shadow:0 20px 40px rgba(0,0,0,0.3); font-family:'Inter', sans-serif; max-height:90vh; overflow-y:auto;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #E2E8F0; padding-bottom:12px;">
                        <h2 style="margin:0; font-family:'Manrope'; color:#0F172A; font-size:22px;"><i class="fa-solid fa-user-plus" style="color:#0F766E;"></i> <?php esc_html_e('Create Medical Staff Account', 'caretochina-medical'); ?></h2>
                        <button type="button" onclick="jQuery('#admin-create-staff-modal').hide()" style="background:none; border:none; font-size:20px; cursor:pointer; color:#64748B;">&times;</button>
                    </div>

                    <form id="admin-create-staff-form" onsubmit="createStaffAccount(event)">
                        <div style="margin-bottom:16px;">
                            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:6px;"><?php esc_html_e('Staff Full Name *', 'caretochina-medical'); ?></label>
                            <input type="text" id="stf_name" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1;">
                        </div>
                        <div style="margin-bottom:16px;">
                            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:6px;"><?php esc_html_e('Staff Email Address *', 'caretochina-medical'); ?></label>
                            <input type="email" id="stf_email" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1;">
                        </div>
                        <div style="margin-bottom:16px;">
                            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:6px;"><?php esc_html_e('Staff Username *', 'caretochina-medical'); ?></label>
                            <input type="text" id="stf_user" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1;">
                        </div>
                        <div style="margin-bottom:20px;">
                            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:6px;"><?php esc_html_e('Set Password *', 'caretochina-medical'); ?></label>
                            <input type="password" id="stf_pass" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1;">
                        </div>

                        <div style="display:flex; justify-content:flex-end; gap:12px; border-top:1px solid #E2E8F0; padding-top:16px;">
                            <button type="button" onclick="jQuery('#admin-create-staff-modal').hide()" class="button button-secondary"><?php esc_html_e('Cancel', 'caretochina-medical'); ?></button>
                            <button type="submit" id="stf_submit_btn" class="button button-primary" style="background:#0F766E; border-color:#0F766E; font-weight:700; padding:6px 20px;"><?php esc_html_e('Create Staff Credential', 'caretochina-medical'); ?></button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
            function createStaffAccount(e) {
                e.preventDefault();
                var btn = jQuery('#stf_submit_btn');
                btn.prop('disabled', true).text('<?php echo esc_js(__('Creating...', 'caretochina-medical')); ?>');
                var nonce = '<?php echo esc_js(wp_create_nonce("caretochina_staff_admin_nonce")); ?>';

                var ajax_url = '/wp-admin/admin-ajax.php';
                try {
                    if (typeof ajaxurl !== 'undefined') {
                        ajax_url = new URL(ajaxurl).pathname;
                    }
                } catch(e) {}

                jQuery.post(ajax_url, {
                    action: 'caretochina_admin_create_staff',
                    name: jQuery('#stf_name').val(),
                    email: jQuery('#stf_email').val(),
                    username: jQuery('#stf_user').val(),
                    password: jQuery('#stf_pass').val(),
                    _wpnonce: nonce
                }, function(res) {
                    if (res.success) {
                        alert('<?php echo esc_js(__('Medical Staff Account created!', 'caretochina-medical')); ?> Username: ' + res.data.username);
                        jQuery('#admin-create-staff-modal').hide();
                        // If we are on the Staff Management tab, refresh staff list
                        if (typeof refreshAdminStaffList === 'function') {
                            refreshAdminStaffList();
                        }
                    } else {
                        alert(res.data.message);
                    }
                    btn.prop('disabled', false).text('<?php echo esc_js(__('Create Staff Credential', 'caretochina-medical')); ?>');
                });
            }
            </script>
            <?php
        }
        echo '</div>';
    }

    private function render_staff_login_ui() {
        ?>
        <style>
        .ctc-staff-login-wrapper {
            max-width: 600px !important;
            margin: 50px auto;
            padding: 0 16px;
            width: 100%;
            box-sizing: border-box;
        }
        .ctc-staff-login-card {
            padding: 40px;
            background: #FFFFFF;
            border-radius: 24px;
            border: 1px solid #E2E8F0;
            box-shadow: 0 20px 40px -15px rgba(15, 118, 110, 0.15);
            transition: all 0.3s ease;
        }
        .ctc-staff-login-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: #CCFBF1;
            color: #0F766E;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 12px;
        }
        .ctc-staff-login-title {
            font-family: 'Manrope', sans-serif;
            color: #0F172A;
            margin: 0 0 6px 0;
            font-size: 24px;
            font-weight: 700;
        }
        .ctc-staff-login-desc {
            color: #64748B;
            font-size: 14px;
            margin: 0;
        }
        .ctc-staff-login-label {
            display: block;
            font-weight: 600;
            font-family: 'Manrope', sans-serif;
            color: #0F172A;
            font-size: 14px;
            margin-bottom: 8px;
        }
        .ctc-staff-login-input {
            width: 100%;
            padding: 14px 18px;
            border-radius: 12px;
            border: 1px solid #CBD5E1;
            background-color: #FFFFFF;
            color: #0F172A;
            font-size: 15px;
            outline: none;
            box-sizing: border-box;
            transition: all 0.2s ease;
        }
        .ctc-staff-login-input:focus {
            border-color: #0F766E;
            box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.15);
        }
        .ctc-staff-login-btn {
            width: 100%;
            padding: 16px;
            border-radius: 999px;
            background: #0F766E;
            color: #FFFFFF;
            font-family: 'Manrope', sans-serif;
            font-size: 16px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .ctc-staff-login-btn:hover {
            background: #0D6E66;
            transform: translateY(-1px);
        }

        /* DARK MODE SUPPORT (Only when website theme is dark) */
        html.dark-theme .ctc-staff-login-card,
        body.dark-theme .ctc-staff-login-card,
        html.dark .ctc-staff-login-card,
        body.dark .ctc-staff-login-card,
        body[data-theme="dark"] .ctc-staff-login-card {
            background: #1C2541 !important;
            border-color: #2D3748 !important;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.5) !important;
        }
        html.dark-theme .ctc-staff-login-icon,
        body.dark-theme .ctc-staff-login-icon,
        html.dark .ctc-staff-login-icon,
        body.dark .ctc-staff-login-icon,
        body[data-theme="dark"] .ctc-staff-login-icon {
            background: rgba(20, 184, 166, 0.2) !important;
            color: #2DD4BF !important;
        }
        html.dark-theme .ctc-staff-login-title,
        body.dark-theme .ctc-staff-login-title,
        html.dark .ctc-staff-login-title,
        body.dark .ctc-staff-login-title,
        body[data-theme="dark"] .ctc-staff-login-title {
            color: #F8FAFC !important;
        }
        html.dark-theme .ctc-staff-login-desc,
        body.dark-theme .ctc-staff-login-desc,
        html.dark .ctc-staff-login-desc,
        body.dark .ctc-staff-login-desc,
        body[data-theme="dark"] .ctc-staff-login-desc {
            color: #94A3B8 !important;
        }
        html.dark-theme .ctc-staff-login-label,
        body.dark-theme .ctc-staff-login-label,
        html.dark .ctc-staff-login-label,
        body.dark .ctc-staff-login-label,
        body[data-theme="dark"] .ctc-staff-login-label {
            color: #F8FAFC !important;
        }
        html.dark-theme .ctc-staff-login-input,
        body.dark-theme .ctc-staff-login-input,
        html.dark .ctc-staff-login-input,
        body.dark .ctc-staff-login-input,
        body[data-theme="dark"] .ctc-staff-login-input {
            background-color: #0F172A !important;
            color: #F8FAFC !important;
            border-color: #334155 !important;
        }
        html.dark-theme .ctc-staff-login-input:focus,
        body.dark-theme .ctc-staff-login-input:focus,
        html.dark .ctc-staff-login-input:focus,
        body.dark .ctc-staff-login-input:focus,
        body[data-theme="dark"] .ctc-staff-login-input:focus {
            border-color: #2DD4BF !important;
            box-shadow: 0 0 0 3px rgba(45, 212, 191, 0.2) !important;
        }
        </style>
        <div class="careyou-staff-portal-wrapper caretochina-staff-portal-wrapper ctc-staff-login-wrapper">
            <div class="glass-card ctc-staff-login-card">
                <div style="text-align:center; margin-bottom:24px;">
                    <div class="ctc-staff-login-icon">
                        <i class="fa-solid fa-user-doctor"></i>
                    </div>
                    <h2 class="ctc-staff-login-title"><?php esc_html_e('Medical Staff Portal Login', 'caretochina-medical'); ?></h2>
                    <p class="ctc-staff-login-desc"><?php esc_html_e('Strictly for authorized Care Coordinators & Technical Staff.', 'caretochina-medical'); ?></p>
                </div>

                <form id="staff-portal-login-form">
                    <div style="margin-bottom:20px;">
                        <label class="ctc-staff-login-label"><?php esc_html_e('Staff Username or Email *', 'caretochina-medical'); ?></label>
                        <input type="text" name="username" class="form-input ctc-staff-login-input" required>
                    </div>
                    <div style="margin-bottom:24px;">
                        <label class="ctc-staff-login-label"><?php esc_html_e('Staff Password *', 'caretochina-medical'); ?></label>
                        <input type="password" name="password" class="form-input ctc-staff-login-input" required>
                    </div>
                    <button type="submit" id="staff_login_btn" class="btn btn-primary btn-full btn-lg ctc-staff-login-btn">
                        <i class="fa-solid fa-lock"></i> <?php esc_html_e('Access Medical Control Desk', 'caretochina-medical'); ?>
                    </button>
                </form>
                <div id="staff-login-response" style="display:none; margin-top:20px; text-align:center; font-weight:700;"></div>
            </div>
        </div>

        <script>
        (function() {
            function initStaffLoginForm() {
                var form = document.getElementById('staff-portal-login-form');
                if (!form || form.dataset.bound === 'true') return;
                form.dataset.bound = 'true';

                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var btn = document.getElementById('staff_login_btn');
                    var res = document.getElementById('staff-login-response');
                    var apiObj = (typeof caretochina_staff_obj !== 'undefined') ? caretochina_staff_obj : ((typeof careyou_staff_obj !== 'undefined') ? careyou_staff_obj : { ajax_url: '/wp-admin/admin-ajax.php', nonce: '' });

                    if (btn) {
                        btn.disabled = true;
                        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <?php echo esc_js(__('Verifying Credentials...', 'caretochina-medical')); ?>';
                    }

                    var usernameInput = form.querySelector('input[name="username"]');
                    var passwordInput = form.querySelector('input[name="password"]');

                    var formData = new URLSearchParams();
                    formData.append('action', 'caretochina_staff_login');
                    formData.append('username', usernameInput ? usernameInput.value : '');
                    formData.append('password', passwordInput ? passwordInput.value : '');
                    formData.append('nonce', apiObj.nonce || '');

                    fetch(apiObj.ajax_url, {
                        method: 'POST',
                        body: formData,
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(response) {
                        if (res) {
                            res.style.display = 'block';
                            if (response && response.success) {
                                res.style.color = '#10B981';
                                res.textContent = response.data ? response.data.message : 'Login successful!';
                                setTimeout(function() { window.location.reload(); }, 1000);
                            } else {
                                res.style.color = '#EF4444';
                                res.textContent = (response && response.data && response.data.message) ? response.data.message : 'Invalid credentials.';
                                if (btn) {
                                    btn.disabled = false;
                                    btn.innerHTML = '<i class="fa-solid fa-lock"></i> <?php echo esc_js(__('Access Medical Control Desk', 'caretochina-medical')); ?>';
                                }
                            }
                        }
                    })
                    .catch(function(err) {
                        if (res) {
                            res.style.display = 'block';
                            res.style.color = '#EF4444';
                            res.textContent = 'Connection error. Please try again.';
                        }
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fa-solid fa-lock"></i> <?php echo esc_js(__('Access Medical Control Desk', 'caretochina-medical')); ?>';
                        }
                    });
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initStaffLoginForm);
            } else {
                initStaffLoginForm();
            }
        })();
        </script>
        <?php
    }

    private function render_portal_ui() {
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';
        $table_messages = $wpdb->prefix . 'caretochina_messages';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $pending_bookings_count = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}caretochina_bookings WHERE status = %s", 'pending')));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $unread_messages_count = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}caretochina_messages WHERE sender_type = %s AND is_read = %d",
            'patient',
            0
        )));

        $chat_conversations = $this->get_chat_conversations(30);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $all_bookings = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}caretochina_bookings ORDER BY id DESC LIMIT %d", 10));
        $bookings = !empty($all_bookings) ? $all_bookings : [];

        if (empty($bookings)) {
            $bookings = [
                (object)[
                    'id' => 1,
                    'patient_id' => 0,
                    'is_guest' => 1,
                    'booking_code' => 'CTC-89420',
                    'full_name' => 'Sarah Jenkins',
                    'age' => 28,
                    'gender' => 'Female',
                    'email' => 'sarah@example.com',
                    'phone' => '+1 (800) 555-0199',
                    'whatsapp' => '+1 (800) 555-0199',
                    'wechat' => 'sarahj88',
                    'messenger' => 'sarah.jenkins.fb',
                    'linkedin' => 'linkedin.com/in/sarahj',
                    'hospital_name' => 'Charité Universitätsmedizin',
                    'specialty' => 'Cardiac Care (Munich, Germany)',
                    'treatment_timing' => 'As soon as possible',
                    'quote_details' => 'Patient uploaded Echocardiogram scans.',
                    'country' => 'United States',
                    'status' => 'pending',
                    'timeline_stage' => 3,
                    'invoice_status' => 'Deposit Paid ($2,000)'
                ]
            ];
        }

        $active_b = !empty($chat_conversations) ? $chat_conversations[0] : (!empty($bookings) ? $bookings[0] : null);
        $store_currency = class_exists('CareToChina_Packages') ? CareToChina_Packages::get_store_currency() : get_option('ctc_payment_currency', 'USD');
        $currency_symbol = class_exists('CareToChina_Packages') ? CareToChina_Packages::get_currency_symbol($store_currency) : '$';
        ?>
        <div class="careyou-staff-portal-wrapper caretochina-staff-portal-wrapper" data-booking-id="<?php echo esc_attr($active_b ? $active_b->id : 0); ?>" data-booking-count="<?php echo count($bookings); ?>">
            <!-- STAFF BANNER HEADER -->
            <div class="staff-header-banner">
                <div style="display:flex; align-items:center; gap:18px;">
                    <div style="width:58px; height:58px; border-radius:50%; background:#CCFBF1; color:#0F766E; display:flex; align-items:center; justify-content:center; font-size:26px; border:2.5px solid #FFF; flex-shrink:0;">
                        <i class="fa-solid fa-user-nurse"></i>
                    </div>
                    <div>
                        <h2><?php esc_html_e('Medical Coordinator Control Desk', 'caretochina-medical'); ?></h2>
                        <p><i class="fa-solid fa-circle text-success" style="color:#10B981;"></i> <?php esc_html_e('Active Duty • Selected Case:', 'caretochina-medical'); ?> <strong id="header-active-case-code">#<?php echo esc_html($active_b ? $active_b->booking_code : '---'); ?></strong> (<span id="header-active-patient-name"><?php echo esc_html($active_b ? $active_b->full_name : __('No Active Case', 'caretochina-medical')); ?></span>)</p>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:16px;">
                    <!-- NOTIFICATION BELL & DROPDOWN -->
                    <div id="staff-header-bell" style="position:relative; width:42px; height:42px; border-radius:50%; background:rgba(255,255,255,0.15); display:flex; align-items:center; justify-content:center; font-size:18px; color:#FFF; cursor:pointer; transition:all 0.2s;" onclick="appStaff.handleNotificationClick(event)" title="<?php esc_attr_e('Notifications', 'caretochina-medical'); ?>">
                        <i class="fa-solid fa-bell"></i>
                        <span id="staff-header-bell-badge" style="position:absolute; top:-4px; right:-4px; background:#EF4444; color:#FFF; border-radius:50%; width:18px; height:18px; display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:700; border:2px solid #0F766E; <?php echo (($pending_bookings_count + $unread_messages_count) === 0) ? 'display:none;' : ''; ?>"><?php echo esc_html($pending_bookings_count + $unread_messages_count); ?></span>
                        
                        <!-- NOTIFICATION DROPDOWN -->
                        <div id="staff-bell-dropdown" style="display:none; position:absolute; top:48px; right:0; width:350px; background:#FFFFFF; border-radius:14px; box-shadow:0 12px 30px rgba(0,0,0,0.2); border:1px solid #E2E8F0; z-index:99999; color:#0F172A; text-align:left; font-family:'Inter', sans-serif; cursor:default;" onclick="event.stopPropagation();">
                            <div style="padding:14px 18px; border-bottom:1px solid #F1F5F9; font-weight:800; font-size:14px; color:#0F766E; display:flex; justify-content:space-between; align-items:center; font-family:'Manrope', sans-serif;">
                                <span><i class="fa-solid fa-bell" style="margin-right:6px;"></i> <?php esc_html_e('Notifications', 'caretochina-medical'); ?></span>
                                <span id="staff-bell-unread-tag" style="font-size:11px; background:#FFE4E6; color:#E11D48; padding:2px 8px; border-radius:10px; font-weight:700; <?php echo (($pending_bookings_count + $unread_messages_count) === 0) ? 'display:none;' : ''; ?>"><?php echo esc_html($pending_bookings_count + $unread_messages_count); ?> <?php esc_html_e('New', 'caretochina-medical'); ?></span>
                            </div>
                            <div id="staff-bell-dropdown-list" style="max-height:300px; overflow-y:auto; font-size:12px;">
                                <?php echo wp_kses_post($this->generate_notifications_dropdown_html()); ?>
                            </div>
                            <div style="padding:10px 16px; border-top:1px solid #F1F5F9; text-align:center; background:#F8FAFC; border-radius:0 0 14px 14px;">
                                <a href="javascript:void(0)" onclick="appStaff.switchTab(jQuery('.staff-tab[onclick*=\'bookings\']')[0], 'bookings'); jQuery('#staff-bell-dropdown').hide();" style="color:#0F766E; font-size:12px; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
                                    <?php esc_html_e('View All Bookings & Approvals', 'caretochina-medical'); ?> &rarr;
                                </a>
                            </div>
                        </div>
                    </div>
                    <span class="badge-pill" style="background:rgba(255,255,255,0.2); color:#FFFFFF; border:1px solid rgba(255,255,255,0.3); font-size:13px; font-weight:700; padding:8px 18px;"><?php esc_html_e('Staff Duty: ACTIVE', 'caretochina-medical'); ?></span>
                </div>
            </div>

            <!-- MAIN STAFF GRID -->
            <div class="staff-container">
                <!-- SIDEBAR NAVIGATION -->
                <div class="staff-sidebar">
                    <button type="button" class="staff-sidebar-toggle-btn" onclick="appStaff.toggleSidebar()"><i class="fa-solid fa-angles-left"></i></button>
                    <button class="staff-tab active" onclick="appStaff.switchTab(this, 'bookings')">
                        <i class="fa-solid fa-calendar-check"></i> 
                        <span><?php esc_html_e('Bookings & Approvals', 'caretochina-medical'); ?></span>
                        <span id="staff-bookings-badge" class="staff-notification-badge" style="background:#EF4444; color:#FFF; border-radius:50%; padding:2px 6px; font-size:10px; font-weight:700; margin-left:6px; <?php echo ($pending_bookings_count === 0) ? 'display:none;' : ''; ?>"><?php echo esc_html($pending_bookings_count); ?></span>
                    </button>
                    <button class="staff-tab" onclick="appStaff.switchTab(this, 'chat')">
                        <i class="fa-solid fa-comments"></i> 
                        <span><?php esc_html_e('Patient Live Chat', 'caretochina-medical'); ?></span>
                        <span id="staff-chat-badge" class="staff-notification-badge" style="background:#EF4444; color:#FFF; border-radius:50%; padding:2px 6px; font-size:10px; font-weight:700; margin-left:6px; <?php echo ($unread_messages_count === 0) ? 'display:none;' : ''; ?>"><?php echo esc_html($unread_messages_count); ?></span>
                    </button>
                    <button class="staff-tab" onclick="appStaff.switchTab(this, 'timeline')"><i class="fa-solid fa-timeline"></i> <span><?php esc_html_e('Treatment Timeline', 'caretochina-medical'); ?></span></button>
                    <button class="staff-tab" onclick="appStaff.switchTab(this, 'invoices')"><i class="fa-solid fa-file-invoice-dollar"></i> <span><?php esc_html_e('Invoices & Payments', 'caretochina-medical'); ?></span></button>
                    <?php if (current_user_can('manage_options')) : ?>
                        <button class="staff-tab" onclick="appStaff.switchTab(this, 'admin-settings')"><i class="fa-solid fa-user-gear"></i> <span><?php esc_html_e('Staff Management (Admin)', 'caretochina-medical'); ?></span></button>
                    <?php endif; ?>
                </div>

                <!-- STAFF PANELS -->
                <div class="staff-content">
                    
                    <!-- TAB 1: BOOKING MANAGEMENT -->
                    <div class="staff-panel active" id="staff-panel-bookings">
                        <div class="glass-card">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; gap:15px; flex-wrap:wrap;">
                                <h3 style="margin:0; font-family:'Manrope', sans-serif; color:#0F172A; font-size:22px; font-weight:700;"><?php esc_html_e('Patient Booking Approvals & Status', 'caretochina-medical'); ?></h3>
                                <div style="display:flex; align-items:center; gap:8px; background:#FFF; border:1px solid #cbd5e1; border-radius:8px; padding:6px 12px; width:300px; max-width:100%; box-sizing:border-box;">
                                    <i class="fa-solid fa-magnifying-glass" style="color:#64748B; font-size:14px;"></i>
                                    <input type="text" id="staff-booking-search" placeholder="<?php esc_html_e('Search patients...', 'caretochina-medical'); ?>" style="border:none; outline:none; font-size:13px; width:100%; color:#0F172A; font-family:'Inter',sans-serif;" onkeyup="appStaff.searchBookings(this.value)">
                                </div>
                            </div>
                            
                            <div style="overflow-x:auto; width:100%;">
                                <table style="width:100%; border-collapse:collapse; text-align:left; border:1px solid #E2E8F0; border-radius:14px; overflow:hidden;">
                                    <thead>
                                        <tr style="background:#F8FAFC; border-bottom:2px solid #E2E8F0; color:#64748B; font-size:12px; font-weight:700; text-transform:uppercase;">
                                            <th style="padding:14px;"><?php esc_html_e('Case Code', 'caretochina-medical'); ?></th>
                                            <th style="padding:14px;"><?php esc_html_e('Patient Profile', 'caretochina-medical'); ?></th>
                                            <th style="padding:14px;"><?php esc_html_e('Contact & Socials', 'caretochina-medical'); ?></th>
                                            <th style="padding:14px;"><?php esc_html_e('Quote & Request details', 'caretochina-medical'); ?></th>
                                            <th style="padding:14px;"><?php esc_html_e('Current Status', 'caretochina-medical'); ?></th>
                                            <th style="padding:14px; width:180px; text-align:right;"><?php esc_html_e('Staff Actions', 'caretochina-medical'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="staff-bookings-tbody">
                                        <?php echo wp_kses_post($this->generate_bookings_table_rows($bookings)); ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div id="staff-bookings-pagination" style="margin-top:20px;">
                                <!-- Rendered dynamically by AJAX -->
                            </div>
                        </div>
                    </div>

                    <!-- VIEW DETAILS POPUP MODAL -->
                    <div id="staff-view-booking-modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(15, 23, 42, 0.65); backdrop-filter:blur(6px); z-index:100000; align-items:center; justify-content:center;">
                        <div style="background:#FFFFFF; border-radius:24px; width:620px; max-width:90%; padding:32px; box-shadow:0 20px 40px rgba(0,0,0,0.3); font-family:'Inter', sans-serif; max-height:90vh; overflow-y:auto; box-sizing:border-box;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #E2E8F0; padding-bottom:12px;">
                                <h2 style="margin:0; font-family:'Manrope'; color:#0F172A; font-size:22px;"><i class="fa-solid fa-eye" style="color:#3B82F6;"></i> <?php esc_html_e('Case Details', 'caretochina-medical'); ?> <span id="view-modal-code" style="color:#0F766E;"></span></h2>
                                <button type="button" onclick="jQuery('#staff-view-booking-modal').hide()" style="background:none; border:none; font-size:20px; cursor:pointer; color:#64748B;">&times;</button>
                            </div>
                            <div id="view-modal-content" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; font-size:13px; color:#334155; line-height:1.5;">
                                <!-- Populated dynamically by JS -->
                            </div>
                            <div style="display:flex; justify-content:flex-end; margin-top:24px; border-top:1px solid #E2E8F0; padding-top:16px;">
                                <button type="button" class="button button-secondary" onclick="jQuery('#staff-view-booking-modal').hide()"><?php esc_html_e('Close Window', 'caretochina-medical'); ?></button>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: LIVE PATIENT CHAT (SIDEBAR LAYOUT) -->
                    <div class="staff-panel" id="staff-panel-chat">
                        <div class="glass-card" style="padding:0; overflow:hidden; width:100%; box-sizing:border-box;">
                            <div class="staff-chat-layout" style="display:flex; height:520px; background:#FFF; font-family:'Inter', sans-serif; width:100%; box-sizing:border-box; overflow:hidden;">
                                <!-- Left Sidebar: Patients List -->
                                <div class="staff-chat-sidebar" style="width:230px; min-width:180px; max-width:240px; border-right:1px solid #E2E8F0; display:flex; flex-direction:column; background:#F8FAFC; flex-shrink:0; box-sizing:border-box;">
                                    <div style="padding:16px; border-bottom:1px solid #E2E8F0; font-weight:700; font-family:'Manrope'; color:#0F172A;"><?php esc_html_e('Patient Chats', 'caretochina-medical'); ?></div>
                                    <div class="staff-chat-patient-list" style="flex:1; overflow-y:auto;">
                                        <?php echo wp_kses_post($this->generate_chat_patient_list_html($chat_conversations, $active_b ? $active_b->id : 0)); ?>
                                    </div>
                                </div>
                                <!-- Right Area: Messaging -->
                                <div class="staff-chat-thread" style="flex:1; display:flex; flex-direction:column; min-width:0; width:100%; box-sizing:border-box; overflow:hidden;">
                                    <div class="staff-chat-thread-header" style="padding:16px; border-bottom:1px solid #E2E8F0; background:#FFF; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; box-sizing:border-box;">
                                        <div style="display:flex; align-items:center; gap:8px; min-width:0; flex-wrap:wrap;">
                                            <strong id="chat-active-patient-name" style="font-family:'Manrope'; color:#0F172A;"><?php echo esc_html($active_b ? $active_b->full_name : __('No Approved Patient Selected', 'caretochina-medical')); ?></strong>
                                            <span id="chat-active-patient-code" style="font-size:12px; color:#64748B;"><?php echo $active_b ? '#' . esc_html($active_b->booking_code) : ''; ?></span>
                                            <?php $is_active_b_guest = $active_b ? (intval($active_b->patient_id ?? 0) === 0 || intval($active_b->is_guest ?? 0) === 1) : false; ?>
                                            <span id="chat-active-guest-badge" style="background:#FEF3C7; color:#92400E; font-size:11px; font-weight:700; padding:2px 8px; border-radius:6px; <?php echo (!$is_active_b_guest || !$active_b) ? 'display:none;' : ''; ?>"><i class="fa-solid fa-user-clock"></i> <?php esc_html_e('Guest', 'caretochina-medical'); ?></span>
                                        </div>
                                        <span style="font-size:12px; color:#10B981; font-weight:600;"><i class="fa-solid fa-circle" style="font-size:8px;"></i> <?php esc_html_e('Live', 'caretochina-medical'); ?></span>
                                    </div>
                                    <div id="staff-chat-box" class="dash-chat-box" style="flex:1; overflow-y:auto; overflow-x:hidden; padding:16px 20px; background:#F8FAFC; width:100%; box-sizing:border-box;">
                                        <?php if (empty($active_b) || empty($chat_conversations)) : ?>
                                            <div style="padding:50px 20px; text-align:center; color:#94A3B8;">
                                                <i class="fa-solid fa-user-clock" style="font-size:36px; margin-bottom:12px; display:block; color:#CBD5E1;"></i>
                                                <strong style="font-size:15px; color:#475569;"><?php esc_html_e('No Approved Patient Selected', 'caretochina-medical'); ?></strong>
                                                <p style="margin:6px 0 0 0; font-size:13px;"><?php esc_html_e('Approve a pending booking from the Bookings & Approvals tab to start live consultation.', 'caretochina-medical'); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div id="staff-chat-typing-indicator" style="padding:4px 20px; font-size:12px; color:#64748B; font-style:italic; display:none; background:#F8FAFC;"></div>
                                    
                                    <div id="staff_attachment_preview" style="display:none; padding:6px 16px; background:#F1F5F9; border-top:1px solid #E2E8F0; font-size:12px; align-items:center; justify-content:space-between; gap:8px;">
                                        <span id="staff_attachment_name" style="color:#0F172A; font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"></span>
                                        <button type="button" onclick="appStaff.clearAttachment()" style="background:none; border:none; color:#EF4444; cursor:pointer; font-weight:800; font-size:14px; line-height:1;">&times;</button>
                                    </div>

                                    <form id="staff-chat-form" enctype="multipart/form-data" style="padding:12px 16px; border-top:1px solid #E2E8F0; background:#FFF; display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin:0; width:100%; box-sizing:border-box; min-width:0; <?php echo empty($active_b) ? 'display:none;' : ''; ?>">
                                        <input type="hidden" name="booking_id" id="staff_chat_booking_id" value="<?php echo esc_attr($active_b ? $active_b->id : 0); ?>">
                                        
                                        <label for="staff_chat_file_input" class="ctc-chat-attach-btn" title="<?php esc_attr_e('Attach Image or PDF (Max 2MB)', 'caretochina-medical'); ?>" style="display:inline-flex; align-items:center; justify-content:center; width:38px; height:38px; border-radius:10px; background:#F1F5F9; border:1px solid #CBD5E1; color:#475569; cursor:pointer; font-size:16px; transition:all 0.2s; flex-shrink:0;">
                                            <i class="fa-solid fa-paperclip"></i>
                                        </label>
                                        <input type="file" id="staff_chat_file_input" name="attachment" accept="image/jpeg,image/png,image/webp,image/gif,application/pdf" style="display:none;" onchange="appStaff.handleFileSelected(this)">

                                        <input type="text" name="message" id="staff_chat_input" class="form-input" placeholder="<?php esc_html_e('Type a response to patient...', 'caretochina-medical'); ?>" autocomplete="off" style="flex:1 1 140px; min-width:110px; padding:10px 14px; border-radius:10px; border:1px solid #cbd5e1; font-size:14px; box-sizing:border-box;">
                                        
                                        <button type="button" id="staff-chat-req-pay-btn" onclick="window.ctcOpenStaffPaymentReqModal()" class="ctc-solid-btn" style="background:#0F766E; color:#FFF; padding:10px 14px; font-size:13px; font-weight:700; border-radius:10px; cursor:pointer; display:flex; align-items:center; gap:6px; white-space:nowrap; flex-shrink:0; <?php echo $is_active_b_guest ? 'opacity:0.45; cursor:not-allowed;' : ''; ?>" title="<?php echo $is_active_b_guest ? esc_attr__('Payment requests can only be sent to registered patients. Ask guest to register first.', 'caretochina-medical') : esc_attr__('Create & Send Payment Request', 'caretochina-medical'); ?>">
                                            <i class="fa-solid fa-file-invoice-dollar"></i> <?php esc_html_e('Request Payment', 'caretochina-medical'); ?>
                                        </button>
                                        <button type="submit" class="ctc-solid-btn btn-teal-primary" style="padding:10px 20px; font-size:14px; border-radius:10px; cursor:pointer; flex-shrink:0; white-space:nowrap;"><i class="fa-solid fa-paper-plane"></i> <?php esc_html_e('Send', 'caretochina-medical'); ?></button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 3: TREATMENT TIMELINE UPDATE -->
                    <div class="staff-panel" id="staff-panel-timeline">
                        <div class="glass-card">
                            <h3 style="margin:0 0 12px 0; font-family:'Manrope', sans-serif; color:#0F172A; font-size:22px; font-weight:700;"><?php esc_html_e('Update Patient Roadmap Stage', 'caretochina-medical'); ?></h3>
                            <p style="color:#64748B; margin-bottom:24px;"><?php esc_html_e('Advance patient treatment milestones in real-time:', 'caretochina-medical'); ?></p>

                            <form id="staff-timeline-form">
                                <input type="hidden" name="booking_id" id="staff_timeline_booking_id" value="<?php echo esc_attr($active_b->id); ?>">
                                <div style="display:flex; flex-direction:column; gap:12px; margin-bottom:24px;">
                                    <label style="font-weight:600; font-size:15px; color:#0F172A;"><?php esc_html_e('Select Active Treatment Stage:', 'caretochina-medical'); ?></label>
                                    <select name="timeline_stage" id="staff_stage_select" style="font-size:15px; padding:14px; border-radius:12px; border:1px solid #cbd5e1; background:#FFF; color:#0F172A;">
                                        <option value="1" <?php selected($active_b->timeline_stage, 1); ?>><?php esc_html_e('Stage 1: Medical Assessment & Consultation', 'caretochina-medical'); ?></option>
                                        <option value="2" <?php selected($active_b->timeline_stage, 2); ?>><?php esc_html_e('Stage 2: Hospital Guarantee & Embassy Visa Issued', 'caretochina-medical'); ?></option>
                                        <option value="3" <?php selected($active_b->timeline_stage, 3); ?>><?php esc_html_e('Stage 3: Airport Arrival & Chauffeur Transfer (ACTIVE)', 'caretochina-medical'); ?></option>
                                        <option value="4" <?php selected($active_b->timeline_stage, 4); ?>><?php esc_html_e('Stage 4: Surgical Procedure at Partner Hospital', 'caretochina-medical'); ?></option>
                                        <option value="5" <?php selected($active_b->timeline_stage, 5); ?>><?php esc_html_e('Stage 5: Post-Op Recovery & Lifetime Telehealth', 'caretochina-medical'); ?></option>
                                    </select>
                                </div>
                                <button type="submit" class="ctc-solid-btn btn-teal-primary" style="padding:14px 28px; border-radius:999px; cursor:pointer;"><i class="fa-solid fa-sync"></i> <?php esc_html_e('Update Timeline Stage', 'caretochina-medical'); ?></button>
                            </form>
                        </div>
                    </div>

                    <!-- TAB 4: INVOICE & PAYMENT MANAGEMENT -->
                    <div class="staff-panel" id="staff-panel-invoices">
                        <div class="glass-card">
                            <h3 style="margin:0 0 24px 0; font-family:'Manrope', sans-serif; color:#0F172A; font-size:22px; font-weight:700;"><?php esc_html_e('Invoice & Payment Approval', 'caretochina-medical'); ?></h3>
                            
                            <div style="background:#F8FAFC; border:1px solid #E2E8F0; padding:24px; border-radius:16px; margin-bottom:24px;">
                                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
                                    <div>
                                        <h4 style="margin:0 0 4px 0; font-size:18px; color:#0F172A;"><?php esc_html_e('All-Inclusive Procedure Package', 'caretochina-medical'); ?></h4>
                                        <p style="color:#64748B; margin:0; font-size:14px;"><?php esc_html_e('Patient:', 'caretochina-medical'); ?> <span id="invoice-active-patient-name"><?php echo esc_html($active_b->full_name); ?></span> (<span id="invoice-active-patient-email"><?php echo esc_html($active_b->email); ?></span>)</p>
                                    </div>
                                    <div style="text-align:right;">
                                        <h3 style="margin:0 0 4px 0; color:#0F766E; font-size:24px; font-weight:800;"><?php esc_html_e('$14,500.00', 'caretochina-medical'); ?></h3>
                                        <span id="staff-invoice-badge" class="badge-pill" style="background:#D1FAE5; color:#065F46; padding:6px 14px; font-weight:700;"><?php echo esc_html($active_b->invoice_status ?? 'Deposit Paid ($2,000)'); ?></span>
                                    </div>
                                </div>
                            </div>

                            <form id="staff-invoice-form">
                                <input type="hidden" name="booking_id" id="staff_invoice_booking_id" value="<?php echo esc_attr($active_b->id); ?>">
                                <div style="display:flex; gap:14px; align-items:center;">
                                    <label style="font-weight:600; font-size:14px; color:#0F172A; white-space:nowrap;"><?php esc_html_e('Set Payment Status:', 'caretochina-medical'); ?></label>
                                    <select name="invoice_status" id="staff_invoice_select" style="flex:1; padding:12px 16px; border-radius:12px; border:1px solid #cbd5e1; background:#FFF; font-size:15px;">
                                        <option value="Deposit Paid ($2,000)"><?php esc_html_e('Deposit Paid ($2,000)', 'caretochina-medical'); ?></option>
                                        <option value="Fully Paid ($14,500.00)"><?php esc_html_e('Fully Paid ($14,500.00)', 'caretochina-medical'); ?></option>
                                        <option value="Pending Deposit"><?php esc_html_e('Pending Deposit', 'caretochina-medical'); ?></option>
                                        <option value="Payment Rejected"><?php esc_html_e('Payment Rejected', 'caretochina-medical'); ?></option>
                                    </select>
                                    <button type="submit" class="ctc-solid-btn btn-teal-primary" style="padding:12px 24px; border-radius:999px; cursor:pointer; white-space:nowrap;"><i class="fa-solid fa-check-double"></i> <?php esc_html_e('Save Status', 'caretochina-medical'); ?></button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- TAB 5: STAFF MANAGEMENT (ADMIN ONLY) -->
                    <?php if (current_user_can('manage_options')) : ?>
                        <div class="staff-panel" id="staff-panel-admin-settings" style="display:none;">
                            <div class="glass-card">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
                                    <h3 style="margin:0; font-family:'Manrope', sans-serif; color:#0F172A; font-size:22px; font-weight:700;"><?php esc_html_e('Medical Staff Accounts Management', 'caretochina-medical'); ?></h3>
                                    <button type="button" onclick="jQuery('#admin-create-staff-modal').css('display', 'flex')" class="ctc-solid-btn btn-teal-primary" style="padding:10px 20px; font-size:14px; border-radius:10px; cursor:pointer;"><i class="fa-solid fa-user-plus"></i> + <?php esc_html_e('Create Staff User', 'caretochina-medical'); ?></button>
                                </div>
                                
                                <div style="overflow-x:auto; width:100%;">
                                    <table style="width:100%; border-collapse:collapse; text-align:left; border:1px solid #E2E8F0; border-radius:14px; overflow:hidden;">
                                        <thead>
                                            <tr style="background:#F8FAFC; border-bottom:2px solid #E2E8F0; color:#64748B; font-size:12px; font-weight:700; text-transform:uppercase;">
                                                <th style="padding:14px;"><?php esc_html_e('Staff Name', 'caretochina-medical'); ?></th>
                                                <th style="padding:14px;"><?php esc_html_e('Username', 'caretochina-medical'); ?></th>
                                                <th style="padding:14px;"><?php esc_html_e('Email Address', 'caretochina-medical'); ?></th>
                                                <th style="padding:14px;"><?php esc_html_e('Role / Capability', 'caretochina-medical'); ?></th>
                                                <th style="padding:14px;"><?php esc_html_e('Joined Date', 'caretochina-medical'); ?></th>
                                                <th style="padding:14px; width:150px; text-align:right;"><?php esc_html_e('Actions', 'caretochina-medical'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="admin-staff-list-tbody">
                                            <?php echo wp_kses_post($this->generate_admin_staff_table_rows()); ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
            <!-- STAFF PAYMENT REQUEST CREATION MODAL -->
            <div id="staff-payment-request-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.65); z-index:99999; align-items:center; justify-content:center; padding:20px; box-sizing:border-box;">
                <div style="background:#FFF; border-radius:20px; max-width:560px; width:100%; padding:28px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); font-family:'Manrope', sans-serif; position:relative; max-height:90vh; overflow-y:auto;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #E2E8F0; padding-bottom:14px;">
                        <h3 style="margin:0; font-size:18px; font-weight:800; color:#0F172A; display:flex; align-items:center; gap:8px;">
                            <i class="fa-solid fa-file-invoice-dollar" style="color:#0F766E;"></i> <?php esc_html_e('Send Payment Request', 'caretochina-medical'); ?>
                        </h3>
                        <button type="button" onclick="jQuery('#staff-payment-request-modal').hide()" style="background:none; border:none; font-size:18px; color:#94A3B8; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
                    </div>

                    <form id="staff-send-payment-request-form">
                        <input type="hidden" name="booking_id" id="req_modal_booking_id" value="<?php echo esc_attr($active_b->id); ?>">

                        <?php
                        $store_currency  = class_exists('CareToChina_Packages') ? CareToChina_Packages::get_store_currency() : 'USD';
                        $currency_symbol = class_exists('CareToChina_Packages') ? CareToChina_Packages::get_currency_symbol($store_currency) : '$';
                        ?>
                        <div style="margin-bottom:18px;">
                            <label style="display:block; font-size:13px; font-weight:700; color:#334155; margin-bottom:8px;"><?php esc_html_e('Choose Pricing Source (Select exactly one):', 'caretochina-medical'); ?></label>
                            <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:8px;">
                                <label style="border:1.5px solid #CBD5E1; border-radius:10px; padding:10px 8px; text-align:center; font-size:11px; font-weight:700; cursor:pointer; color:#475569;" class="ctc-pricing-opt-label active">
                                    <input type="radio" name="pricing_type" value="package" checked style="margin-bottom:4px;" onchange="window.ctcSwitchPricingType('package')"><br>
                                    <?php esc_html_e('Service Package', 'caretochina-medical'); ?>
                                </label>
                                <label style="border:1.5px solid #CBD5E1; border-radius:10px; padding:10px 8px; text-align:center; font-size:11px; font-weight:700; cursor:pointer; color:#475569;" class="ctc-pricing-opt-label">
                                    <input type="radio" name="pricing_type" value="custom_amount" style="margin-bottom:4px;" onchange="window.ctcSwitchPricingType('custom_amount')"><br>
                                    <?php esc_html_e('Custom Fee', 'caretochina-medical'); ?>
                                </label>
                                <label style="border:1.5px solid #CBD5E1; border-radius:10px; padding:10px 8px; text-align:center; font-size:11px; font-weight:700; cursor:pointer; color:#475569;" class="ctc-pricing-opt-label">
                                    <input type="radio" name="pricing_type" value="custom_treatment" style="margin-bottom:4px;" onchange="window.ctcSwitchPricingType('custom_treatment')"><br>
                                    <?php esc_html_e('One-Off Treatment', 'caretochina-medical'); ?>
                                </label>
                            </div>
                        </div>

                        <!-- SECTION 1: SERVICE PACKAGE -->
                        <div id="pricing-sec-package" class="pricing-sec-box">
                            <div style="margin-bottom:14px;">
                                <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;"><?php esc_html_e('Select Service Package *', 'caretochina-medical'); ?></label>
                                <select name="package_id" id="req_package_select" style="width:100%; padding:10px; border-radius:8px; border:1px solid #CBD5E1; font-size:13px;" onchange="if(window.ctcOnStaffPackageChange) window.ctcOnStaffPackageChange(this)">
                                    <option value="0"><?php esc_html_e('-- Select Service Package --', 'caretochina-medical'); ?></option>
                                    <?php
                                    $packages = class_exists('CareToChina_Packages') ? CareToChina_Packages::instance()->get_all_packages() : [];
                                    if (!empty($packages)) {
                                        foreach ($packages as $pkg) {
                                            $pkg_label = $pkg->name . ($pkg->price > 0 ? ' (' . $pkg->price_formatted . ')' : ' [' . __('Price not set', 'caretochina-medical') . ']');
                                            echo '<option value="' . esc_attr($pkg->id) . '" data-price="' . esc_attr($pkg->price) . '" data-title="' . esc_attr($pkg->name) . '">' . esc_html($pkg_label) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div style="margin-bottom:14px;">
                                <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;"><?php esc_html_e('Custom Package Name Override (Optional)', 'caretochina-medical'); ?></label>
                                <input type="text" name="plan_name" id="req_plan_name_input" class="form-input" placeholder="e.g. Plan A: Ultimate Exclusive Package" style="width:100%; padding:10px; border-radius:8px; border:1px solid #CBD5E1; font-size:13px;">
                            </div>
                            <div style="margin-bottom:14px;">
                                <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;"><?php
                                /* translators: 1: Currency symbol, 2: Currency code */
                                printf(esc_html__('Locked Amount (%1$s %2$s) *', 'caretochina-medical'), esc_html($currency_symbol), esc_html($store_currency));
                                ?></label>
                                <input type="number" step="0.01" name="plan_custom_amount" id="req_plan_amount" class="form-input" placeholder="0.00" style="width:100%; padding:10px; border-radius:8px; border:1px solid #CBD5E1; font-size:13px;">
                            </div>
                        </div>

                        <!-- SECTION 2: CUSTOM AMOUNT -->
                        <div id="pricing-sec-custom_amount" class="pricing-sec-box" style="display:none;">
                            <div style="margin-bottom:14px;">
                                <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;"><?php esc_html_e('Short Description / Fee Label *', 'caretochina-medical'); ?></label>
                                <input type="text" name="custom_amount_title" class="form-input" placeholder="e.g. Specialist Consultation & Second Opinion" style="width:100%; padding:10px; border-radius:8px; border:1px solid #CBD5E1; font-size:13px;">
                            </div>
                            <div style="margin-bottom:14px;">
                                <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;"><?php
                                /* translators: 1: Currency symbol, 2: Currency code */
                                printf(esc_html__('Fee Amount (%1$s %2$s) *', 'caretochina-medical'), esc_html($currency_symbol), esc_html($store_currency));
                                ?></label>
                                <input type="number" step="0.01" name="custom_fee_amount" class="form-input" placeholder="e.g. 150.00" style="width:100%; padding:10px; border-radius:8px; border:1px solid #CBD5E1; font-size:13px;">
                            </div>
                        </div>

                        <!-- SECTION 3: CUSTOM TREATMENT -->
                        <div id="pricing-sec-custom_treatment" class="pricing-sec-box" style="display:none;">
                            <div style="margin-bottom:14px;">
                                <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;"><?php esc_html_e('Custom Treatment Title *', 'caretochina-medical'); ?></label>
                                <input type="text" name="custom_treatment_title" class="form-input" placeholder="e.g. Comprehensive Immunotherapy Protocol" style="width:100%; padding:10px; border-radius:8px; border:1px solid #CBD5E1; font-size:13px;">
                            </div>
                            <div style="margin-bottom:14px;">
                                <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;"><?php esc_html_e('Detailed Treatment Overview (Rich content, sanitized) *', 'caretochina-medical'); ?></label>
                                <textarea name="custom_treatment_content" rows="3" class="form-input" placeholder="Include treatment scope, hospital inclusion, concierge services..." style="width:100%; padding:10px; border-radius:8px; border:1px solid #CBD5E1; font-size:13px;"></textarea>
                            </div>
                            <div style="margin-bottom:14px;">
                                <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;"><?php
                                /* translators: 1: Currency symbol, 2: Currency code */
                                printf(esc_html__('Total Package Price (%1$s %2$s) *', 'caretochina-medical'), esc_html($currency_symbol), esc_html($store_currency));
                                ?></label>
                                <input type="number" step="0.01" name="custom_treatment_amount" class="form-input" placeholder="e.g. 5000.00" style="width:100%; padding:10px; border-radius:8px; border:1px solid #CBD5E1; font-size:13px;">
                            </div>
                        </div>

                        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                            <button type="button" onclick="jQuery('#staff-payment-request-modal').hide()" class="ctc-solid-btn" style="background:#F1F5F9; color:#475569; padding:10px 18px; border-radius:10px; border:none; cursor:pointer; font-weight:600; font-size:13px;"><?php esc_html_e('Cancel', 'caretochina-medical'); ?></button>
                            <button type="submit" id="btn-submit-payment-req" class="ctc-solid-btn btn-teal-primary" style="padding:10px 22px; border-radius:10px; cursor:pointer; font-weight:700; font-size:13px;">
                                <i class="fa-solid fa-paper-plane"></i> <?php esc_html_e('Send Request to Patient', 'caretochina-medical'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- TOAST ALERT NOTIFICATION CONTAINER -->
            <div id="ctc-staff-toast-container" style="position:fixed; bottom:20px; right:20px; z-index:999999; display:flex; flex-direction:column; gap:10px;"></div>
        </div>
        <?php
    }

    public function generate_bookings_table_rows($bookings) {
        $html = '';
        foreach ($bookings as $b) {
            $status_style = 'background:#E2E8F0; color:#0F172A;';
            if ($b->status === 'confirmed') {
                $status_style = 'background:#D1FAE5; color:#065F46;';
            } elseif ($b->status === 'waiting') {
                $status_style = 'background:#FEF3C7; color:#B45309;';
            } elseif ($b->status === 'cancelled') {
                $status_style = 'background:#FEE2E2; color:#991B1B;';
            }

            $socials = [];
            if ($b->whatsapp) $socials[] = '<strong>WA:</strong> ' . esc_html($b->whatsapp);
            if ($b->wechat) $socials[] = '<strong>WC:</strong> ' . esc_html($b->wechat);
            if ($b->messenger) $socials[] = '<strong>MS:</strong> ' . esc_html($b->messenger);
            if ($b->linkedin) $socials[] = '<strong>LN:</strong> ' . esc_html($b->linkedin);
            $socials_str = implode('<br>', $socials);
            if (empty($socials_str)) $socials_str = '<span style="color:#94A3B8;">None</span>';

            $age_gender = [];
            if ($b->age) $age_gender[] = esc_html($b->age) . ' yrs';
            if ($b->gender) $age_gender[] = esc_html($b->gender);
            $age_gender_str = implode(' / ', $age_gender);

            $delete_btn = '';
            if (current_user_can('manage_options')) {
                $delete_btn = sprintf(
                    '<button type="button" class="btn-action-delete" onclick="appStaff.deletePatientData(%d)" style="background:#EF4444; color:#FFF; border:none; width:32px; height:32px; border-radius:6px; cursor:pointer; display:flex; align-items:center; justify-content:center;" title="%s"><i class="fa-solid fa-trash-can"></i></button>',
                    $b->id,
                    __('Delete Case', 'caretochina-medical')
                );
            }

            $b_patient_id = intval($b->patient_id ?? 0);
            $is_restricted = false;
            if ($b_patient_id > 0) {
                $is_restricted = get_user_meta($b_patient_id, 'patient_restricted', true) ? true : false;
            }

            $restrict_btn = '';
            if ($b_patient_id > 0) {
                $restrict_btn = sprintf(
                    '<button type="button" class="btn-action-restrict" onclick="appStaff.toggleRestrictPatient(%d, %d)" style="background:%s; color:#FFF; border:none; width:32px; height:32px; border-radius:6px; cursor:pointer; display:flex; align-items:center; justify-content:center;" title="%s"><i class="fa-solid fa-ban"></i></button>',
                    $b->id,
                    $b_patient_id,
                    $is_restricted ? '#EF4444' : '#F59E0B',
                    $is_restricted ? __('Unrestrict Patient Chat', 'caretochina-medical') : __('Restrict Patient Chat', 'caretochina-medical')
                );
            } else {
                $restrict_btn = '<button type="button" style="background:#E2E8F0; color:#94A3B8; border:none; width:32px; height:32px; border-radius:6px; cursor:not-allowed; display:flex; align-items:center; justify-content:center;" title="' . esc_attr(__('Guest users cannot be restricted', 'caretochina-medical')) . '" disabled><i class="fa-solid fa-ban"></i></button>';
            }

            $html .= sprintf('
                <tr style="border-bottom:1px solid #E2E8F0; font-size:13px; vertical-align:top;" data-row-booking-id="%d">
                    <td style="padding:14px; font-weight:700; color:#0F766E;">#%s</td>
                    <td style="padding:14px;">
                        <strong>%s</strong><br>
                        <span style="font-size:11px; color:#64748B;">%s</span><br>
                        <span style="font-size:11px; color:#64748B; font-style:italic;">%s</span>
                    </td>
                    <td style="padding:14px;">
                        <span style="font-weight:600;">%s</span><br>
                        <span style="font-size:11px; color:#64748B;">%s</span><br>
                        <div style="font-size:10px; color:#0F766E; margin-top:4px; line-height:1.3;">%s</div>
                    </td>
                    <td style="padding:14px; max-width:240px; word-wrap:break-word; white-space:normal;">
                        <strong>Hosp:</strong> %s<br>
                        <strong>Spec:</strong> %s<br>
                        <strong>Timing:</strong> %s<br>
                        <p style="margin:4px 0 0 0; font-size:11px; color:#64748B; display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden; text-overflow:ellipsis;">%s</p>
                    </td>
                    <td style="padding:14px;">
                        <span id="badge-status-%d" class="badge-pill" style="font-size:11px; padding:4px 10px; font-weight:700; border-radius:8px; display:inline-block; %s">
                            %s
                        </span>
                    </td>
                    <td style="padding:14px; text-align:right;">
                        <div style="display:flex; gap:6px; justify-content:flex-end; align-items:center;">
                            <button type="button" class="btn-action-verify" onclick="appStaff.verifyBooking(%d, \'%s\', \'%s\')" style="background:#0F766E; color:#FFF; border:none; width:32px; height:32px; border-radius:6px; cursor:pointer; display:flex; align-items:center; justify-content:center;" title="%s"><i class="fa-solid fa-user-check"></i></button>
                            <button type="button" class="btn-action-view" onclick="appStaff.viewBookingDetails(%d)" style="background:#3B82F6; color:#FFF; border:none; width:32px; height:32px; border-radius:6px; cursor:pointer; display:flex; align-items:center; justify-content:center;" title="%s"><i class="fa-solid fa-eye"></i></button>
                            %s
                            %s
                        </div>
                    </td>
                </tr>',
                $b->id,
                esc_html($b->booking_code),
                esc_html($b->full_name),
                $age_gender_str,
                esc_html($b->country),
                esc_html($b->email),
                esc_html($b->phone),
                $socials_str,
                esc_html($b->hospital_name),
                esc_html($b->specialty),
                esc_html($b->treatment_timing),
                esc_html($b->quote_details),
                $b->id,
                $status_style,
                strtoupper(esc_html($b->status)),
                $b->id, esc_js($b->full_name), esc_js($b->booking_code), __('Verify & Chat', 'caretochina-medical'),
                $b->id, __('View Details', 'caretochina-medical'),
                $restrict_btn,
                $delete_btn
            );
        }
        return $html;
    }

    /**
     * Generate HTML for Staff Header Notification Dropdown
     */
    public function generate_notifications_dropdown_html() {
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';
        $table_messages = $wpdb->prefix . 'caretochina_messages';

        $items = [];

        // 1. Pending Bookings (New Bookings requiring approval)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $pending_list = $wpdb->get_results($wpdb->prepare("SELECT id, booking_code, full_name, specialty, hospital_name, created_at FROM {$wpdb->prefix}caretochina_bookings WHERE status = %s ORDER BY id DESC LIMIT %d", 'pending', 6));
        if (!empty($pending_list)) {
            foreach ($pending_list as $b) {
                $items[] = [
                    'type'     => 'booking',
                    'id'       => intval($b->id),
                    'code'     => $b->booking_code,
                    'name'     => $b->full_name,
                    'title'    => /* translators: %s: dynamic value */
 sprintf(__('New Booking: #%s', 'caretochina-medical'), $b->booking_code),
                    'subtitle' => $b->full_name . ' • ' . ($b->specialty ?: ($b->hospital_name ?: __('Medical Care', 'caretochina-medical'))),
                    'time'     => human_time_diff(strtotime($b->created_at), current_time('timestamp')) . ' ' . __('ago', 'caretochina-medical'),
                    'ts'       => strtotime($b->created_at)
                ];
            }
        }

        // 2. Unread Messages from Patients
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $unread_msg_list = $wpdb->get_results($wpdb->prepare("
            SELECT m.id, m.booking_id, m.message, m.created_at, b.booking_code, b.full_name as patient_name 
            FROM {$wpdb->prefix}caretochina_messages m 
            JOIN {$wpdb->prefix}caretochina_bookings b ON m.booking_id = b.id 
            WHERE m.sender_type = %s AND m.is_read = %d 
            ORDER BY m.id DESC LIMIT %d
        ", 'patient', 0, 6));
            if (!empty($unread_msg_list)) {
                foreach ($unread_msg_list as $m) {
                    $items[] = [
                        'type'     => 'message',
                        'id'       => intval($m->booking_id),
                        'code'     => $m->booking_code,
                        'name'     => $m->patient_name,
                        'title'    => /* translators: %s: dynamic value */
 sprintf(__('Message from %s', 'caretochina-medical'), $m->patient_name),
                        'subtitle' => wp_html_excerpt($m->message, 34, '...'),
                        'time'     => human_time_diff(strtotime($m->created_at), current_time('timestamp')) . ' ' . __('ago', 'caretochina-medical'),
                        'ts'       => strtotime($m->created_at)
                    ];
                }
            }

        if (empty($items)) {
            return '<div style="padding:28px 16px; text-align:center; color:#94A3B8;"><i class="fa-regular fa-bell-slash" style="font-size:26px; margin-bottom:8px; display:block; color:#CBD5E1;"></i> ' . esc_html__('No new notifications', 'caretochina-medical') . '</div>';
        }

        // Sort by timestamp desc
        usort($items, function($a, $b) {
            return ($b['ts'] ?? 0) - ($a['ts'] ?? 0);
        });

        $html = '';
        foreach ($items as $item) {
            $icon = ($item['type'] === 'booking') ? 'fa-calendar-plus' : 'fa-comments';
            $icon_bg = ($item['type'] === 'booking') ? '#FEF3C7' : '#CCFBF1';
            $icon_color = ($item['type'] === 'booking') ? '#D97706' : '#0F766E';
            $badge_tag = ($item['type'] === 'booking') 
                ? '<span style="font-size:10px; background:#FEF3C7; color:#92400E; padding:2px 7px; border-radius:6px; font-weight:700; flex-shrink:0;">' . esc_html__('Approve', 'caretochina-medical') . '</span>' 
                : '<span style="font-size:10px; background:#CCFBF1; color:#0F766E; padding:2px 7px; border-radius:6px; font-weight:700; flex-shrink:0;">' . esc_html__('Chat', 'caretochina-medical') . '</span>';

            $html .= sprintf(
                '<div class="staff-dropdown-item" onclick="appStaff.handleDropdownItemClick(\'%s\', %d, \'%s\', \'%s\')" style="padding:12px 16px; border-bottom:1px solid #F1F5F9; cursor:pointer; display:flex; gap:12px; align-items:flex-start; transition:background 0.2s;">
                    <div style="width:34px; height:34px; border-radius:50%%; background:%s; color:%s; display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0; margin-top:2px;">
                        <i class="fa-solid %s"></i>
                    </div>
                    <div style="flex:1; min-width:0;">
                        <div style="display:flex; justify-content:space-between; align-items:center; gap:6px; margin-bottom:2px;">
                            <span style="font-weight:700; color:#0F172A; font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">%s</span>
                            %s
                        </div>
                        <div style="font-size:12px; color:#64748B; line-height:1.3; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">%s</div>
                        <div style="font-size:11px; color:#94A3B8; margin-top:4px;"><i class="fa-regular fa-clock" style="font-size:10px;"></i> %s</div>
                    </div>
                </div>',
                esc_attr($item['type']),
                intval($item['id']),
                esc_js($item['name']),
                esc_js($item['code']),
                $icon_bg,
                $icon_color,
                $icon,
                esc_html($item['title']),
                $badge_tag,
                esc_html($item['subtitle']),
                esc_html($item['time'])
            );
        }
        return $html;
    }

    public function get_chat_conversations($limit = 50) {
        global $wpdb;
        $limit = max(1, min(100, intval($limit)));

        // 1. Fetch active bookings with clean index
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT b.* 
             FROM {$wpdb->prefix}caretochina_bookings b
             WHERE b.status IN ('confirmed', 'completed', 'waiting')
             ORDER BY b.id DESC
             LIMIT %d",
            $limit
        ));

        if (empty($bookings)) {
            return [];
        }

        $booking_ids = wp_list_pluck($bookings, 'id');
        $escaped_ids = implode(',', array_map('intval', $booking_ids));

        // 2. Fetch unread counts in one single batch query (using idx_booking_sender_read)
        $unread_counts_map = [];
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $unread_rows = $wpdb->get_results(
            "SELECT booking_id, COUNT(*) as cnt 
             FROM {$wpdb->prefix}caretochina_messages 
             WHERE booking_id IN ($escaped_ids) AND sender_type = 'patient' AND is_read = 0 
             GROUP BY booking_id"
        );
        if (!empty($unread_rows)) {
            foreach ($unread_rows as $row) {
                $unread_counts_map[intval($row->booking_id)] = intval($row->cnt);
            }
        }

        // 3. Fetch latest message per booking in one batch query
        $latest_msgs_map = [];
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $latest_msg_ids = $wpdb->get_col(
            "SELECT MAX(id) 
             FROM {$wpdb->prefix}caretochina_messages 
             WHERE booking_id IN ($escaped_ids) 
             GROUP BY booking_id"
        );
        if (!empty($latest_msg_ids)) {
            $msg_id_list = implode(',', array_map('intval', $latest_msg_ids));
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $msg_rows = $wpdb->get_results("SELECT id, booking_id, message, attachment_type, attachment_name, sender_type, message_type, created_at FROM {$wpdb->prefix}caretochina_messages WHERE id IN ($msg_id_list)");
            if (!empty($msg_rows)) {
                foreach ($msg_rows as $mr) {
                    $latest_msgs_map[intval($mr->booking_id)] = $mr;
                }
            }
        }

        // 4. Map back to booking objects
        foreach ($bookings as $b) {
            $b_id = intval($b->id);
            $b->unread_count = $unread_counts_map[$b_id] ?? 0;
            if (isset($latest_msgs_map[$b_id])) {
                $lm = $latest_msgs_map[$b_id];
                $b->last_msg_text      = $lm->message;
                $b->last_msg_att_type  = $lm->attachment_type;
                $b->last_msg_att_name  = $lm->attachment_name;
                $b->last_msg_sender    = $lm->sender_type;
                $b->last_msg_type      = $lm->message_type;
                $b->last_msg_time      = $lm->created_at;
            } else {
                $b->last_msg_text      = null;
                $b->last_msg_att_type  = null;
                $b->last_msg_att_name  = null;
                $b->last_msg_sender    = null;
                $b->last_msg_type      = null;
                $b->last_msg_time      = null;
            }
        }

        return $bookings;
    }

    public function generate_chat_patient_list_html($bookings, $active_id = 0) {
        if (empty($bookings)) {
            return '<div style="padding:20px; text-align:center; color:#94A3B8; font-size:13px;">' . esc_html__('No patient chats found', 'caretochina-medical') . '</div>';
        }

        $html = '';
        foreach ($bookings as $index => $b) {
            $b_id = intval($b->id);
            $b_patient_id = intval($b->patient_id ?? 0);
            $is_guest = (intval($b->is_guest ?? 0) === 1 || $b_patient_id === 0);
            $is_active = ($active_id > 0) ? ($b_id === $active_id) : ($index === 0);
            $unread_count = intval($b->unread_count ?? 0);

            // Determine latest message preview snippet
            $msg_preview = '';
            if (isset($b->last_msg_type) && $b->last_msg_type === 'payment_request') {
                $prefix = ($b->last_msg_sender === 'coordinator') ? __('You: ', 'caretochina-medical') : '';
                $msg_preview = $prefix . '💳 ' . __('Payment Request', 'caretochina-medical');
            } elseif (!empty($b->last_msg_text)) {
                $prefix = ($b->last_msg_sender === 'coordinator') ? __('You: ', 'caretochina-medical') : '';
                $msg_preview = $prefix . esc_html(wp_html_excerpt($b->last_msg_text, 28, '...'));
            } elseif (!empty($b->last_msg_att_type)) {
                $prefix = ($b->last_msg_sender === 'coordinator') ? __('You: ', 'caretochina-medical') : '';
                $icon = ($b->last_msg_att_type === 'image') ? '📷 ' . __('Photo', 'caretochina-medical') : '📄 ' . __('Document', 'caretochina-medical');
                $msg_preview = $prefix . $icon;
            } else {
                $msg_preview = '<span style="color:#94A3B8; font-style:italic;">' . __('Case created', 'caretochina-medical') . '</span>';
            }

            // Determine formatted time
            $time_str = '';
            if (!empty($b->last_msg_time)) {
                $msg_ts = strtotime($b->last_msg_time);
                if (gmdate('Y-m-d', $msg_ts) === current_time('Y-m-d')) {
                    $time_str = date_i18n('g:i A', $msg_ts);
                } else {
                    $time_str = human_time_diff($msg_ts, current_time('timestamp')) . ' ago';
                }
            } elseif (!empty($b->created_at)) {
                $time_str = date_i18n('M d', strtotime($b->created_at));
            }

            $guest_badge = $is_guest ? '<span class="staff-chat-item-guest-tag">' . esc_html__('Guest', 'caretochina-medical') . '</span>' : '';
            $unread_badge = ($unread_count > 0) ? sprintf('<span class="staff-chat-item-unread-badge">%d</span>', $unread_count) : '';
            $active_class = $is_active ? 'active' : '';

            $html .= sprintf('
                <div class="staff-chat-patient-item %s" 
                     data-booking-id="%d" 
                     data-patient-id="%d" 
                     data-is-guest="%d"
                     onclick="appStaff.selectPatientChat(this, %d, \'%s\', \'%s\')">
                    <div class="staff-chat-item-top">
                        <div class="staff-chat-item-name-wrap">
                            <strong class="staff-chat-item-name">%s</strong>
                            %s
                        </div>
                        <span class="staff-chat-item-time">%s</span>
                    </div>
                    <div class="staff-chat-item-bottom">
                        <span class="staff-chat-item-code">#%s</span>
                        <div class="staff-chat-item-snippet %s">%s</div>
                        %s
                    </div>
                </div>',
                esc_attr($active_class),
                $b_id,
                $b_patient_id,
                $is_guest ? 1 : 0,
                $b_id,
                esc_js($b->full_name),
                esc_js($b->booking_code),
                esc_html($b->full_name),
                $guest_badge,
                esc_html($time_str),
                esc_html($b->booking_code),
                ($unread_count > 0) ? 'has-unread' : '',
                $msg_preview,
                $unread_badge
            );
        }
        return $html;
    }

    private function check_staff_capability() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('You must be logged in.', 'caretochina-medical')]);
        }
        $current_user = wp_get_current_user();
        if (!current_user_can('edit_posts') && !in_array('medical_staff', (array)$current_user->roles)) {
            wp_send_json_error(['message' => __('Access denied. Coordinator privileges required.', 'caretochina-medical')]);
        }
    }

    public function handle_get_bookings() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-medical')]);
        }
        $this->check_staff_capability();
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';
        
        $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        $paged = isset($_POST['paged']) ? max(1, absint($_POST['paged'])) : 1;
        $limit = 10;
        $offset = ($paged - 1) * $limit;

        if (!empty($search)) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $total_items = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}caretochina_bookings WHERE full_name LIKE %s OR email LIKE %s OR phone LIKE %s OR country LIKE %s OR booking_code LIKE %s",
                $like, $like, $like, $like, $like
            ));
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $bookings = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}caretochina_bookings WHERE full_name LIKE %s OR email LIKE %s OR phone LIKE %s OR country LIKE %s OR booking_code LIKE %s ORDER BY id DESC LIMIT %d OFFSET %d",
                $like, $like, $like, $like, $like, $limit, $offset
            ));
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}caretochina_bookings");
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $bookings = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}caretochina_bookings ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset));
        }
        
        $html = $this->generate_bookings_table_rows($bookings);
        
        // Generate pagination HTML
        $total_pages = ceil($total_items / $limit);
        $pagination_html = '';
        if ($total_pages > 1) {
            $pagination_html .= '<div class="ctc-pagination" style="display:flex; justify-content:center; gap:8px; margin-top:20px;">';
            for ($i = 1; $i <= $total_pages; $i++) {
                $active_style = ($i === $paged) ? 'background:#0F766E; color:#FFF; border-color:#0F766E;' : 'background:#FFF; color:#0F172A; border-color:#cbd5e1;';
                $pagination_html .= sprintf(
                    '<button type="button" class="ctc-page-num" onclick="appStaff.changeBookingsPage(%d)" style="padding:6px 12px; border-radius:6px; border:1px solid; cursor:pointer; font-weight:600; font-size:13px; transition:all 0.2s; %s">%d</button>',
                    $i, $active_style, $i
                );
            }
            $pagination_html .= '</div>';
        }

        wp_send_json_success([
            'html' => $html,
            'pagination' => $pagination_html,
            'total_items' => $total_items
        ]);
    }

    public function handle_check_new_bookings() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-medical')]);
        }
        $this->check_staff_capability();
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $count = intval($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}caretochina_bookings"));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $latest = $wpdb->get_row($wpdb->prepare("SELECT booking_code, full_name FROM {$wpdb->prefix}caretochina_bookings ORDER BY id DESC LIMIT %d", 1));
        
        wp_send_json_success([
            'count' => $count,
            'latest_code' => $latest ? $latest->booking_code : '',
            'latest_name' => $latest ? $latest->full_name : ''
        ]);
    }

    public function handle_send_typing() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-medical')]);
        }
        $this->check_staff_capability();
        $booking_id = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;
        if ($booking_id > 0) {
            set_transient('ctc_typing_' . $booking_id . '_coordinator', 1, 4);
            wp_send_json_success();
        }
        wp_send_json_error();
    }

    public function handle_staff_login() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Security verification failed.', 'caretochina-medical')]);
        }

        $username = isset($_POST['username']) ? sanitize_text_field(wp_unslash($_POST['username'])) : '';
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords must preserve special characters.
        $password = isset($_POST['password']) ? wp_unslash($_POST['password']) : '';

        $user = wp_signon(['user_login' => $username, 'user_password' => $password, 'remember' => true], is_ssl());

        if (is_wp_error($user)) {
            wp_send_json_error(['message' => __('Invalid staff username or password.', 'caretochina-medical')]);
        } else {
            if (user_can($user, 'edit_posts') || in_array('medical_staff', (array)$user->roles)) {
                wp_send_json_success(['message' => __('Credentials verified! Loading Staff Desk...', 'caretochina-medical')]);
            } else {
                wp_logout();
                wp_send_json_error(['message' => __('Access Denied: Account is not authorized for Staff Control Desk.', 'caretochina-medical')]);
            }
        }
    }

    public function handle_create_staff_account() {
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_admin_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_admin_nonce')) {
            wp_send_json_error(['message' => __('Unauthorized admin capability.', 'caretochina-medical')]);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized admin capability.', 'caretochina-medical')]);
        }

        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $username = isset($_POST['username']) ? sanitize_user(wp_unslash($_POST['username'])) : '';
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords must preserve special characters.
        $password = isset($_POST['password']) ? wp_unslash($_POST['password']) : '';

        if (empty($name) || empty($email) || empty($username) || empty($password)) {
            wp_send_json_error(['message' => __('All staff credential fields are required.', 'caretochina-medical')]);
        }

        if (preg_match('/\s/', $password)) {
            wp_send_json_error(['message' => __('Staff password cannot contain spaces or whitespace.', 'caretochina-medical')]);
        }

        $pass_len = strlen($password);
        if ($pass_len < 6 || $pass_len > 20) {
            wp_send_json_error(['message' => __('Staff password must be between 6 and 20 characters long.', 'caretochina-medical')]);
        }

        if (!preg_match('/[a-zA-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            wp_send_json_error(['message' => __('Staff password must contain both letters and numbers.', 'caretochina-medical')]);
        }

        if (username_exists($username) || email_exists($email)) {
            wp_send_json_error(['message' => __('Username or Email is already registered.', 'caretochina-medical')]);
        }

        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            wp_send_json_error(['message' => $user_id->get_error_message()]);
        } else {
            wp_update_user(['ID' => $user_id, 'display_name' => $name, 'role' => 'editor']);
            wp_send_json_success(['username' => $username]);
        }
    }

    public function handle_update_booking_status() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-medical')]);
        }
        $this->check_staff_capability();
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';

        $id = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : 'pending';

        if ($id > 0) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update($table_bookings, ['status' => $status], ['id' => $id]);
            wp_send_json_success(['status' => strtoupper($status)]);
        }
        wp_send_json_error(['message' => __('Invalid booking.', 'caretochina-medical')]);
    }

    public function handle_verify_booking() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-medical')]);
        }
        $this->check_staff_capability();
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';
        $table_messages = $wpdb->prefix . 'caretochina_messages';

        $id = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;

        if ($id > 0) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}caretochina_bookings WHERE id = %d", $id));
            if (!$booking) {
                wp_send_json_error(['message' => __('Booking not found.', 'caretochina-medical')]);
            }

            // 1. Update status to confirmed
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update($table_bookings, ['status' => 'confirmed'], ['id' => $id]);

            // 2. Send default message in chat
            $current_user = wp_get_current_user();
            $staff_name = $current_user->exists() ? 'Staff (' . $current_user->display_name . ')' : 'Staff (Coordinator)';
            
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->insert($table_messages, [
                'booking_id'  => $id,
                'sender_type' => 'coordinator',
                'sender_name' => $staff_name,
                'message'     => /* translators: %s: dynamic value */
 sprintf(__('Hello %s, your booking request has been verified by our staff. How can I help you today?', 'caretochina-medical'), $booking->full_name),
                'is_read'     => 0
            ]);

            // 3. Send email to patient (Non-blocking async)
            $email = $booking->email;
            if (!empty($email)) {
            $dash_url = class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_url('patient_dashboard') : home_url('/patient-dashboard/');
            $chat_url = !empty($booking->guest_token) ? home_url('/guest-chat/?token=' . $booking->guest_token) : ($booking->is_guest ? add_query_arg(['booking_code' => $booking->booking_code], $dash_url) : add_query_arg(['tab' => 'messages'], $dash_url));

            $email_data = [
                'patient_name'    => $booking->full_name,
                'full_name'       => $booking->full_name,
                'patient_email'   => $email,
                'patient_phone'   => $booking->phone ?? '',
                'booking_code'    => $booking->booking_code,
                'specialty'       => $booking->specialty ?? 'General Medical Care',
                'hospital_name'   => $booking->hospital_name ?? 'Assigned Hospital',
                'status'          => 'Verified & Approved',
                'chat_url'        => $chat_url,
                'dashboard_url'   => $dash_url,
            ];

            // Enrich with package data if available
            if (!empty($booking->package_id) && class_exists('CareToChina_Packages')) {
                $pkg = CareToChina_Packages::instance()->get_package(intval($booking->package_id));
                if ($pkg) {
                    $email_data['package_name']     = $pkg->name;
                    $email_data['package_price']    = $pkg->price_formatted;
                    $email_data['package_timeline'] = $pkg->timeline ?? '';
                }
            }

            if (class_exists('CareToChina_Email_Templates')) {
                CareToChina_Email_Templates::send_notification('status_update', $email, $email_data);
            }
        }

            wp_send_json_success([
                'booking_id' => $id,
                'status'     => 'confirmed',
                'message'    => __('Booking verified successfully.', 'caretochina-medical')
            ]);
        }
        wp_send_json_error(['message' => __('Invalid booking.', 'caretochina-medical')]);
    }

    public function get_staff_chat_html($booking_id) {
        global $wpdb;
        $table_messages = $wpdb->prefix . 'caretochina_messages';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}caretochina_messages SET is_read = %d WHERE booking_id = %d AND sender_type = %s", 1, $booking_id, 'patient'));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $messages = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}caretochina_messages WHERE booking_id = %d ORDER BY id ASC", $booking_id));

        $chat_html = '';
        if (empty($messages)) {
            $chat_html .= '<div class="chat-msg coordinator mb-14" style="display:flex; gap:12px; align-items:flex-start;"><img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=100&q=80" style="width:36px; height:36px; border-radius:50%;"><div class="msg-bubble" style="background:#0F766E; color:#FFF; border:none; padding:12px 18px; border-radius:18px; font-size:13px; line-height:1.4;"><strong>Elena (Care Coordinator):</strong> ' . esc_html__('Hello! How can I assist you with your treatment roadmap today?', 'caretochina-medical') . '</div></div>';
        } else {
            foreach ($messages as $m) {
                $read_tick = ($m->is_read == 1) ? '<span style="color:#3B82F6; margin-left:6px; font-weight:700;" title="' . esc_attr(__('Read by Patient', 'caretochina-medical')) . '">✓✓ Seen</span>' : '<span style="color:#94A3B8; margin-left:6px;" title="' . esc_attr(__('Delivered', 'caretochina-medical')) . '">✓ Delivered</span>';

                // Check for Payment Request message type
                if (isset($m->message_type) && $m->message_type === 'payment_request' && !empty($m->payment_request_id)) {
                    if (class_exists('CareToChina_Payment_Request_Manager')) {
                        $chat_html .= '<div class="chat-msg payment-request-msg mb-14" style="display:flex; justify-content:flex-end; margin-bottom:14px; width:100%;">';
                        $chat_html .= CareToChina_Payment_Request_Manager::render_card($m->payment_request_id, true);
                        $chat_html .= '</div>';
                        continue;
                    }
                }

                // Render File Attachment if present
                $attachment_html = '';
                if (!empty($m->attachment_url)) {
                    if ($m->attachment_type === 'image' || preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $m->attachment_url)) {
                        $attachment_html = '<div class="chat-attachment-image" style="margin-top:8px;"><a href="' . esc_url($m->attachment_url) . '" target="_blank" rel="noopener noreferrer"><img src="' . esc_url($m->attachment_url) . '" alt="' . esc_attr($m->attachment_name ?: 'Image Attachment') . '" style="max-width:240px; max-height:180px; border-radius:10px; object-fit:cover; display:block; border:1px solid rgba(0,0,0,0.1);"></a></div>';
                    } elseif ($m->attachment_type === 'pdf' || preg_match('/\.pdf$/i', $m->attachment_url)) {
                        $attachment_html = '<div class="chat-attachment-pdf" style="margin-top:8px;"><a href="' . esc_url($m->attachment_url) . '" target="_blank" rel="noopener noreferrer" style="display:inline-flex; align-items:center; gap:8px; background:rgba(255,255,255,0.95); color:#0F172A; padding:8px 12px; border-radius:8px; text-decoration:none; font-size:12px; font-weight:600; border:1px solid #CBD5E1;"><i class="fa-solid fa-file-pdf" style="color:#EF4444; font-size:16px;"></i> <span style="max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' . esc_html($m->attachment_name ?: 'Medical_Document.pdf') . '</span> <i class="fa-solid fa-arrow-down-to-bracket" style="color:#0F766E;"></i></a></div>';
                    }
                }

                if ($m->sender_type === 'coordinator') {
                    $name = 'roji';
                    if (preg_match('/Staff \((.+)\)/', $m->sender_name, $matches)) {
                        $name = $matches[1];
                    }
                    $msg_text_html = !empty($m->message) ? esc_html($m->message) : '';
                    $chat_html .= '<div class="chat-msg coordinator mb-14" style="display:flex; justify-content:flex-end; margin-bottom:14px; text-align:right; font-family:\'Inter\', sans-serif; width:100%; box-sizing:border-box;">
                        <div class="msg-bubble" style="background:#0F766E; color:#FFF; padding:10px 16px; border-radius:18px 18px 2px 18px; font-size:13px; max-width:82%; line-height:1.4; display:inline-block; text-align:left; border:none; word-break:break-word; overflow-wrap:anywhere; box-sizing:border-box;">
                            ' . $msg_text_html . ' <span style="font-size:11px; font-weight:700; color:#CCFBF1; margin-left:6px;">:Staff(' . esc_html($name) . ')</span>
                            ' . $attachment_html . '
                            <div style="font-size:9px; text-align:right; margin-top:4px; opacity:0.8;">' . $read_tick . '</div>
                        </div>
                    </div>';
                } else {
                    $pat_name = $m->sender_name ?: 'Patient';
                    $msg_text_html = !empty($m->message) ? esc_html($m->message) : '';
                    $chat_html .= '<div class="chat-msg patient mb-14" style="display:flex; justify-content:flex-start; margin-bottom:14px; font-family:\'Inter\', sans-serif; width:100%; box-sizing:border-box;">
                        <div class="msg-bubble" style="background:#FFFFFF; color:#0F172A; border:1px solid #E2E8F0; padding:10px 16px; border-radius:18px 18px 18px 2px; font-size:13px; max-width:82%; line-height:1.4; word-break:break-word; overflow-wrap:anywhere; box-sizing:border-box;">
                            <span style="font-weight:700; color:#0F766E; margin-right:4px;">Patient(' . esc_html($pat_name) . '):</span> ' . $msg_text_html . '
                            ' . $attachment_html . '
                        </div>
                    </div>';
                }
            }
        }
        return $chat_html;
    }

    public function handle_send_chat() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-medical')]);
        }
        $this->check_staff_capability();
        global $wpdb;
        $table_messages = $wpdb->prefix . 'caretochina_messages';

        $id = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;
        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';

        // Check if booking exists and is approved (confirmed/completed)
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}caretochina_bookings WHERE id = %d", $id));
        if (!$booking) {
            wp_send_json_error(['message' => __('Patient booking record not found.', 'caretochina-medical')]);
        }
        if ($booking->status === 'pending') {
            wp_send_json_error(['message' => __('Please approve this patient booking from the Bookings tab before sending messages.', 'caretochina-medical')]);
        }

        $current_user = wp_get_current_user();
        $staff_name = $current_user->exists() ? 'Staff (' . $current_user->display_name . ')' : 'Staff (Coordinator)';

        // Handle File Attachment (Max 2MB, Images & PDF)
        $attachment_url = '';
        $attachment_name = '';
        $attachment_type = '';

        if (!empty($_FILES['attachment']) && !empty($_FILES['attachment']['name'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $file = $_FILES['attachment'];

            // 1. Check size (max 2MB = 2097152 bytes)
            if ($file['size'] > 2097152) {
                wp_send_json_error(['message' => __('Attachment file size exceeds the 2MB limit.', 'caretochina-medical')]);
            }

            // 2. Validate MIME type
            $allowed_mimes = [
                'jpg|jpeg|jpe' => 'image/jpeg',
                'png'          => 'image/png',
                'webp'         => 'image/webp',
                'gif'          => 'image/gif',
                'pdf'          => 'application/pdf',
            ];

            require_once(ABSPATH . 'wp-admin/includes/file.php');
            $file_check = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], $allowed_mimes);

            if (empty($file_check['ext']) || empty($file_check['type'])) {
                wp_send_json_error(['message' => __('Invalid file format. Only images (JPG, PNG, WEBP, GIF) and PDF documents up to 2MB are allowed.', 'caretochina-medical')]);
            }

            // 3. Upload file
            $upload_overrides = ['test_form' => false, 'mimes' => $allowed_mimes];
            $movefile = wp_handle_upload($file, $upload_overrides);

            if ($movefile && !isset($movefile['error'])) {
                $attachment_url  = esc_url_raw($movefile['url']);
                $attachment_name = sanitize_file_name($file['name']);
                $attachment_type = (strpos($file_check['type'], 'image/') === 0) ? 'image' : 'pdf';
            } else {
                wp_send_json_error(['message' => __('Failed to upload file: ', 'caretochina-medical') . ($movefile['error'] ?? 'Unknown error')]);
            }
        }

        if ($id > 0 && (!empty($message) || !empty($attachment_url))) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->insert($table_messages, [
                'booking_id'      => $id,
                'sender_type'     => 'coordinator',
                'sender_name'     => $staff_name,
                'message'         => $message,
                'attachment_url'  => $attachment_url,
                'attachment_name' => $attachment_name,
                'attachment_type' => $attachment_type,
                'is_read'         => 0,
                'created_at'      => current_time('mysql'),
            ]);
            
            $html = $this->get_staff_chat_html($id);
            $chat_sidebar_html = $this->generate_chat_patient_list_html($this->get_chat_conversations(30), $id);
            wp_send_json_success([
                'sender' => $staff_name,
                'message' => $message,
                'html' => $html,
                'chat_sidebar_html' => $chat_sidebar_html
            ]);
        }
        wp_send_json_error(['message' => __('Please enter a message or select a file to send.', 'caretochina-medical')]);
    }

    public function handle_get_staff_chat() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-medical')]);
        }
        $this->check_staff_capability();

        $booking_id = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;

        if ($booking_id > 0) {
            $chat_html = $this->get_staff_chat_html($booking_id);
            // Check if patient is typing
            $is_typing = get_transient('ctc_typing_' . $booking_id . '_patient') ? true : false;

            wp_send_json_success([
                'html' => $chat_html,
                'is_typing' => $is_typing
            ]);
        }
        wp_send_json_error(['message' => __('Invalid booking ID', 'caretochina-medical')]);
    }

    public function handle_update_timeline() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-medical')]);
        }
        $this->check_staff_capability();
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';

        $booking_id = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;
        $stage = isset($_POST['timeline_stage']) ? max(1, min(5, absint($_POST['timeline_stage']))) : 1;

        if ($booking_id > 0) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update($table_bookings, ['timeline_stage' => $stage], ['id' => $booking_id]);
            /* translators: %d: Stage number */
            wp_send_json_success(['message' => sprintf(__('Timeline stage updated to Stage %d', 'caretochina-medical'), $stage)]);
        }
        wp_send_json_error(['message' => __('Invalid booking.', 'caretochina-medical')]);
    }

    public function handle_update_invoice() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-medical')]);
        }
        $this->check_staff_capability();
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';

        $booking_id = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;
        $status = isset($_POST['invoice_status']) ? sanitize_text_field(wp_unslash($_POST['invoice_status'])) : '';

        if ($booking_id > 0 && !empty($status)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update($table_bookings, ['invoice_status' => $status], ['id' => $booking_id]);
            wp_send_json_success(['status' => $status]);
        }
        wp_send_json_error(['message' => __('Invalid invoice status update.', 'caretochina-medical')]);
    }

    public function generate_admin_staff_table_rows() {
        $users = get_users(['role__in' => ['administrator', 'editor', 'medical_staff']]);
        $html = '';
        foreach ($users as $u) {
            $joined = date_i18n('M d, Y', strtotime($u->user_registered));
            $display_name = esc_html($u->display_name ? $u->display_name : $u->first_name . ' ' . $u->last_name);
            if (empty(trim($display_name))) {
                $display_name = esc_html($u->user_login);
            }
            $roles = esc_html(implode(', ', array_map('ucfirst', $u->roles)));
            
            // Prevent deleting yourself
            $is_self = ($u->ID === get_current_user_id());
            $delete_btn = '';
            if (!$is_self) {
                $delete_btn = sprintf(
                    '<button type="button" onclick="deleteStaffAccount(%d)" style="background:#EF4444; color:#FFF; border:none; padding:6px 12px; border-radius:6px; cursor:pointer; font-weight:700; font-size:11px;"><i class="fa-solid fa-trash"></i> %s</button>',
                    $u->ID, esc_html__('Delete', 'caretochina-medical')
                );
            } else {
                $delete_btn = '<span style="font-size:11px; color:#64748B; font-weight:600; font-style:italic;">You (Self)</span>';
            }

            $html .= sprintf('
                <tr style="border-bottom:1px solid #E2E8F0; font-size:13px; vertical-align:middle;" id="staff-row-%d">
                    <td style="padding:14px; font-weight:700; color:#0F766E;">%s</td>
                    <td style="padding:14px; font-weight:600;">%s</td>
                    <td style="padding:14px;">%s</td>
                    <td style="padding:14px;"><span class="badge-pill" style="font-size:11px; background:#CCFBF1; color:#0F766E; padding:4px 10px; font-weight:700; border-radius:8px;">%s</span></td>
                    <td style="padding:14px; color:#64748B;">%s</td>
                    <td style="padding:14px; text-align:right;">%s</td>
                </tr>',
                $u->ID,
                $display_name,
                esc_html($u->user_login),
                esc_html($u->user_email),
                $roles,
                $joined,
                $delete_btn
            );
        }
        return $html;
    }

    public function handle_get_staff_list() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-medical')]);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized admin capability.', 'caretochina-medical')]);
        }
        $html = $this->generate_admin_staff_table_rows();
        wp_send_json_success(['html' => $html]);
    }

    public function handle_delete_staff_account() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-medical')]);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized admin capability.', 'caretochina-medical')]);
        }
        
        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        if ($user_id > 0) {
            if ($user_id === get_current_user_id()) {
                wp_send_json_error(['message' => __('You cannot delete your own account.', 'caretochina-medical')]);
            }
            
            // Delete user in WordPress
            require_once ABSPATH . 'wp-admin/includes/user.php';
            $deleted = wp_delete_user($user_id);
            if ($deleted) {
                wp_send_json_success(['message' => __('Staff account deleted successfully.', 'caretochina-medical')]);
            } else {
                wp_send_json_error(['message' => __('Failed to delete user account.', 'caretochina-medical')]);
            }
        }
        wp_send_json_error(['message' => __('Invalid user ID.', 'caretochina-medical')]);
    }

    public function handle_admin_delete_patient_data() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-medical')]);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized admin capability.', 'caretochina-medical')]);
        }

        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';
        $table_messages = $wpdb->prefix . 'caretochina_messages';

        $booking_id = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;

        if ($booking_id > 0) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}caretochina_bookings WHERE id = %d", $booking_id));
            if ($booking) {
                // Delete messages for this booking
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->delete($table_messages, ['booking_id' => $booking_id]);

                // Delete booking itself
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->delete($table_bookings, ['id' => $booking_id]);

                // Delete patient WP User account if exists and has the role 'patient'
                $patient_id = intval($booking->patient_id);
                if ($patient_id > 0) {
                    $user = get_userdata($patient_id);
                    if ($user && in_array('patient', (array)$user->roles)) {
                        // Delete avatar file if exists
                        $avatar_url = get_user_meta($patient_id, 'patient_avatar', true);
                        if (!empty($avatar_url)) {
                            $upload_dir = wp_upload_dir();
                            $base_url = $upload_dir['baseurl'];
                            $base_dir = $upload_dir['basedir'];
                            if (strpos($avatar_url, $base_url) === 0) {
                                $old_file_path = str_replace($base_url, $base_dir, $avatar_url);
                                if (file_exists($old_file_path)) {
                                    wp_delete_file($old_file_path);
                                }
                            }
                        }
                        require_once ABSPATH . 'wp-admin/includes/user.php';
                        wp_delete_user($patient_id);
                    }
                }
                wp_send_json_success(['message' => __('Patient and associated booking data deleted successfully.', 'caretochina-medical')]);
            }
        }
        wp_send_json_error(['message' => __('Failed to delete patient data.', 'caretochina-medical')]);
    }

    public function handle_staff_check_unread_updates() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-medical')]);
        }
        $this->check_staff_capability();
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';
        $table_messages = $wpdb->prefix . 'caretochina_messages';

        // 1. Get total and pending bookings count
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $bookings_count = intval($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}caretochina_bookings"));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $pending_bookings_count = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}caretochina_bookings WHERE status = %s", 'pending')));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $latest_booking = $wpdb->get_row($wpdb->prepare("SELECT booking_code, full_name FROM {$wpdb->prefix}caretochina_bookings ORDER BY id DESC LIMIT %d", 1));

        // 2. Get unread messages count
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $unread_messages_count = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}caretochina_messages WHERE sender_type = %s AND is_read = %d",
            'patient',
            0
        )));

        // 3. Get latest unread message from a patient
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $latest_message = $wpdb->get_row($wpdb->prepare("
            SELECT m.*, b.booking_code, b.full_name as patient_name 
            FROM {$wpdb->prefix}caretochina_messages m 
            JOIN {$wpdb->prefix}caretochina_bookings b ON m.booking_id = b.id 
            WHERE m.sender_type = %s AND m.is_read = %d 
            ORDER BY m.id DESC LIMIT %d
        ", 'patient', 0, 1));

        $unread_items = [];
        
        // Fetch pending bookings list
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $pending_list = $wpdb->get_results($wpdb->prepare("SELECT id, booking_code, full_name, specialty, created_at FROM {$wpdb->prefix}caretochina_bookings WHERE status = %s ORDER BY id DESC LIMIT %d", 'pending', 5));
        if (!empty($pending_list)) {
            foreach ($pending_list as $item) {
                $unread_items[] = [
                    'type' => 'booking',
                    'id' => intval($item->id),
                    'code' => $item->booking_code,
                    'name' => $item->full_name,
                    'title' => /* translators: %s: dynamic value */
 sprintf(__('New Booking: #%s', 'caretochina-medical'), $item->booking_code),
                    'subtitle' => $item->full_name . ' • ' . ($item->specialty ?: __('Medical Consultation', 'caretochina-medical')),
                    'time' => human_time_diff(strtotime($item->created_at), current_time('timestamp')) . ' ago'
                ];
            }
        }

        // Fetch unread messages list
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $unread_msg_list = $wpdb->get_results($wpdb->prepare("
            SELECT m.id, m.booking_id, m.message, m.created_at, b.booking_code, b.full_name as patient_name 
            FROM {$wpdb->prefix}caretochina_messages m 
            JOIN {$wpdb->prefix}caretochina_bookings b ON m.booking_id = b.id 
            WHERE m.sender_type = %s AND m.is_read = %d 
            ORDER BY m.id DESC LIMIT %d
        ", 'patient', 0, 5));
        if (!empty($unread_msg_list)) {
            foreach ($unread_msg_list as $item) {
                $unread_items[] = [
                    'type' => 'message',
                    'id' => intval($item->booking_id),
                    'code' => $item->booking_code,
                    'name' => $item->patient_name,
                    'title' => /* translators: %s: dynamic value */
 sprintf(__('Message from %s', 'caretochina-medical'), $item->patient_name),
                    'subtitle' => wp_html_excerpt($item->message, 34, '...'),
                    'time' => human_time_diff(strtotime($item->created_at), current_time('timestamp')) . ' ago'
                ];
            }
        }

        $active_booking_id = isset($_POST['active_booking_id']) ? absint($_POST['active_booking_id']) : 0;
        $conversations = $this->get_chat_conversations(30);
        $chat_sidebar_html = $this->generate_chat_patient_list_html($conversations, $active_booking_id);
        $dropdown_html = $this->generate_notifications_dropdown_html();

        wp_send_json_success([
            'bookings_count' => $bookings_count,
            'pending_bookings_count' => $pending_bookings_count,
            'unread_messages_count' => $unread_messages_count,
            'latest_booking_code' => $latest_booking ? $latest_booking->booking_code : '',
            'latest_booking_name' => $latest_booking ? $latest_booking->full_name : '',
            'latest_message_id' => $latest_message ? intval($latest_message->id) : 0,
            'latest_message_sender' => $latest_message ? $latest_message->patient_name : '',
            'latest_message_code' => $latest_message ? $latest_message->booking_code : '',
            'latest_message_text' => $latest_message ? wp_html_excerpt($latest_message->message, 40) : '',
            'latest_message_booking_id' => $latest_message ? intval($latest_message->booking_id) : 0,
            'unread_items' => $unread_items,
            'dropdown_html' => $dropdown_html,
            'chat_sidebar_html' => $chat_sidebar_html
        ]);
    }

    public function add_admin_bar_notification_node($wp_admin_bar) {
        if (!current_user_can('edit_posts')) {
            return;
        }

        $unread_count = $this->get_unread_notifications_count();

        $title = '<span class="ab-icon dashicons-before dashicons-bell" style="top:2px;"></span>';
        if ($unread_count > 0) {
            $title .= sprintf(' <span class="ab-label" style="background:#EF4444 !important; color:#FFF !important; border-radius:50%%; padding:0 6px; font-weight:700; font-size:10px; margin-left:4px; display:inline-block; line-height:16px; height:16px;">%d</span>', $unread_count);
        }

        $wp_admin_bar->add_node([
            'id'    => 'staff-desk-notifications',
            'title' => $title,
            'href'  => admin_url('admin.php?page=caretochina-staff-desk'),
            'meta'  => [
                'title' => __('Care Staff Desk Notifications', 'caretochina-medical'),
            ]
        ]);

        if ($unread_count > 0) {
            global $wpdb;
            $table_bookings = $wpdb->prefix . 'caretochina_bookings';
            $table_messages = $wpdb->prefix . 'caretochina_messages';

            // 1. Fetch pending bookings
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $pending_list = $wpdb->get_results($wpdb->prepare("SELECT id, booking_code, full_name FROM {$wpdb->prefix}caretochina_bookings WHERE status = %s ORDER BY id DESC LIMIT %d", 'pending', 3));
            if (!empty($pending_list)) {
                foreach ($pending_list as $item) {
                    $wp_admin_bar->add_node([
                        'id'     => 'staff-notif-booking-' . $item->id,
                        'parent' => 'staff-desk-notifications',
                        'title'  => sprintf('📅 New Booking: #%s (%s)', esc_html($item->booking_code), esc_html($item->full_name)),
                        'href'   => admin_url('admin.php?page=caretochina-staff-desk'),
                    ]);
                }
            }

            // 2. Fetch unread patient messages
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $unread_msg_list = $wpdb->get_results($wpdb->prepare("
                SELECT m.id, m.booking_id, m.message, b.booking_code, b.full_name as patient_name 
                FROM {$wpdb->prefix}caretochina_messages m 
                JOIN {$wpdb->prefix}caretochina_bookings b ON m.booking_id = b.id 
                WHERE m.sender_type = %s AND m.is_read = %d 
                ORDER BY m.id DESC LIMIT %d
            ", 'patient', 0, 3));
            if (!empty($unread_msg_list)) {
                foreach ($unread_msg_list as $item) {
                    $wp_admin_bar->add_node([
                        'id'     => 'staff-notif-msg-' . $item->id,
                        'parent' => 'staff-desk-notifications',
                        'title'  => sprintf('💬 Msg from %s: "%s"', esc_html($item->patient_name), esc_html(wp_html_excerpt($item->message, 25))),
                        'href'   => admin_url('admin.php?page=caretochina-staff-desk'),
                    ]);
                }
            }
        }
    }

    public function hide_admin_bar_on_staff_portal($show) {
        if (is_admin()) {
            return $show;
        }

        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $roles = (array) $user->roles;

            // Administrators always keep the top admin bar
            if (current_user_can('manage_options')) {
                return $show;
            }

            // Hide admin bar specifically for staff (editor / medical_staff) and patient roles
            if (in_array('patient', $roles) || in_array('editor', $roles) || in_array('medical_staff', $roles)) {
                return false;
            }
        }

        return $show;
    }

    public function restrict_staff_admin_access() {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            $is_staff = (current_user_can('edit_posts') || in_array('medical_staff', (array)$current_user->roles));
            if ($is_staff && !current_user_can('manage_options')) {
                wp_safe_redirect(home_url('/staff-portal/'));
                exit;
            }
        }
    }

    public function handle_get_booking_details() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-medical')]);
        }
        $this->check_staff_capability();
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';
        
        $id = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;
        if ($id > 0) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $b = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}caretochina_bookings WHERE id = %d", $id));
            if ($b) {
                wp_send_json_success([
                    'code' => $b->booking_code,
                    'name' => $b->full_name,
                    'email' => $b->email,
                    'phone' => $b->phone,
                    'age' => $b->age ? $b->age . ' yrs' : 'N/A',
                    'gender' => $b->gender ?: 'N/A',
                    'country' => $b->country ?: 'N/A',
                    'whatsapp' => $b->whatsapp ?: 'N/A',
                    'wechat' => $b->wechat ?: 'N/A',
                    'messenger' => $b->messenger ?: 'N/A',
                    'linkedin' => $b->linkedin ?: 'N/A',
                    'hospital' => $b->hospital_name,
                    'specialty' => $b->specialty,
                    'timing' => $b->treatment_timing,
                    'quote' => esc_html($b->quote_details),
                    'status' => strtoupper($b->status),
                    'invoice' => $b->invoice_status ?: 'Pending'
                ]);
            }
        }
        wp_send_json_error(['message' => __('Booking details not found.', 'caretochina-medical')]);
    }

    public function handle_toggle_restrict() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-medical')]);
        }
        $this->check_staff_capability();
        $patient_id = isset($_POST['patient_id']) ? absint($_POST['patient_id']) : 0;
        $reason = isset($_POST['reason']) ? sanitize_text_field(wp_unslash($_POST['reason'])) : '';
        
        if ($patient_id > 0) {
            $currently_restricted = get_user_meta($patient_id, 'patient_restricted', true) ? true : false;
            if ($currently_restricted) {
                delete_user_meta($patient_id, 'patient_restricted');
                delete_user_meta($patient_id, 'patient_restriction_reason');
                wp_send_json_success(['message' => __('Patient chat restriction has been removed.', 'caretochina-medical')]);
            } else {
                update_user_meta($patient_id, 'patient_restricted', 1);
                update_user_meta($patient_id, 'patient_restriction_reason', $reason ?: __('Violation of terms of service.', 'caretochina-medical'));
                wp_send_json_success(['message' => __('Patient has been restricted from live chat.', 'caretochina-medical')]);
            }
        }
        wp_send_json_error(['message' => __('Invalid patient ID.', 'caretochina-medical')]);
    }

    public function handle_staff_cancel_booking() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-medical')]);
        }
        if (!current_user_can('caretochina_manage_bookings') && !current_user_can('manage_options') && !current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'caretochina-medical')]);
        }

        $booking_id = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;
        $confirm_no_refund = !empty($_POST['confirm_no_refund']);

        $res = CareToChina_Payment_Manager::instance()->cancel_booking($booking_id, get_current_user_id(), $confirm_no_refund);

        if (is_wp_error($res)) {
            wp_send_json_error(['message' => $res->get_error_message()]);
        }
        wp_send_json_success($res);
    }

    public function handle_staff_refund_booking() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-medical')]);
        }
        if (!current_user_can('caretochina_manage_bookings') && !current_user_can('manage_options') && !current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'caretochina-medical')]);
        }

        $booking_id = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;
        $amount = isset($_POST['amount']) ? floatval(wp_unslash($_POST['amount'])) : 0;
        $reason = isset($_POST['reason']) ? sanitize_text_field(wp_unslash($_POST['reason'])) : '';

        $res = CareToChina_Payment_Manager::instance()->refund_booking($booking_id, $amount, $reason, get_current_user_id());

        if (is_wp_error($res)) {
            wp_send_json_error(['message' => $res->get_error_message()]);
        }
        wp_send_json_success($res);
    }

    public function handle_get_payment_audit_logs() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-medical')]);
        }
        $booking_id = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;

        global $wpdb;
        $table_logs = $wpdb->prefix . 'caretochina_payment_audit_logs';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}caretochina_payment_audit_logs WHERE booking_id = %d ORDER BY id DESC LIMIT %d",
            $booking_id,
            50
        ));

        wp_send_json_success(['logs' => $logs ?: []]);
    }
}