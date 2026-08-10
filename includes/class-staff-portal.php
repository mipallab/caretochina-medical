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

        $pending_bookings = 0;
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_bookings'") === $table_bookings) {
            $pending_bookings = intval($wpdb->get_var("SELECT COUNT(*) FROM $table_bookings WHERE status = 'pending'"));
        }

        $unread_messages = 0;
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_messages'") === $table_messages) {
            $unread_messages = intval($wpdb->get_var("SELECT COUNT(*) FROM $table_messages WHERE sender_type = 'patient' AND is_read = 0"));
        }

        return $pending_bookings + $unread_messages;
    }

    public function register_admin_menu() {
        $unread_count = $this->get_unread_notifications_count();
        $menu_title = __('Care Staff Desk', 'caretochina-staff');
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
        echo '<div class="wrap" style="padding:20px; font-family:\'Inter\', sans-serif;">';
        echo '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">';
        echo '<h1 class="wp-heading-inline" style="margin:0;"><i class="fa-solid fa-user-doctor text-teal"></i> ' . __('Medical Technical Person Portal', 'caretochina-staff') . '</h1>';
        if (current_user_can('manage_options')) {
            echo '<button type="button" onclick="jQuery(\'#admin-create-staff-modal\').css(\'display\', \'flex\')" class="button button-primary" style="background:#0F766E; border-color:#0F766E; font-weight:700;"><i class="fa-solid fa-user-plus"></i> + ' . __('Create New Staff Account', 'caretochina-staff') . '</button>';
        }
        echo '</div>';

        $this->render_portal_ui();

        if (current_user_can('manage_options')) {
            ?>
            <div id="admin-create-staff-modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(15, 23, 42, 0.65); backdrop-filter:blur(6px); z-index:100000; align-items:center; justify-content:center;">
                <div style="background:#FFFFFF; border-radius:24px; width:550px; max-width:90%; padding:32px; box-shadow:0 20px 40px rgba(0,0,0,0.3); font-family:'Inter', sans-serif; max-height:90vh; overflow-y:auto;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #E2E8F0; padding-bottom:12px;">
                        <h2 style="margin:0; font-family:'Manrope'; color:#0F172A; font-size:22px;"><i class="fa-solid fa-user-plus" style="color:#0F766E;"></i> <?php _e('Create Medical Staff Account', 'caretochina-staff'); ?></h2>
                        <button type="button" onclick="jQuery('#admin-create-staff-modal').hide()" style="background:none; border:none; font-size:20px; cursor:pointer; color:#64748B;">&times;</button>
                    </div>

                    <form id="admin-create-staff-form" onsubmit="createStaffAccount(event)">
                        <div style="margin-bottom:16px;">
                            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:6px;"><?php _e('Staff Full Name *', 'caretochina-staff'); ?></label>
                            <input type="text" id="stf_name" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1;">
                        </div>
                        <div style="margin-bottom:16px;">
                            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:6px;"><?php _e('Staff Email Address *', 'caretochina-staff'); ?></label>
                            <input type="email" id="stf_email" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1;">
                        </div>
                        <div style="margin-bottom:16px;">
                            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:6px;"><?php _e('Staff Username *', 'caretochina-staff'); ?></label>
                            <input type="text" id="stf_user" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1;">
                        </div>
                        <div style="margin-bottom:20px;">
                            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:6px;"><?php _e('Set Password *', 'caretochina-staff'); ?></label>
                            <input type="password" id="stf_pass" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1;">
                        </div>

                        <div style="display:flex; justify-content:flex-end; gap:12px; border-top:1px solid #E2E8F0; padding-top:16px;">
                            <button type="button" onclick="jQuery('#admin-create-staff-modal').hide()" class="button button-secondary"><?php _e('Cancel', 'caretochina-staff'); ?></button>
                            <button type="submit" id="stf_submit_btn" class="button button-primary" style="background:#0F766E; border-color:#0F766E; font-weight:700; padding:6px 20px;"><?php _e('Create Staff Credential', 'caretochina-staff'); ?></button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
            function createStaffAccount(e) {
                e.preventDefault();
                var btn = jQuery('#stf_submit_btn');
                btn.prop('disabled', true).text('<?php echo esc_js(__('Creating...', 'caretochina-staff')); ?>');
                var nonce = '<?php echo wp_create_nonce("caretochina_staff_admin_nonce"); ?>';

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
                        alert('<?php echo esc_js(__('Medical Staff Account created!', 'caretochina-staff')); ?> Username: ' + res.data.username);
                        jQuery('#admin-create-staff-modal').hide();
                        // If we are on the Staff Management tab, refresh staff list
                        if (typeof refreshAdminStaffList === 'function') {
                            refreshAdminStaffList();
                        }
                    } else {
                        alert(res.data.message);
                    }
                    btn.prop('disabled', false).text('<?php echo esc_js(__('Create Staff Credential', 'caretochina-staff')); ?>');
                });
            }
            </script>
            <?php
        }
        echo '</div>';
    }

    private function render_staff_login_ui() {
        ?>
        <div class="careyou-staff-portal-wrapper caretochina-staff-portal-wrapper" style="max-width:550px; margin:50px auto;">
            <div class="glass-card" style="padding:40px; background:#FFFFFF; border-radius:24px; border:1px solid #E2E8F0; box-shadow:0 20px 40px -15px rgba(15, 118, 110, 0.15);">
                <div style="text-align:center; margin-bottom:24px;">
                    <div style="width:64px; height:64px; border-radius:50%; background:#CCFBF1; color:#0F766E; display:inline-flex; align-items:center; justify-content:center; font-size:28px; margin-bottom:12px;">
                        <i class="fa-solid fa-user-doctor"></i>
                    </div>
                    <h2 style="font-family:'Manrope'; color:#0F172A; margin:0 0 6px 0;"><?php _e('Medical Staff Portal Login', 'caretochina-staff'); ?></h2>
                    <p style="color:#64748B; font-size:14px; margin:0;"><?php _e('Strictly for authorized Care Coordinators & Technical Staff.', 'caretochina-staff'); ?></p>
                </div>

                <form id="staff-portal-login-form">
                    <div style="margin-bottom:20px;">
                        <label class="form-label" style="display:block; font-weight:600; font-family:'Manrope'; color:#0F172A; font-size:14px; margin-bottom:8px;"><?php _e('Staff Username or Email *', 'caretochina-staff'); ?></label>
                        <input type="text" name="username" class="form-input" required style="width:100%; padding:14px 18px; border-radius:12px; border:1px solid #cbd5e1;">
                    </div>
                    <div style="margin-bottom:24px;">
                        <label class="form-label" style="display:block; font-weight:600; font-family:'Manrope'; color:#0F172A; font-size:14px; margin-bottom:8px;"><?php _e('Staff Password *', 'caretochina-staff'); ?></label>
                        <input type="password" name="password" class="form-input" required style="width:100%; padding:14px 18px; border-radius:12px; border:1px solid #cbd5e1;">
                    </div>
                    <button type="submit" id="staff_login_btn" class="btn btn-primary btn-full btn-lg" style="width:100%; padding:16px; border-radius:999px; background:#0F766E; color:#FFFFFF; font-family:'Manrope'; font-size:16px; font-weight:700; border:none; cursor:pointer;">
                        <i class="fa-solid fa-lock"></i> <?php _e('Access Medical Control Desk', 'caretochina-staff'); ?>
                    </button>
                </form>
                <div id="staff-login-response" style="display:none; margin-top:20px; text-align:center; font-weight:700;"></div>
            </div>
        </div>

        <script>
        jQuery('#staff-portal-login-form').on('submit', function(e) {
            e.preventDefault();
            var btn = jQuery('#staff_login_btn');
            var res = jQuery('#staff-login-response');
            var apiObj = (typeof caretochina_staff_obj !== 'undefined') ? caretochina_staff_obj : careyou_staff_obj;

            btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> <?php echo esc_js(__('Verifying Credentials...', 'caretochina-staff')); ?>');

            jQuery.post(apiObj.ajax_url, {
                action: 'caretochina_staff_login',
                username: jQuery(this).find('input[name="username"]').val(),
                password: jQuery(this).find('input[name="password"]').val(),
                nonce: apiObj.nonce
            }, function(response) {
                res.show();
                if (response.success) {
                    res.css('color', '#10B981').text(response.data.message);
                    setTimeout(function() { window.location.reload(); }, 1000);
                } else {
                    res.css('color', '#EF4444').text(response.data.message);
                    btn.prop('disabled', false).html('<i class="fa-solid fa-lock"></i> <?php echo esc_js(__('Access Medical Control Desk', 'caretochina-staff')); ?>');
                }
            });
        });
        </script>
        <?php
    }

    private function render_portal_ui() {
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';
        $table_messages = $wpdb->prefix . 'caretochina_messages';

        $pending_bookings_count = 0;
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_bookings'") === $table_bookings) {
            $pending_bookings_count = intval($wpdb->get_var("SELECT COUNT(*) FROM $table_bookings WHERE status = 'pending'"));
        }

        $unread_messages_count = 0;
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_messages'") === $table_messages) {
            $unread_messages_count = intval($wpdb->get_var("SELECT COUNT(*) FROM $table_messages WHERE sender_type = 'patient' AND is_read = 0"));
        }

        $bookings = $wpdb->get_results("SELECT * FROM $table_bookings ORDER BY id DESC LIMIT 10");

        if (empty($bookings)) {
            $bookings = [
                (object)[
                    'id' => 1,
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

        $active_b = $bookings[0];
        ?>
        <div class="careyou-staff-portal-wrapper caretochina-staff-portal-wrapper" data-booking-id="<?php echo esc_attr($active_b->id); ?>" data-booking-count="<?php echo count($bookings); ?>">
            <!-- STAFF BANNER HEADER -->
            <div class="staff-header-banner">
                <div style="display:flex; align-items:center; gap:18px;">
                    <div style="width:58px; height:58px; border-radius:50%; background:#CCFBF1; color:#0F766E; display:flex; align-items:center; justify-content:center; font-size:26px; border:2.5px solid #FFF;">
                        <i class="fa-solid fa-user-nurse"></i>
                    </div>
                    <div>
                        <h2><?php _e('Medical Coordinator Control Desk', 'caretochina-staff'); ?></h2>
                        <p><i class="fa-solid fa-circle text-success" style="color:#10B981;"></i> <?php _e('Active Duty • Selected Case:', 'caretochina-staff'); ?> <strong id="header-active-case-code">#<?php echo esc_html($active_b->booking_code); ?></strong> (<span id="header-active-patient-name"><?php echo esc_html($active_b->full_name); ?></span>)</p>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:16px;">
                    <!-- Theme Toggle Button -->
                    <button type="button" class="staff-theme-toggle-btn" onclick="window.appToggleTheme()" style="background:rgba(255,255,255,0.15); border:none; width:42px; height:42px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; color:#FFF; font-size:18px; transition:all 0.2s;" title="<?php _e('Toggle Dark/Light Mode', 'caretochina-staff'); ?>">
                        <i class="fa-solid fa-circle-half-stroke"></i>
                    </button>
                    <div id="staff-header-bell" style="position:relative; width:42px; height:42px; border-radius:50%; background:rgba(255,255,255,0.15); display:flex; align-items:center; justify-content:center; font-size:18px; color:#FFF; cursor:pointer; transition:all 0.2s;" onclick="appStaff.handleNotificationClick(event)">
                        <i class="fa-solid fa-bell"></i>
                        <span id="staff-header-bell-badge" style="position:absolute; top:-4px; right:-4px; background:#EF4444; color:#FFF; border-radius:50%; width:18px; height:18px; display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:700; border:2px solid #0F766E; <?php echo (($pending_bookings_count + $unread_messages_count) === 0) ? 'display:none;' : ''; ?>"><?php echo ($pending_bookings_count + $unread_messages_count); ?></span>
                        
                        <!-- NOTIFICATION DROPDOWN -->
                        <div id="staff-bell-dropdown" style="display:none; position:absolute; top:48px; right:0; width:320px; background:#FFF; border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.15); border:1px solid #E2E8F0; z-index:9999; color:#0F172A; text-align:left; font-family:'Inter', sans-serif;">
                            <div style="padding:12px 16px; border-bottom:1px solid #F1F5F9; font-weight:700; font-size:14px; color:#0F766E; display:flex; justify-content:space-between; align-items:center;">
                                <span><?php _e('Notifications', 'caretochina-staff'); ?></span>
                                <span style="font-size:11px; background:#FFE4E6; color:#E11D48; padding:2px 8px; border-radius:10px; font-weight:600;"><?php _e('Unread', 'caretochina-staff'); ?></span>
                            </div>
                            <div id="staff-bell-dropdown-list" style="max-height:280px; overflow-y:auto; font-size:12px;">
                                <div style="padding:20px; text-align:center; color:#94A3B8;"><?php _e('Loading notifications...', 'caretochina-staff'); ?></div>
                            </div>
                        </div>
                    </div>
                    <span class="badge-pill" style="background:rgba(255,255,255,0.2); color:#FFFFFF; border:1px solid rgba(255,255,255,0.3); font-size:13px; font-weight:700; padding:8px 18px;"><?php _e('Staff Duty: ACTIVE', 'caretochina-staff'); ?></span>
                </div>
            </div>

            <!-- MAIN STAFF GRID -->
            <div class="staff-container">
                <!-- SIDEBAR NAVIGATION -->
                <div class="staff-sidebar">
                    <button type="button" class="staff-sidebar-toggle-btn" onclick="appStaff.toggleSidebar()"><i class="fa-solid fa-angles-left"></i></button>
                    <button class="staff-tab active" onclick="appStaff.switchTab(this, 'bookings')">
                        <i class="fa-solid fa-calendar-check"></i> 
                        <span><?php _e('Bookings & Approvals', 'caretochina-staff'); ?></span>
                        <span id="staff-bookings-badge" class="staff-notification-badge" style="background:#EF4444; color:#FFF; border-radius:50%; padding:2px 6px; font-size:10px; font-weight:700; margin-left:6px; <?php echo ($pending_bookings_count === 0) ? 'display:none;' : ''; ?>"><?php echo $pending_bookings_count; ?></span>
                    </button>
                    <button class="staff-tab" onclick="appStaff.switchTab(this, 'chat')">
                        <i class="fa-solid fa-comments"></i> 
                        <span><?php _e('Patient Live Chat', 'caretochina-staff'); ?></span>
                        <span id="staff-chat-badge" class="staff-notification-badge" style="background:#EF4444; color:#FFF; border-radius:50%; padding:2px 6px; font-size:10px; font-weight:700; margin-left:6px; <?php echo ($unread_messages_count === 0) ? 'display:none;' : ''; ?>"><?php echo $unread_messages_count; ?></span>
                    </button>
                    <button class="staff-tab" onclick="appStaff.switchTab(this, 'timeline')"><i class="fa-solid fa-timeline"></i> <span><?php _e('Treatment Timeline', 'caretochina-staff'); ?></span></button>
                    <button class="staff-tab" onclick="appStaff.switchTab(this, 'invoices')"><i class="fa-solid fa-file-invoice-dollar"></i> <span><?php _e('Invoices & Payments', 'caretochina-staff'); ?></span></button>
                    <?php if (current_user_can('manage_options')) : ?>
                        <button class="staff-tab" onclick="appStaff.switchTab(this, 'admin-settings')"><i class="fa-solid fa-user-gear"></i> <span><?php _e('Staff Management (Admin)', 'caretochina-staff'); ?></span></button>
                    <?php endif; ?>
                </div>

                <!-- STAFF PANELS -->
                <div class="staff-content">
                    
                    <!-- TAB 1: BOOKING MANAGEMENT -->
                    <div class="staff-panel active" id="staff-panel-bookings">
                        <div class="glass-card">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; gap:15px; flex-wrap:wrap;">
                                <h3 style="margin:0; font-family:'Manrope', sans-serif; color:#0F172A; font-size:22px; font-weight:700;"><?php _e('Patient Booking Approvals & Status', 'caretochina-staff'); ?></h3>
                                <div style="display:flex; align-items:center; gap:8px; background:#FFF; border:1px solid #cbd5e1; border-radius:8px; padding:6px 12px; width:300px; max-width:100%; box-sizing:border-box;">
                                    <i class="fa-solid fa-magnifying-glass" style="color:#64748B; font-size:14px;"></i>
                                    <input type="text" id="staff-booking-search" placeholder="<?php _e('Search patients...', 'caretochina-staff'); ?>" style="border:none; outline:none; font-size:13px; width:100%; color:#0F172A; font-family:'Inter',sans-serif;" onkeyup="appStaff.searchBookings(this.value)">
                                </div>
                            </div>
                            
                            <div style="overflow-x:auto; width:100%;">
                                <table style="width:100%; border-collapse:collapse; text-align:left; border:1px solid #E2E8F0; border-radius:14px; overflow:hidden;">
                                    <thead>
                                        <tr style="background:#F8FAFC; border-bottom:2px solid #E2E8F0; color:#64748B; font-size:12px; font-weight:700; text-transform:uppercase;">
                                            <th style="padding:14px;"><?php _e('Case Code', 'caretochina-staff'); ?></th>
                                            <th style="padding:14px;"><?php _e('Patient Profile', 'caretochina-staff'); ?></th>
                                            <th style="padding:14px;"><?php _e('Contact & Socials', 'caretochina-staff'); ?></th>
                                            <th style="padding:14px;"><?php _e('Quote & Request details', 'caretochina-staff'); ?></th>
                                            <th style="padding:14px;"><?php _e('Current Status', 'caretochina-staff'); ?></th>
                                            <th style="padding:14px; width:180px; text-align:right;"><?php _e('Staff Actions', 'caretochina-staff'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="staff-bookings-tbody">
                                        <?php echo $this->generate_bookings_table_rows($bookings); ?>
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
                                <h2 style="margin:0; font-family:'Manrope'; color:#0F172A; font-size:22px;"><i class="fa-solid fa-eye" style="color:#3B82F6;"></i> <?php _e('Case Details', 'caretochina-staff'); ?> <span id="view-modal-code" style="color:#0F766E;"></span></h2>
                                <button type="button" onclick="jQuery('#staff-view-booking-modal').hide()" style="background:none; border:none; font-size:20px; cursor:pointer; color:#64748B;">&times;</button>
                            </div>
                            <div id="view-modal-content" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; font-size:13px; color:#334155; line-height:1.5;">
                                <!-- Populated dynamically by JS -->
                            </div>
                            <div style="display:flex; justify-content:flex-end; margin-top:24px; border-top:1px solid #E2E8F0; padding-top:16px;">
                                <button type="button" class="button button-secondary" onclick="jQuery('#staff-view-booking-modal').hide()"><?php _e('Close Window', 'caretochina-staff'); ?></button>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: LIVE PATIENT CHAT (SIDEBAR LAYOUT) -->
                    <div class="staff-panel" id="staff-panel-chat">
                        <div class="glass-card" style="padding:0; overflow:hidden;">
                            <div class="staff-chat-layout" style="display:flex; height:500px; background:#FFF; font-family:'Inter', sans-serif;">
                                <!-- Left Sidebar: Patients List -->
                                <div class="staff-chat-sidebar" style="width:240px; border-right:1px solid #E2E8F0; display:flex; flex-direction:column; background:#F8FAFC;">
                                    <div style="padding:16px; border-bottom:1px solid #E2E8F0; font-weight:700; font-family:'Manrope'; color:#0F172A;"><?php _e('Patient Chats', 'caretochina-staff'); ?></div>
                                    <div class="staff-chat-patient-list" style="flex:1; overflow-y:auto;">
                                        <?php foreach ($bookings as $index => $b): ?>
                                            <div class="staff-chat-patient-item <?php echo $index === 0 ? 'active' : ''; ?>" 
                                                 data-booking-id="<?php echo $b->id; ?>" 
                                                 data-patient-id="<?php echo $b->patient_id; ?>" 
                                                 onclick="appStaff.selectPatientChat(this, <?php echo $b->id; ?>, '<?php echo esc_js($b->full_name); ?>', '<?php echo esc_js($b->booking_code); ?>')" 
                                                 style="padding:12px 16px; border-bottom:1px solid #E2E8F0; cursor:pointer; transition:all 0.2s; <?php echo $index === 0 ? 'background:#CCFBF1; border-left:4px solid #0F766E;' : ''; ?>">
                                                <div style="font-weight:700; color:#0F172A; font-size:13px;"><?php echo esc_html($b->full_name); ?></div>
                                                <div style="font-size:11px; color:#64748B; margin-top:2px; display:flex; justify-content:space-between; align-items:center;">
                                                    <span>#<?php echo esc_html($b->booking_code); ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <!-- Right Area: Messaging -->
                                <div class="staff-chat-thread" style="flex:1; display:flex; flex-direction:column;">
                                    <div class="staff-chat-thread-header" style="padding:16px; border-bottom:1px solid #E2E8F0; background:#FFF; display:flex; justify-content:space-between; align-items:center;">
                                        <div>
                                            <strong id="chat-active-patient-name" style="font-family:'Manrope'; color:#0F172A;"><?php echo esc_html($active_b->full_name); ?></strong>
                                            <span id="chat-active-patient-code" style="font-size:12px; color:#64748B; margin-left:8px;">#<?php echo esc_html($active_b->booking_code); ?></span>
                                        </div>
                                        <span style="font-size:12px; color:#10B981; font-weight:600;"><i class="fa-solid fa-circle" style="font-size:8px;"></i> <?php _e('Live', 'caretochina-staff'); ?></span>
                                    </div>
                                    <div id="staff-chat-box" class="dash-chat-box" style="flex:1; overflow-y:auto; padding:20px; background:#F8FAFC; max-height: 330px;">
                                        <!-- Loaded dynamically via AJAX Polling -->
                                    </div>
                                    <div id="staff-chat-typing-indicator" style="padding:4px 20px; font-size:12px; color:#64748B; font-style:italic; display:none; background:#F8FAFC;"></div>
                                    <?php $is_active_b_guest = ($active_b->patient_id == 0); ?>
                                    <form id="staff-chat-form" style="padding:12px 16px; border-top:1px solid #E2E8F0; background:#FFF; display:flex; gap:12px; align-items:center; margin:0; <?php echo $is_active_b_guest ? 'display:none;' : ''; ?>">
                                        <input type="hidden" name="booking_id" id="staff_chat_booking_id" value="<?php echo esc_attr($active_b->id); ?>">
                                        <input type="text" name="message" id="staff_chat_input" class="form-input" placeholder="<?php _e('Type a response to patient...', 'caretochina-staff'); ?>" required style="flex:1; padding:11px; border-radius:10px; border:1px solid #cbd5e1; font-size:14px;">
                                        <button type="submit" class="ctc-solid-btn btn-teal-primary" style="padding:11px 22px; font-size:14px; border-radius:10px; cursor:pointer;"><i class="fa-solid fa-paper-plane"></i> <?php _e('Send', 'caretochina-staff'); ?></button>
                                    </form>
                                    <div id="staff-chat-guest-notice" style="padding:16px; background:#FEF3C7; color:#B45309; text-align:center; font-size:13px; font-weight:600; border-top:1px solid #FCD34D; width:100%; box-sizing:border-box; <?php echo !$is_active_b_guest ? 'display:none;' : ''; ?>">
                                        <i class="fa-solid fa-triangle-exclamation"></i> <?php _e('This patient is an unregistered guest user. Live chat will be enabled once they register their account.', 'caretochina-staff'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 3: TREATMENT TIMELINE UPDATE -->
                    <div class="staff-panel" id="staff-panel-timeline">
                        <div class="glass-card">
                            <h3 style="margin:0 0 12px 0; font-family:'Manrope', sans-serif; color:#0F172A; font-size:22px; font-weight:700;"><?php _e('Update Patient Roadmap Stage', 'caretochina-staff'); ?></h3>
                            <p style="color:#64748B; margin-bottom:24px;"><?php _e('Advance patient treatment milestones in real-time:', 'caretochina-staff'); ?></p>

                            <form id="staff-timeline-form">
                                <input type="hidden" name="booking_id" id="staff_timeline_booking_id" value="<?php echo esc_attr($active_b->id); ?>">
                                <div style="display:flex; flex-direction:column; gap:12px; margin-bottom:24px;">
                                    <label style="font-weight:600; font-size:15px; color:#0F172A;"><?php _e('Select Active Treatment Stage:', 'caretochina-staff'); ?></label>
                                    <select name="timeline_stage" id="staff_stage_select" style="font-size:15px; padding:14px; border-radius:12px; border:1px solid #cbd5e1; background:#FFF; color:#0F172A;">
                                        <option value="1" <?php selected($active_b->timeline_stage, 1); ?>><?php _e('Stage 1: Medical Assessment & Consultation', 'caretochina-staff'); ?></option>
                                        <option value="2" <?php selected($active_b->timeline_stage, 2); ?>><?php _e('Stage 2: Hospital Guarantee & Embassy Visa Issued', 'caretochina-staff'); ?></option>
                                        <option value="3" <?php selected($active_b->timeline_stage, 3); ?>><?php _e('Stage 3: Airport Arrival & Chauffeur Transfer (ACTIVE)', 'caretochina-staff'); ?></option>
                                        <option value="4" <?php selected($active_b->timeline_stage, 4); ?>><?php _e('Stage 4: Surgical Procedure at Partner Hospital', 'caretochina-staff'); ?></option>
                                        <option value="5" <?php selected($active_b->timeline_stage, 5); ?>><?php _e('Stage 5: Post-Op Recovery & Lifetime Telehealth', 'caretochina-staff'); ?></option>
                                    </select>
                                </div>
                                <button type="submit" class="ctc-solid-btn btn-teal-primary" style="padding:14px 28px; border-radius:999px; cursor:pointer;"><i class="fa-solid fa-sync"></i> <?php _e('Update Timeline Stage', 'caretochina-staff'); ?></button>
                            </form>
                        </div>
                    </div>

                    <!-- TAB 4: INVOICE & PAYMENT MANAGEMENT -->
                    <div class="staff-panel" id="staff-panel-invoices">
                        <div class="glass-card">
                            <h3 style="margin:0 0 24px 0; font-family:'Manrope', sans-serif; color:#0F172A; font-size:22px; font-weight:700;"><?php _e('Invoice & Payment Approval', 'caretochina-staff'); ?></h3>
                            
                            <div style="background:#F8FAFC; border:1px solid #E2E8F0; padding:24px; border-radius:16px; margin-bottom:24px;">
                                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
                                    <div>
                                        <h4 style="margin:0 0 4px 0; font-size:18px; color:#0F172A;"><?php _e('All-Inclusive Procedure Package', 'caretochina-staff'); ?></h4>
                                        <p style="color:#64748B; margin:0; font-size:14px;"><?php _e('Patient:', 'caretochina-staff'); ?> <span id="invoice-active-patient-name"><?php echo esc_html($active_b->full_name); ?></span> (<span id="invoice-active-patient-email"><?php echo esc_html($active_b->email); ?></span>)</p>
                                    </div>
                                    <div style="text-align:right;">
                                        <h3 style="margin:0 0 4px 0; color:#0F766E; font-size:24px; font-weight:800;"><?php _e('$14,500.00', 'caretochina-staff'); ?></h3>
                                        <span id="staff-invoice-badge" class="badge-pill" style="background:#D1FAE5; color:#065F46; padding:6px 14px; font-weight:700;"><?php echo esc_html($active_b->invoice_status ?? 'Deposit Paid ($2,000)'); ?></span>
                                    </div>
                                </div>
                            </div>

                            <form id="staff-invoice-form">
                                <input type="hidden" name="booking_id" id="staff_invoice_booking_id" value="<?php echo esc_attr($active_b->id); ?>">
                                <div style="display:flex; gap:14px; align-items:center;">
                                    <label style="font-weight:600; font-size:14px; color:#0F172A; white-space:nowrap;"><?php _e('Set Payment Status:', 'caretochina-staff'); ?></label>
                                    <select name="invoice_status" id="staff_invoice_select" style="flex:1; padding:12px 16px; border-radius:12px; border:1px solid #cbd5e1; background:#FFF; font-size:15px;">
                                        <option value="Deposit Paid ($2,000)"><?php _e('Deposit Paid ($2,000)', 'caretochina-staff'); ?></option>
                                        <option value="Fully Paid ($14,500.00)"><?php _e('Fully Paid ($14,500.00)', 'caretochina-staff'); ?></option>
                                        <option value="Pending Deposit"><?php _e('Pending Deposit', 'caretochina-staff'); ?></option>
                                        <option value="Payment Rejected"><?php _e('Payment Rejected', 'caretochina-staff'); ?></option>
                                    </select>
                                    <button type="submit" class="ctc-solid-btn btn-teal-primary" style="padding:12px 24px; border-radius:999px; cursor:pointer; white-space:nowrap;"><i class="fa-solid fa-check-double"></i> <?php _e('Save Status', 'caretochina-staff'); ?></button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- TAB 5: STAFF MANAGEMENT (ADMIN ONLY) -->
                    <?php if (current_user_can('manage_options')) : ?>
                        <div class="staff-panel" id="staff-panel-admin-settings" style="display:none;">
                            <div class="glass-card">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
                                    <h3 style="margin:0; font-family:'Manrope', sans-serif; color:#0F172A; font-size:22px; font-weight:700;"><?php _e('Medical Staff Accounts Management', 'caretochina-staff'); ?></h3>
                                    <button type="button" onclick="jQuery('#admin-create-staff-modal').css('display', 'flex')" class="ctc-solid-btn btn-teal-primary" style="padding:10px 20px; font-size:14px; border-radius:10px; cursor:pointer;"><i class="fa-solid fa-user-plus"></i> + <?php _e('Create Staff User', 'caretochina-staff'); ?></button>
                                </div>
                                
                                <div style="overflow-x:auto; width:100%;">
                                    <table style="width:100%; border-collapse:collapse; text-align:left; border:1px solid #E2E8F0; border-radius:14px; overflow:hidden;">
                                        <thead>
                                            <tr style="background:#F8FAFC; border-bottom:2px solid #E2E8F0; color:#64748B; font-size:12px; font-weight:700; text-transform:uppercase;">
                                                <th style="padding:14px;"><?php _e('Staff Name', 'caretochina-staff'); ?></th>
                                                <th style="padding:14px;"><?php _e('Username', 'caretochina-staff'); ?></th>
                                                <th style="padding:14px;"><?php _e('Email Address', 'caretochina-staff'); ?></th>
                                                <th style="padding:14px;"><?php _e('Role / Capability', 'caretochina-staff'); ?></th>
                                                <th style="padding:14px;"><?php _e('Joined Date', 'caretochina-staff'); ?></th>
                                                <th style="padding:14px; width:150px; text-align:right;"><?php _e('Actions', 'caretochina-staff'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="admin-staff-list-tbody">
                                            <?php echo $this->generate_admin_staff_table_rows(); ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

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
                    __('Delete Case', 'caretochina-staff')
                );
            }

            $is_restricted = false;
            if ($b->patient_id > 0) {
                $is_restricted = get_user_meta($b->patient_id, 'patient_restricted', true) ? true : false;
            }

            $restrict_btn = '';
            if ($b->patient_id > 0) {
                $restrict_btn = sprintf(
                    '<button type="button" class="btn-action-restrict" onclick="appStaff.toggleRestrictPatient(%d, %d)" style="background:%s; color:#FFF; border:none; width:32px; height:32px; border-radius:6px; cursor:pointer; display:flex; align-items:center; justify-content:center;" title="%s"><i class="fa-solid fa-ban"></i></button>',
                    $b->id,
                    $b->patient_id,
                    $is_restricted ? '#EF4444' : '#F59E0B',
                    $is_restricted ? __('Unrestrict Patient Chat', 'caretochina-staff') : __('Restrict Patient Chat', 'caretochina-staff')
                );
            } else {
                $restrict_btn = '<button type="button" style="background:#E2E8F0; color:#94A3B8; border:none; width:32px; height:32px; border-radius:6px; cursor:not-allowed; display:flex; align-items:center; justify-content:center;" title="' . esc_attr(__('Guest users cannot be restricted', 'caretochina-staff')) . '" disabled><i class="fa-solid fa-ban"></i></button>';
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
                $b->id, esc_js($b->full_name), esc_js($b->booking_code), __('Verify & Chat', 'caretochina-staff'),
                $b->id, __('View Details', 'caretochina-staff'),
                $restrict_btn,
                $delete_btn
            );
        }
        return $html;
    }

    private function check_staff_capability() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('You must be logged in.', 'caretochina-staff')]);
        }
        $current_user = wp_get_current_user();
        if (!current_user_can('edit_posts') && !in_array('medical_staff', (array)$current_user->roles)) {
            wp_send_json_error(['message' => __('Access denied. Coordinator privileges required.', 'caretochina-staff')]);
        }
    }

    public function handle_get_bookings() {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-staff')]);
        }
        $this->check_staff_capability();
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';
        
        $search = sanitize_text_field($_POST['search'] ?? '');
        $paged = intval($_POST['paged'] ?? 1);
        $limit = 10;
        $offset = ($paged - 1) * $limit;

        $where = ' WHERE 1=1 ';
        if (!empty($search)) {
            $where .= $wpdb->prepare(
                " AND (full_name LIKE %s OR email LIKE %s OR phone LIKE %s OR country LIKE %s OR booking_code LIKE %s) ",
                "%$search%", "%$search%", "%$search%", "%$search%", "%$search%"
            );
        }

        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_bookings $where");
        $bookings = $wpdb->get_results("SELECT * FROM $table_bookings $where ORDER BY id DESC LIMIT $limit OFFSET $offset");
        
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
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-staff')]);
        }
        $this->check_staff_capability();
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';
        
        $count = intval($wpdb->get_var("SELECT COUNT(*) FROM $table_bookings"));
        $latest = $wpdb->get_row("SELECT booking_code, full_name FROM $table_bookings ORDER BY id DESC LIMIT 1");
        
        wp_send_json_success([
            'count' => $count,
            'latest_code' => $latest ? $latest->booking_code : '',
            'latest_name' => $latest ? $latest->full_name : ''
        ]);
    }

    public function handle_send_typing() {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-staff')]);
        }
        $this->check_staff_capability();
        $booking_id = intval($_POST['booking_id'] ?? 0);
        if ($booking_id > 0) {
            set_transient('ctc_typing_' . $booking_id . '_coordinator', 1, 4);
            wp_send_json_success();
        }
        wp_send_json_error();
    }

    public function handle_staff_login() {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Security verification failed.', 'caretochina-staff')]);
        }

        $username = sanitize_text_field($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $user = wp_signon(['user_login' => $username, 'user_password' => $password, 'remember' => true], is_ssl());

        if (is_wp_error($user)) {
            wp_send_json_error(['message' => __('Invalid staff username or password.', 'caretochina-staff')]);
        } else {
            if (user_can($user, 'edit_posts') || in_array('medical_staff', (array)$user->roles)) {
                wp_send_json_success(['message' => __('Credentials verified! Loading Staff Desk...', 'caretochina-staff')]);
            } else {
                wp_logout();
                wp_send_json_error(['message' => __('Access Denied: Account is not authorized for Staff Control Desk.', 'caretochina-staff')]);
            }
        }
    }

    public function handle_create_staff_account() {
        $nonce = $_POST['_wpnonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_admin_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_admin_nonce')) {
            wp_send_json_error(['message' => __('Unauthorized admin capability.', 'caretochina-staff')]);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized admin capability.', 'caretochina-staff')]);
        }

        $name = sanitize_text_field($_POST['name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $username = sanitize_user($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($name) || empty($email) || empty($username) || empty($password)) {
            wp_send_json_error(['message' => __('All staff credential fields are required.', 'caretochina-staff')]);
        }

        if (username_exists($username) || email_exists($email)) {
            wp_send_json_error(['message' => __('Username or Email is already registered.', 'caretochina-staff')]);
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
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-staff')]);
        }
        $this->check_staff_capability();
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';

        $id = intval($_POST['booking_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? 'pending');

        if ($id > 0) {
            $wpdb->update($table_bookings, ['status' => $status], ['id' => $id]);
            wp_send_json_success(['status' => strtoupper($status)]);
        }
        wp_send_json_error(['message' => __('Invalid booking.', 'caretochina-staff')]);
    }

    public function handle_verify_booking() {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-staff')]);
        }
        $this->check_staff_capability();
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';
        $table_messages = $wpdb->prefix . 'caretochina_messages';

        $id = intval($_POST['booking_id'] ?? 0);

        if ($id > 0) {
            $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_bookings WHERE id = %d", $id));
            if (!$booking) {
                wp_send_json_error(['message' => __('Booking not found.', 'caretochina-staff')]);
            }

            // 1. Update status to confirmed
            $wpdb->update($table_bookings, ['status' => 'confirmed'], ['id' => $id]);

            // 2. Send default message in chat
            $current_user = wp_get_current_user();
            $staff_name = $current_user->exists() ? 'Staff (' . $current_user->display_name . ')' : 'Staff (Coordinator)';
            
            $wpdb->insert($table_messages, [
                'booking_id'  => $id,
                'sender_type' => 'coordinator',
                'sender_name' => $staff_name,
                'message'     => sprintf(__('Hello %s, your booking request has been verified by our staff. How can I help you today?', 'caretochina-staff'), $booking->full_name),
                'is_read'     => 0
            ]);

            // 3. Send email to patient
            $email = $booking->email;
            $subject = sprintf(__('Your CareToChina Booking Has Been Verified - Case #%s', 'caretochina-staff'), $booking->booking_code);
            $message = sprintf(
                __("Dear %s,\n\nWe are pleased to inform you that your booking request with CareToChina has been verified by our medical coordinators.\n\nYou can now log in to your Patient Dashboard and message your coordinator directly in the live chat tab at:\n%s\n\nBest regards,\nCareToChina Medical Travel Desk", 'caretochina-staff'),
                $booking->full_name, home_url('/patient-dashboard/')
            );
            $headers = ['Content-Type: text/plain; charset=UTF-8', 'From: CareToChina Health <care@caretochina.com>'];
            
            wp_mail($email, $subject, $message, $headers);

            wp_send_json_success();
        }
        wp_send_json_error(['message' => __('Invalid booking.', 'caretochina-staff')]);
    }

    public function get_staff_chat_html($booking_id) {
        global $wpdb;
        $table_messages = $wpdb->prefix . 'caretochina_messages';

        $wpdb->query($wpdb->prepare("UPDATE $table_messages SET is_read = 1 WHERE booking_id = %d AND sender_type = %s", $booking_id, 'patient'));

        $messages = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_messages WHERE booking_id = %d ORDER BY id ASC", $booking_id));

        $chat_html = '';
        if (empty($messages)) {
            $chat_html .= '<div class="chat-msg coordinator mb-14" style="display:flex; gap:12px; align-items:flex-start;"><img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=100&q=80" style="width:36px; height:36px; border-radius:50%;"><div class="msg-bubble" style="background:#0F766E; color:#FFF; border:none; padding:12px 18px; border-radius:18px; font-size:13px; line-height:1.4;"><strong>Elena (Care Coordinator):</strong> ' . __('Hello! How can I assist you with your treatment roadmap today?', 'caretochina-staff') . '</div></div>';
        } else {
            foreach ($messages as $m) {
                $read_tick = ($m->is_read == 1) ? '<span style="color:#3B82F6; margin-left:6px; font-weight:700;" title="' . esc_attr(__('Read by Patient', 'caretochina-staff')) . '">✓✓ Seen</span>' : '<span style="color:#94A3B8; margin-left:6px;" title="' . esc_attr(__('Delivered', 'caretochina-staff')) . '">✓ Delivered</span>';

                if ($m->sender_type === 'coordinator') {
                    $name = 'roji';
                    if (preg_match('/Staff \((.+)\)/', $m->sender_name, $matches)) {
                        $name = $matches[1];
                    }
                    $chat_html .= '<div class="chat-msg coordinator mb-14" style="display:flex; justify-content:flex-end; margin-bottom:14px; text-align:right; font-family:\'Inter\', sans-serif; width:100%;">
                        <div class="msg-bubble" style="background:#0F766E; color:#FFF; padding:10px 16px; border-radius:18px 18px 2px 18px; font-size:13px; max-width:80%; line-height:1.4; display:inline-block; text-align:left; border:none;">
                            ' . esc_html($m->message) . ' <span style="font-size:11px; font-weight:700; color:#CCFBF1; margin-left:6px;">:Staff(' . esc_html($name) . ')</span>
                            <div style="font-size:9px; text-align:right; margin-top:4px; opacity:0.8;">' . $read_tick . '</div>
                        </div>
                    </div>';
                } else {
                    $pat_name = $m->sender_name;
                    $chat_html .= '<div class="chat-msg patient mb-14" style="display:flex; justify-content:flex-start; margin-bottom:14px; font-family:\'Inter\', sans-serif; width:100%;">
                        <div class="msg-bubble" style="background:#FFFFFF; color:#0F172A; border:1px solid #E2E8F0; padding:10px 16px; border-radius:18px 18px 18px 2px; font-size:13px; max-width:80%; line-height:1.4;">
                            <span style="font-weight:700; color:#0F766E; margin-right:4px;">Patient(' . esc_html($pat_name) . '):</span> ' . esc_html($m->message) . '
                        </div>
                    </div>';
                }
            }
        }
        return $chat_html;
    }

    public function handle_send_chat() {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-staff')]);
        }
        $this->check_staff_capability();
        global $wpdb;
        $table_messages = $wpdb->prefix . 'caretochina_messages';

        $id = intval($_POST['booking_id'] ?? 0);
        $message = sanitize_textarea_field($_POST['message'] ?? '');

        $current_user = wp_get_current_user();
        $staff_name = $current_user->exists() ? 'Staff (' . $current_user->display_name . ')' : 'Staff (Coordinator)';

        if ($id > 0 && !empty($message)) {
            $wpdb->insert($table_messages, [
                'booking_id'  => $id,
                'sender_type' => 'coordinator',
                'sender_name' => $staff_name,
                'message'     => $message,
                'is_read'     => 0,
                'created_at'  => current_time('mysql'),
            ]);
            
            $html = $this->get_staff_chat_html($id);
            wp_send_json_success(['sender' => $staff_name, 'message' => $message, 'html' => $html]);
        }
        wp_send_json_error(['message' => __('Invalid chat submission.', 'caretochina-staff')]);
    }

    public function handle_get_staff_chat() {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-staff')]);
        }
        $this->check_staff_capability();

        $booking_id = intval($_POST['booking_id'] ?? 0);

        if ($booking_id > 0) {
            $chat_html = $this->get_staff_chat_html($booking_id);
            // Check if patient is typing
            $is_typing = get_transient('ctc_typing_' . $booking_id . '_patient') ? true : false;

            wp_send_json_success([
                'html' => $chat_html,
                'is_typing' => $is_typing
            ]);
        }
        wp_send_json_error(['message' => __('Invalid booking ID', 'caretochina-staff')]);
    }

    public function handle_update_timeline() {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-staff')]);
        }
        $this->check_staff_capability();
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';

        $booking_id = intval($_POST['booking_id'] ?? 0);
        $stage = intval($_POST['timeline_stage'] ?? 1);

        if ($booking_id > 0) {
            $wpdb->update($table_bookings, ['timeline_stage' => $stage], ['id' => $booking_id]);
            wp_send_json_success(['message' => sprintf(__('Timeline stage updated to Stage %d', 'caretochina-staff'), $stage)]);
        }
        wp_send_json_error(['message' => __('Invalid booking.', 'caretochina-staff')]);
    }

    public function handle_update_invoice() {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-staff')]);
        }
        $this->check_staff_capability();
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';

        $booking_id = intval($_POST['booking_id'] ?? 0);
        $status = sanitize_text_field($_POST['invoice_status'] ?? '');

        if ($booking_id > 0 && !empty($status)) {
            $wpdb->update($table_bookings, ['invoice_status' => $status], ['id' => $booking_id]);
            wp_send_json_success(['status' => $status]);
        }
        wp_send_json_error(['message' => __('Invalid invoice status update.', 'caretochina-staff')]);
    }

    public function generate_admin_staff_table_rows() {
        $users = get_users(['role__in' => ['administrator', 'editor', 'medical_staff']]);
        $html = '';
        foreach ($users as $u) {
            $joined = date('M d, Y', strtotime($u->user_registered));
            $display_name = esc_html($u->display_name ? $u->display_name : $u->first_name . ' ' . $u->last_name);
            if (empty(trim($display_name))) {
                $display_name = esc_html($u->user_login);
            }
            $roles = implode(', ', array_map('ucfirst', $u->roles));
            
            // Prevent deleting yourself
            $is_self = ($u->ID === get_current_user_id());
            $delete_btn = '';
            if (!$is_self) {
                $delete_btn = sprintf(
                    '<button type="button" onclick="deleteStaffAccount(%d)" style="background:#EF4444; color:#FFF; border:none; padding:6px 12px; border-radius:6px; cursor:pointer; font-weight:700; font-size:11px;"><i class="fa-solid fa-trash"></i> %s</button>',
                    $u->ID, __('Delete', 'caretochina-staff')
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
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-staff')]);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized admin capability.', 'caretochina-staff')]);
        }
        $html = $this->generate_admin_staff_table_rows();
        wp_send_json_success(['html' => $html]);
    }

    public function handle_delete_staff_account() {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-staff')]);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized admin capability.', 'caretochina-staff')]);
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        if ($user_id > 0) {
            if ($user_id === get_current_user_id()) {
                wp_send_json_error(['message' => __('You cannot delete your own account.', 'caretochina-staff')]);
            }
            
            // Delete user in WordPress
            require_once ABSPATH . 'wp-admin/includes/user.php';
            $deleted = wp_delete_user($user_id);
            if ($deleted) {
                wp_send_json_success(['message' => __('Staff account deleted successfully.', 'caretochina-staff')]);
            } else {
                wp_send_json_error(['message' => __('Failed to delete user account.', 'caretochina-staff')]);
            }
        }
        wp_send_json_error(['message' => __('Invalid user ID.', 'caretochina-staff')]);
    }

    public function handle_admin_delete_patient_data() {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-staff')]);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized admin capability.', 'caretochina-staff')]);
        }

        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';
        $table_messages = $wpdb->prefix . 'caretochina_messages';

        $booking_id = intval($_POST['booking_id'] ?? 0);

        if ($booking_id > 0) {
            $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_bookings WHERE id = %d", $booking_id));
            if ($booking) {
                // Delete messages for this booking
                $wpdb->delete($table_messages, ['booking_id' => $booking_id]);

                // Delete booking itself
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
                                    @unlink($old_file_path);
                                }
                            }
                        }
                        require_once ABSPATH . 'wp-admin/includes/user.php';
                        wp_delete_user($patient_id);
                    }
                }
                wp_send_json_success(['message' => __('Patient and associated booking data deleted successfully.', 'caretochina-staff')]);
            }
        }
        wp_send_json_error(['message' => __('Failed to delete patient data.', 'caretochina-staff')]);
    }

    public function handle_staff_check_unread_updates() {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-staff')]);
        }
        $this->check_staff_capability();
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';
        $table_messages = $wpdb->prefix . 'caretochina_messages';

        // 1. Get total and pending bookings count
        $bookings_count = intval($wpdb->get_var("SELECT COUNT(*) FROM $table_bookings"));
        $pending_bookings_count = intval($wpdb->get_var("SELECT COUNT(*) FROM $table_bookings WHERE status = 'pending'"));
        $latest_booking = $wpdb->get_row("SELECT booking_code, full_name FROM $table_bookings ORDER BY id DESC LIMIT 1");

        // 2. Get unread messages count
        $unread_messages_count = intval($wpdb->get_var("SELECT COUNT(*) FROM $table_messages WHERE sender_type = 'patient' AND is_read = 0"));

        // 3. Get latest unread message from a patient
        $latest_message = $wpdb->get_row("
            SELECT m.*, b.booking_code, b.full_name as patient_name 
            FROM $table_messages m 
            JOIN $table_bookings b ON m.booking_id = b.id 
            WHERE m.sender_type = 'patient' AND m.is_read = 0 
            ORDER BY m.id DESC LIMIT 1
        ");

        $unread_items = [];
        
        // Fetch pending bookings list
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_bookings'") === $table_bookings) {
            $pending_list = $wpdb->get_results("SELECT id, booking_code, full_name, created_at FROM $table_bookings WHERE status = 'pending' ORDER BY id DESC LIMIT 5");
            foreach ($pending_list as $item) {
                $unread_items[] = [
                    'type' => 'booking',
                    'id' => intval($item->id),
                    'code' => $item->booking_code,
                    'name' => $item->full_name,
                    'title' => sprintf(__('New Booking: #%s (%s)', 'caretochina-staff'), $item->booking_code, $item->full_name),
                    'time' => human_time_diff(strtotime($item->created_at), current_time('timestamp')) . ' ago'
                ];
            }
        }

        // Fetch unread messages list
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_messages'") === $table_messages) {
            $unread_msg_list = $wpdb->get_results("
                SELECT m.id, m.booking_id, m.message, m.created_at, b.booking_code, b.full_name as patient_name 
                FROM $table_messages m 
                JOIN $table_bookings b ON m.booking_id = b.id 
                WHERE m.sender_type = 'patient' AND m.is_read = 0 
                ORDER BY m.id DESC LIMIT 5
            ");
            foreach ($unread_msg_list as $item) {
                $unread_items[] = [
                    'type' => 'message',
                    'id' => intval($item->booking_id),
                    'code' => $item->booking_code,
                    'name' => $item->patient_name,
                    'title' => sprintf(__('Msg from %s: "%s"', 'caretochina-staff'), $item->patient_name, wp_html_excerpt($item->message, 30)),
                    'time' => human_time_diff(strtotime($item->created_at), current_time('timestamp')) . ' ago'
                ];
            }
        }

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
            'unread_items' => $unread_items
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
                'title' => __('Care Staff Desk Notifications', 'caretochina-staff'),
            ]
        ]);

        if ($unread_count > 0) {
            global $wpdb;
            $table_bookings = $wpdb->prefix . 'caretochina_bookings';
            $table_messages = $wpdb->prefix . 'caretochina_messages';

            // 1. Fetch pending bookings
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_bookings'") === $table_bookings) {
                $pending_list = $wpdb->get_results("SELECT id, booking_code, full_name FROM $table_bookings WHERE status = 'pending' ORDER BY id DESC LIMIT 3");
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
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_messages'") === $table_messages) {
                $unread_msg_list = $wpdb->get_results("
                    SELECT m.id, m.booking_id, m.message, b.booking_code, b.full_name as patient_name 
                    FROM $table_messages m 
                    JOIN $table_bookings b ON m.booking_id = b.id 
                    WHERE m.sender_type = 'patient' AND m.is_read = 0 
                    ORDER BY m.id DESC LIMIT 3
                ");
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

    public function restrict_staff_admin_access() {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            $is_staff = (current_user_can('edit_posts') || in_array('medical_staff', (array)$current_user->roles));
            if ($is_staff && !current_user_can('manage_options')) {
                wp_redirect(home_url('/staff-portal/'));
                exit;
            }
        }
    }

    public function handle_get_booking_details() {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-staff')]);
        }
        $this->check_staff_capability();
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';
        
        $id = intval($_POST['booking_id'] ?? 0);
        if ($id > 0) {
            $b = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_bookings WHERE id = %d", $id));
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
        wp_send_json_error(['message' => __('Booking details not found.', 'caretochina-staff')]);
    }

    public function handle_toggle_restrict() {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'careyou_staff_nonce')) {
            wp_send_json_error(['message' => __('Invalid security nonce.', 'caretochina-staff')]);
        }
        $this->check_staff_capability();
        $patient_id = intval($_POST['patient_id'] ?? 0);
        $reason = sanitize_text_field($_POST['reason'] ?? '');
        
        if ($patient_id > 0) {
            $currently_restricted = get_user_meta($patient_id, 'patient_restricted', true) ? true : false;
            if ($currently_restricted) {
                delete_user_meta($patient_id, 'patient_restricted');
                delete_user_meta($patient_id, 'patient_restriction_reason');
                wp_send_json_success(['message' => __('Patient chat restriction has been removed.', 'caretochina-staff')]);
            } else {
                update_user_meta($patient_id, 'patient_restricted', 1);
                update_user_meta($patient_id, 'patient_restriction_reason', $reason ?: __('Violation of terms of service.', 'caretochina-staff'));
                wp_send_json_success(['message' => __('Patient has been restricted from live chat.', 'caretochina-staff')]);
            }
        }
        wp_send_json_error(['message' => __('Invalid patient ID.', 'caretochina-staff')]);
    }
}