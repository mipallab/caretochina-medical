<?php
if (!defined('ABSPATH')) {
    exit;
}

class CareToChina_Patient_Dashboard {
    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_shortcode('caretochina_patient_dashboard', [$this, 'render_dashboard']);
        add_shortcode('careyou_patient_dashboard', [$this, 'render_dashboard']); // Backward compatibility alias

        add_action('wp_ajax_caretochina_send_patient_message', [$this, 'handle_patient_message']);
        add_action('wp_ajax_nopriv_caretochina_send_patient_message', [$this, 'handle_patient_message']);
        add_action('wp_ajax_caretochina_get_patient_chat', [$this, 'handle_get_patient_chat']);
        add_action('wp_ajax_nopriv_caretochina_get_patient_chat', [$this, 'handle_get_patient_chat']);
        add_action('wp_ajax_caretochina_update_patient_profile', [$this, 'handle_update_patient_profile']);
        add_action('wp_ajax_caretochina_upload_patient_avatar', [$this, 'handle_patient_avatar_upload']);
        add_action('wp_ajax_caretochina_patient_send_typing', [$this, 'handle_patient_typing']);
        add_action('wp_ajax_nopriv_caretochina_patient_send_typing', [$this, 'handle_patient_typing']);
        add_action('wp_ajax_caretochina_patient_delete_own_account', [$this, 'handle_patient_delete_own_account']);
        add_action('wp_ajax_careyou_patient_delete_own_account', [$this, 'handle_patient_delete_own_account']);

        // Backward compatibility AJAX aliases
        add_action('wp_ajax_careyou_send_patient_message', [$this, 'handle_patient_message']);
        add_action('wp_ajax_nopriv_careyou_send_patient_message', [$this, 'handle_patient_message']);
        add_action('wp_ajax_careyou_get_patient_chat', [$this, 'handle_get_patient_chat']);
        add_action('wp_ajax_nopriv_careyou_get_patient_chat', [$this, 'handle_get_patient_chat']);
        add_action('wp_ajax_careyou_upload_patient_avatar', [$this, 'handle_patient_avatar_upload']);
        add_action('template_redirect', [$this, 'restrict_guest_access']);
    }

    /**
     * Resolve booking access for logged-in user or guest with valid token
     *
     * @param int $booking_id
     * @param string $raw_token
     * @param string $booking_code
     * @return object|false
     */
    public function resolve_booking_access($booking_id = 0, $raw_token = '', $booking_code = '') {
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';

        $booking = null;
        if ($booking_id > 0) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}caretochina_bookings WHERE id = %d", $booking_id));
        } elseif (!empty($booking_code)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}caretochina_bookings WHERE booking_code = %s", sanitize_text_field(wp_unslash($booking_code))));
        }

        if (!$booking) {
            return false;
        }

        // Case 1: Authenticated patient owns this case (by patient_id OR verified email)
        if (is_user_logged_in()) {
            $curr_user = wp_get_current_user();
            $curr_user_id = $curr_user->ID;
            $curr_user_email = strtolower($curr_user->user_email);

            if (($booking->patient_id > 0 && intval($booking->patient_id) === $curr_user_id) || 
                (!empty($booking->email) && strtolower($booking->email) === $curr_user_email)) {
                // Ensure patient_id is properly synced if it wasn't
                if (intval($booking->patient_id) !== $curr_user_id) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->update($table_bookings, ['patient_id' => $curr_user_id, 'is_guest' => 0, 'guest_token_hash' => ''], ['id' => $booking->id]);
                    $booking->patient_id = $curr_user_id;
                    $booking->is_guest = 0;
                }
                return $booking;
            }

            // Also permit staff / admin
            if (current_user_can('manage_options') || current_user_can('edit_posts') || current_user_can('medical_staff')) {
                return $booking;
            }
        }

        // Case 2: Guest booking verified via token
        if (intval($booking->patient_id) === 0 || intval($booking->is_guest) === 1) {
            if (empty($raw_token)) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $raw_token = isset($_REQUEST['guest_token']) ? sanitize_text_field(wp_unslash($_REQUEST['guest_token'])) : (isset($_REQUEST['token']) ? sanitize_text_field(wp_unslash($_REQUEST['token'])) : (isset($_COOKIE['ctc_guest_token']) ? sanitize_text_field(wp_unslash($_COOKIE['ctc_guest_token'])) : (isset($_COOKIE['ctc_active_guest_token']) ? sanitize_text_field(wp_unslash($_COOKIE['ctc_active_guest_token'])) : '')));
            }

            if (!empty($raw_token) && !empty($booking->guest_token_hash)) {
                $hash = hash('sha256', $raw_token);
                if (hash_equals($booking->guest_token_hash, $hash)) {
                    // Enforce admin-configurable guest token expiration
                    $expiry_days = intval(get_option('ctc_guest_token_expiry_days', 90));
                    if ($expiry_days > 0 && !empty($booking->created_at)) {
                        $created_time = strtotime($booking->created_at);
                        if (time() > ($created_time + ($expiry_days * 86400))) {
                            return false; // Session expired
                        }
                    }
                    return $booking;
                }
            }
        }

        return false;
    }

    public function restrict_guest_access() {
        if (!is_user_logged_in()) {
            $configured_id = class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_id('patient_dashboard') : 0;
            $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
            $is_dash_page = ($configured_id > 0 && is_page($configured_id)) || is_page('patient-dashboard') || strpos($request_uri, 'patient-dashboard') !== false;
            
            if ($is_dash_page) {
                // Check if visitor has valid guest chat access credentials
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $guest_booking_id = isset($_REQUEST['booking_id']) ? absint($_REQUEST['booking_id']) : 0;
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $guest_token = isset($_REQUEST['token']) ? sanitize_text_field(wp_unslash($_REQUEST['token'])) : (isset($_REQUEST['guest_token']) ? sanitize_text_field(wp_unslash($_REQUEST['guest_token'])) : '');
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $guest_booking_code = isset($_REQUEST['booking_code']) ? sanitize_text_field(wp_unslash($_REQUEST['booking_code'])) : '';

                $guest_booking = $this->resolve_booking_access(
                    $guest_booking_id,
                    $guest_token,
                    $guest_booking_code
                );

                // If not valid guest access, redirect to login
                if (!$guest_booking) {
                    wp_safe_redirect(home_url('/patient-login/'));
                    exit;
                }
            }
        }
    }

    public function render_dashboard() {
        ob_start();
        if (!is_user_logged_in()) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $raw_token = isset($_REQUEST['token']) ? sanitize_text_field(wp_unslash($_REQUEST['token'])) : (isset($_REQUEST['guest_token']) ? sanitize_text_field(wp_unslash($_REQUEST['guest_token'])) : (isset($_COOKIE['ctc_guest_token']) ? sanitize_text_field(wp_unslash($_COOKIE['ctc_guest_token'])) : (isset($_COOKIE['ctc_active_guest_token']) ? sanitize_text_field(wp_unslash($_COOKIE['ctc_active_guest_token'])) : '')));
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $dash_booking_id = isset($_REQUEST['booking_id']) ? absint($_REQUEST['booking_id']) : 0;
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $dash_booking_code = isset($_REQUEST['booking_code']) ? sanitize_text_field(wp_unslash($_REQUEST['booking_code'])) : '';

            $guest_booking = $this->resolve_booking_access(
                $dash_booking_id,
                $raw_token,
                $dash_booking_code
            );

            if ($guest_booking) {
                return $this->render_guest_dashboard($guest_booking, $raw_token);
            }

            echo '<script>window.location.href = "' . esc_js(home_url('/patient-login/')) . '";</script>';
            return ob_get_clean();
        }
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';

        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;
        $patient_id = $user_id;
        $email = $current_user->exists() ? $current_user->user_email : '';
        $display_name = $current_user->exists() ? $current_user->display_name : 'Patient';
        
        $phone = get_user_meta($user_id, 'patient_phone', true);
        $gender = get_user_meta($user_id, 'patient_gender', true);
        $age = get_user_meta($user_id, 'patient_age', true);
        $whatsapp = get_user_meta($user_id, 'patient_whatsapp', true);
        $wechat = get_user_meta($user_id, 'patient_wechat', true);
        $messenger = get_user_meta($user_id, 'patient_messenger', true);
        $linkedin = get_user_meta($user_id, 'patient_linkedin', true);

        // Resolve dynamic avatar URL
        $avatar_url = get_user_meta($user_id, 'patient_avatar', true);
        if (empty($avatar_url)) {
            $uploads = wp_get_upload_dir();
            $base_url = !empty($uploads['baseurl']) ? $uploads['baseurl'] : content_url('/uploads');
            if (strcasecmp($gender, 'Female') === 0) {
                $avatar_url = $base_url . '/2026/08/placeholder_female.webp';
            } else {
                $avatar_url = $base_url . '/2026/08/placeholder_male.webp';
            }
        }

        $bookings = [];
        if (is_user_logged_in() && !empty($email)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $bookings = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}caretochina_bookings WHERE patient_id = %d OR LOWER(email) = LOWER(%s) ORDER BY id DESC", $user_id, $email));
        }

        $active_booking = null;
        if (!empty($bookings)) {
            $active_booking = $bookings[0];
            // Synchronize all bookings for this email
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}caretochina_bookings SET patient_id = %d, is_guest = 0 WHERE LOWER(email) = LOWER(%s)", $user_id, $email));
            $stage = intval($active_booking->timeline_stage ?? 1);
            $stage_pct = min(100, max(20, $stage * 20));
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'overview';
        $valid_tabs = ['overview', 'invoices', 'account', 'milestones', 'messages', 'logout'];
        if (!in_array($active_tab, $valid_tabs, true)) {
            $active_tab = 'overview';
        }

        $logout_url = wp_logout_url(home_url('/patient-login/'));
        ?>
        <div class="careyou-dashboard-wrapper caretochina-dashboard-wrapper" data-booking-id="<?php echo esc_attr($active_booking ? $active_booking->id : 0); ?>">
            <!-- 1. DASHBOARD HEADER BANNER -->
            <div class="ctc-dash-banner">
                <div class="ctc-dash-banner-left">
                    <img src="<?php echo esc_url($avatar_url); ?>" alt="Patient Avatar" class="ctc-dash-avatar">
                    <div class="ctc-dash-banner-info">
                        <h2 class="ctc-dash-welcome-text"><?php
                            /* translators: %s: Patient display name */
                            printf(esc_html__('Welcome back, %s', 'caretochina-medical'), esc_html($display_name));
                            ?></h2>
                        <p class="ctc-dash-subtitle-text">
                            <span class="ctc-status-dot"></span> 
                            <?php if ($active_booking) : ?>
                                <?php esc_html_e('Care Case:', 'caretochina-medical'); ?> <strong>#<?php echo esc_html($active_booking->booking_code); ?></strong> 
                            <?php else : ?>
                                <?php esc_html_e('No Active Travel Case', 'caretochina-medical'); ?>
                            <?php endif; ?>
                            &nbsp;•&nbsp; <?php esc_html_e('Role:', 'caretochina-medical'); ?> <strong><?php esc_html_e('Patient', 'caretochina-medical'); ?></strong>
                        </p>
                    </div>
                </div>
                <div class="ctc-dash-banner-actions">
                    <!-- Theme Toggle Button -->
                    <button type="button" class="ctc-hdr-btn ctc-hdr-btn-glass" onclick="window.appToggleTheme()" style="width:42px; height:42px; border-radius:50%; padding:0; display:inline-flex; align-items:center; justify-content:center; cursor:pointer;" title="<?php esc_html_e('Toggle Dark/Light Mode', 'caretochina-medical'); ?>">
                        <i class="fa-solid fa-circle-half-stroke"></i>
                    </button>
                    <?php if ($active_booking) : ?>
                        <button type="button" class="ctc-hdr-btn ctc-hdr-btn-glass" onclick="appDash.switchTabDirect('messages')">
                            <i class="fa-solid fa-headset"></i> <?php esc_html_e('Care Coordinator Chat', 'caretochina-medical'); ?>
                        </button>
                    <?php endif; ?>
                    <a href="<?php echo esc_url($logout_url); ?>" class="ctc-hdr-btn ctc-hdr-btn-glass">
                        <i class="fa-solid fa-right-from-bracket"></i> <?php esc_html_e('Logout', 'caretochina-medical'); ?>
                    </a>
                </div>
            </div>

            <!-- 2. DASHBOARD CONTAINER GRID (SIDEBAR TABS + PANELS) -->
            <div class="ctc-dash-grid">
                <!-- SIDEBAR NAVIGATION TABS -->
                <div class="ctc-dash-sidebar">
                    <button type="button" class="ctc-sidebar-toggle-btn" onclick="appDash.toggleSidebar()"><i class="fa-solid fa-angles-left"></i></button>
                    <button type="button" class="ctc-sidebar-tab <?php echo ($active_tab === 'overview') ? 'active' : ''; ?>" onclick="appDash.switchTab(this, 'overview')">
                        <i class="fa-solid fa-chart-line"></i> <span><?php esc_html_e('Account Overview', 'caretochina-medical'); ?></span>
                    </button>
                    <button type="button" class="ctc-sidebar-tab <?php echo ($active_tab === 'invoices') ? 'active' : ''; ?>" onclick="appDash.switchTab(this, 'invoices')">
                        <i class="fa-solid fa-file-invoice-dollar"></i> <span><?php esc_html_e('Payment History', 'caretochina-medical'); ?></span>
                        <span id="patient-unread-invoice-badge" class="ctc-tab-unread-badge" style="display:none; background:#EF4444; color:#FFF; border-radius:999px; padding:2px 8px; font-size:11px; font-weight:800; margin-left:auto;"></span>
                    </button>
                    <button type="button" class="ctc-sidebar-tab <?php echo ($active_tab === 'account') ? 'active' : ''; ?>" onclick="appDash.switchTab(this, 'account')">
                        <i class="fa-solid fa-user-gear"></i> <span><?php esc_html_e('Account Settings', 'caretochina-medical'); ?></span>
                    </button>
                    <button type="button" class="ctc-sidebar-tab <?php echo ($active_tab === 'milestones') ? 'active' : ''; ?>" onclick="appDash.switchTab(this, 'milestones')">
                        <i class="fa-solid fa-list-check"></i> <span><?php esc_html_e('Treatment Timeline', 'caretochina-medical'); ?></span>
                    </button>
                    <button type="button" class="ctc-sidebar-tab <?php echo ($active_tab === 'messages') ? 'active' : ''; ?>" onclick="appDash.switchTab(this, 'messages')">
                        <i class="fa-solid fa-comments"></i> <span><?php esc_html_e('Coordinator Messages', 'caretochina-medical'); ?></span>
                        <span id="patient-unread-msg-badge" class="ctc-tab-unread-badge" style="display:none; background:#EF4444; color:#FFF; border-radius:999px; padding:2px 8px; font-size:11px; font-weight:800; margin-left:auto;"></span>
                    </button>
                    <button type="button" class="ctc-sidebar-tab tab-logout-item" onclick="appDash.switchTab(this, 'logout')">
                        <i class="fa-solid fa-right-from-bracket"></i> <span><?php esc_html_e('Log Out', 'caretochina-medical'); ?></span>
                    </button>
                </div>

                <!-- MAIN CONTENT PANELS -->
                <div class="ctc-dash-content">
                    
                    <!-- TAB 1: Account Overview -->
                    <div class="ctc-dash-panel <?php echo ($active_tab === 'overview') ? 'active' : ''; ?>" id="dash-panel-overview" style="display:<?php echo ($active_tab === 'overview') ? 'block' : 'none'; ?>;">
                        <?php if ($active_booking) : ?>
                            <!-- ACTION REQUIRED: DEPOSIT PAYMENT BANNER IF UNPAID -->
                            <?php if (in_array(strtolower($active_booking->status), ['pending']) && floatval($active_booking->amount) > 0) : ?>
                                <div class="ctc-deposit-action-banner">
                                    <div class="ctc-deposit-banner-left">
                                        <div class="ctc-deposit-icon-wrap">
                                            <i class="fa-solid fa-file-invoice-dollar"></i>
                                        </div>
                                        <div>
                                            <strong class="ctc-deposit-title"><?php esc_html_e('Booking Deposit Required', 'caretochina-medical'); ?></strong>
                                            <div class="ctc-deposit-desc"><?php 
                                                $active_curr = $active_booking->currency ?: 'USD';
                                                $active_sym = class_exists('CareToChina_Packages') ? CareToChina_Packages::get_currency_symbol($active_curr) : '$';
                                                /* translators: 1: Deposit amount, 2: Booking code */
                                                printf(esc_html__('Deposit of %1$s is pending for Case #%2$s. Pay now to lock in your appointment.', 'caretochina-medical'), esc_html($active_sym . number_format((float)$active_booking->amount, 2) . ' ' . $active_curr), esc_html($active_booking->booking_code)); 
                                            ?></div>
                                        </div>
                                    </div>
                                    <button type="button" onclick="CareToChinaPayment.openPaymentModal(<?php echo esc_attr($active_booking->id); ?>, <?php echo esc_attr($active_booking->amount); ?>, '<?php echo esc_js($active_booking->currency ?: 'USD'); ?>', '<?php echo esc_js($active_booking->specialty); ?>')" class="ctc-solid-btn btn-teal-primary ctc-deposit-btn">
                                        <i class="fa-solid fa-lock"></i> <?php esc_html_e('Pay Deposit Online', 'caretochina-medical'); ?>
                                    </button>
                                </div>
                            <?php endif; ?>

                            <!-- STAT CARDS GRID -->
                            <div class="ctc-stat-grid">
                                <div class="ctc-stat-card">
                                    <div class="ctc-stat-icon icon-teal"><i class="fa-solid fa-hospital"></i></div>
                                    <div class="ctc-stat-details">
                                        <h4 class="ctc-stat-val"><?php echo esc_html($active_booking->hospital_name); ?></h4>
                                        <span class="ctc-stat-lbl"><?php esc_html_e('Assigned Hospital', 'caretochina-medical'); ?></span>
                                    </div>
                                </div>
                                <div class="ctc-stat-card">
                                    <div class="ctc-stat-icon icon-green"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
                                    <div class="ctc-stat-details">
                                        <h4 class="ctc-stat-val"><?php echo esc_html($active_booking->specialty); ?></h4>
                                        <span class="ctc-stat-lbl"><?php esc_html_e('Specialty', 'caretochina-medical'); ?></span>
                                    </div>
                                </div>
                                <div class="ctc-stat-card">
                                    <div class="ctc-stat-icon icon-amber"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                                    <div class="ctc-stat-details">
                                        <h4 class="ctc-stat-val text-teal-accent"><?php echo esc_html($active_booking->invoice_status); ?></h4>
                                        <span class="ctc-stat-lbl"><?php esc_html_e('Payment status', 'caretochina-medical'); ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- PROGRESS BAR CARD -->
                            <div class="ctc-panel-card" style="margin-bottom:24px;">
                                <div class="ctc-card-header-row">
                                    <h3 class="ctc-card-title"><?php esc_html_e('Treatment Journey Progress', 'caretochina-medical'); ?></h3>
                                    <span class="ctc-progress-pct"><?php echo esc_html($stage_pct); ?>% <?php esc_html_e('Completed', 'caretochina-medical'); ?></span>
                                </div>
                                <div class="ctc-progress-track">
                                    <div class="ctc-progress-bar-fill" style="width: <?php echo esc_attr($stage_pct); ?>%;"></div>
                                </div>
                            </div>

                            <!-- BOOKINGS LIST TABLE CARD -->
                            <div class="ctc-panel-card">
                                <h3 class="ctc-card-title" style="margin-bottom:18px !important;"><?php esc_html_e('Active Medical Travel Bookings', 'caretochina-medical'); ?></h3>
                                <div class="ctc-table-responsive">
                                    <table class="ctc-custom-table">
                                        <thead>
                                             <tr>
                                                <th><?php esc_html_e('Case Code', 'caretochina-medical'); ?></th>
                                                <th><?php esc_html_e('Specialty', 'caretochina-medical'); ?></th>
                                                <th><?php esc_html_e('Hospital Preferred', 'caretochina-medical'); ?></th>
                                                <th><?php esc_html_e('Timing', 'caretochina-medical'); ?></th>
                                                <th><?php esc_html_e('Status', 'caretochina-medical'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($bookings as $b): ?>
                                                <tr>
                                                    <td class="ctc-td-code"><?php echo esc_html($b->booking_code); ?></td>
                                                    <td><?php echo esc_html($b->specialty); ?></td>
                                                    <td><?php echo esc_html($b->hospital_name); ?></td>
                                                    <td><?php echo esc_html($b->treatment_timing); ?></td>
                                                    <td><span class="ctc-badge-pill badge-success"><?php echo esc_html(strtoupper($b->status)); ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php else : ?>
                            <!-- GET A FREE QUOTE BANNER (No bookings found) -->
                            <div class="ctc-panel-card text-center ctc-no-bookings-card">
                                <div class="ctc-no-bookings-icon-wrap">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                                </div>
                                <h3 class="ctc-no-bookings-title"><?php esc_html_e('No Active Treatment Cases Found', 'caretochina-medical'); ?></h3>
                                <p class="ctc-no-bookings-desc"><?php esc_html_e('Connect with top JCI-certified hospitals in China. Start your personalized medical consultation roadmap today.', 'caretochina-medical'); ?></p>
                                <button type="button" class="ctc-solid-btn btn-teal-primary btn-lg ctc-no-bookings-btn" onclick="appWizard.openScenario1()"><i class="fa-solid fa-calendar-plus"></i> <?php esc_html_e('Request Free Quote & Plan Now', 'caretochina-medical'); ?></button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- TAB 2: PAYMENT HISTORY & BILLING INVOICES -->
                    <div class="ctc-dash-panel <?php echo ($active_tab === 'invoices') ? 'active' : ''; ?>" id="dash-panel-invoices" style="display:<?php echo ($active_tab === 'invoices') ? 'block' : 'none'; ?>;">
                        <div class="ctc-panel-card" style="margin-bottom: 24px !important;">
                            <h3 class="ctc-card-title" style="margin-bottom: 18px !important;"><?php esc_html_e('Payment History & Invoices', 'caretochina-medical'); ?></h3>
                            
                            <?php
                            global $wpdb;
                            $table_bookings = $wpdb->prefix . 'caretochina_bookings';
                            $table_requests = $wpdb->prefix . 'caretochina_payment_requests';

                            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                            $patient_bookings = ($patient_id > 0) ? $wpdb->get_results($wpdb->prepare(
                                "SELECT * FROM {$wpdb->prefix}caretochina_bookings WHERE patient_id = %d OR LOWER(email) = LOWER(%s) ORDER BY id DESC",
                                $patient_id,
                                $email
                            )) : [];

                            $booking_ids = wp_list_pluck($patient_bookings, 'id');
                            if (!empty($booking_ids)) {
                                $escaped_b_ids = implode(', ', array_map('intval', $booking_ids));
                                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                                $patient_requests = ($patient_id > 0) ? $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}caretochina_payment_requests WHERE patient_id = %d OR chat_thread_booking_id IN ($escaped_b_ids) ORDER BY id DESC", $patient_id)) : [];
                            } else {
                                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                                $patient_requests = ($patient_id > 0) ? $wpdb->get_results($wpdb->prepare(
                                    "SELECT * FROM {$wpdb->prefix}caretochina_payment_requests WHERE patient_id = %d ORDER BY id DESC",
                                    $patient_id
                                )) : [];
                            }

                            $total_paid = 0.00;
                            $paid_count = 0;
                            if (!empty($patient_bookings)) {
                                foreach ($patient_bookings as $pb) {
                                    if (in_array($pb->status, ['confirmed', 'paid'])) {
                                        $total_paid += floatval($pb->amount);
                                        $paid_count++;
                                    }
                                }
                            }
                            if (!empty($patient_requests)) {
                                foreach ($patient_requests as $pr) {
                                    if ($pr->status === 'accepted_paid') {
                                        $total_paid += floatval($pr->amount);
                                        $paid_count++;
                                    }
                                }
                            }

                            $has_records = (!empty($patient_bookings) || !empty($patient_requests));
                            ?>

                            <?php if ($has_records) : ?>
                                <div class="ctc-summary-grid" style="margin-bottom: 24px !important;">
                                    <div class="ctc-summary-box">
                                        <span class="ctc-summary-lbl"><?php esc_html_e('Total Completed Payments', 'caretochina-medical'); ?></span>
                                        <h3 class="ctc-summary-val" style="color:#0F766E;">$<?php echo esc_html(number_format($total_paid, 2)); ?></h3>
                                    </div>
                                    <div class="ctc-summary-box">
                                        <span class="ctc-summary-lbl"><?php esc_html_e('Confirmed Invoices', 'caretochina-medical'); ?></span>
                                        <h3 class="ctc-summary-val text-teal-accent" style="font-size:18px; font-weight:800;"><?php echo esc_html($paid_count); ?> <?php esc_html_e('Paid Case(s)', 'caretochina-medical'); ?></h3>
                                    </div>
                                </div>
                                <div class="ctc-table-responsive">
                                    <table class="ctc-custom-table">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e('Reference #', 'caretochina-medical'); ?></th>
                                                <th><?php esc_html_e('Treatment / Service', 'caretochina-medical'); ?></th>
                                                <th><?php esc_html_e('Amount', 'caretochina-medical'); ?></th>
                                                <th><?php esc_html_e('Type / Method', 'caretochina-medical'); ?></th>
                                                <th><?php esc_html_e('Status', 'caretochina-medical'); ?></th>
                                                <th><?php esc_html_e('Date', 'caretochina-medical'); ?></th>
                                                <th><?php esc_html_e('Action', 'caretochina-medical'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            // 1. Render custom payment requests
                                            if (!empty($patient_requests)) {
                                                foreach ($patient_requests as $pr) {
                                                    $pr_status = $pr->status;
                                                    $pr_badge_class = 'badge-pending';
                                                    $pr_badge_lbl = __('Payment Requested', 'caretochina-medical');

                                                    if ($pr_status === 'accepted_paid') {
                                                        $pr_badge_class = 'badge-success';
                                                        $pr_badge_lbl = __('Paid', 'caretochina-medical');
                                                    } elseif ($pr_status === 'cancelled') {
                                                        $pr_badge_class = 'badge-danger';
                                                        $pr_badge_lbl = __('Cancelled', 'caretochina-medical');
                                                    }
                                                    ?>
                                                    <tr>
                                                        <td class="ctc-td-code" data-label="<?php esc_attr_e('Case Code', 'caretochina-medical'); ?>">
                                                            <span style="font-weight:800; color:#0F766E;">#<?php echo esc_html($pr->request_code); ?></span>
                                                        </td>
                                                        <td data-label="<?php esc_attr_e('Service / Treatment', 'caretochina-medical'); ?>">
                                                            <strong><?php echo esc_html($pr->custom_title); ?></strong>
                                                            <br><span style="font-size:11px; color:#0F766E;"><i class="fa-solid fa-headset"></i> <?php esc_html_e('Staff Payment Request', 'caretochina-medical'); ?></span>
                                                        </td>
                                                        <td data-label="<?php esc_attr_e('Amount', 'caretochina-medical'); ?>" style="font-weight:700; color:#0F766E;">
                                                            <?php 
                                                             $pr_curr = $pr->currency ?: 'USD';
                                                             $pr_sym = class_exists('CareToChina_Packages') ? CareToChina_Packages::get_currency_symbol($pr_curr) : '$';
                                                             echo esc_html($pr_sym . number_format((float)$pr->amount, 2) . ' ' . $pr_curr); 
                                                            ?>
                                                        </td>
                                                        <td data-label="<?php esc_attr_e('Gateway', 'caretochina-medical'); ?>">
                                                            <?php if ($pr_status === 'accepted_paid') : ?>
                                                                <span style="font-weight:600; font-size:12px; color:#475569;"><i class="fa-solid fa-credit-card"></i> <?php echo esc_html($pr->gateway ?: 'Online Gateway'); ?></span>
                                                            <?php else : ?>
                                                                <span style="color:#94A3B8; font-size:13px;">—</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td data-label="<?php esc_attr_e('Status', 'caretochina-medical'); ?>"><span class="ctc-badge-pill <?php echo esc_attr($pr_badge_class); ?>"><?php echo esc_html($pr_badge_lbl); ?></span></td>
                                                        <td data-label="<?php esc_attr_e('Date', 'caretochina-medical'); ?>" style="font-size:12px; color:#64748B;"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($pr->created_at))); ?></td>
                                                        <td data-label="<?php esc_attr_e('Action', 'caretochina-medical'); ?>">
                                                            <?php if ($pr_status === 'pending' || $pr_status === 'processing') : ?>
                                                                <button type="button" onclick="ctcAcceptPaymentRequest(<?php echo esc_attr(intval($pr->id)); ?>)" class="ctc-btn-pay" style="background:#0F766E; color:#FFF; border:none; padding:6px 14px; border-radius:8px; font-size:12px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:6px;" aria-label="<?php esc_attr_e('Pay now for this medical service', 'caretochina-medical'); ?>">
                                                                    <i class="fa-solid fa-lock"></i> <?php esc_html_e('Pay Now', 'caretochina-medical'); ?>
                                                                </button>
                                                            <?php elseif ($pr_status === 'accepted_paid') : ?>
                                                                <button type="button" onclick="window.ctcOpenReceiptModal(<?php echo esc_attr(json_encode([
                                                                    'code'      => $pr->request_code,
                                                                    'name'      => $display_name,
                                                                    'email'     => $email,
                                                                    'specialty' => $pr->custom_title,
                                                                    'hospital'  => 'CareToChina Medical Services',
                                                                    'amount'    => number_format((float)$pr->amount, 2),
                                                                    'currency'  => $pr->currency ?: 'USD',
                                                                    'gateway'   => 'Online Checkout',
                                                                    'date'      => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($pr->updated_at ?: $pr->created_at)),
                                                                    'status'    => 'Paid in Full'
                                                                ])); ?>)" class="ctc-btn-receipt" style="background:#F0FDFA; color:#0F766E; border:1px solid #99F6E4; padding:6px 12px; border-radius:8px; font-size:12px; font-weight:700; cursor:pointer;" aria-label="<?php esc_attr_e('View receipt for this payment', 'caretochina-medical'); ?>">
                                                                    <i class="fa-solid fa-receipt"></i> <?php esc_html_e('View Receipt', 'caretochina-medical'); ?>
                                                                </button>
                                                            <?php else : ?>
                                                                <span style="color:#94A3B8; font-size:12px;">—</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <?php
                                                }
                                            }

                                            // 2. Render booking packages
                                            if (!empty($patient_bookings)) {
                                                foreach ($patient_bookings as $pb) {
                                                    $status = $pb->status;
                                                    $badge_class = 'badge-pending';
                                                    $badge_lbl = ucfirst($status);

                                                    if ($status === 'confirmed' || $status === 'paid') {
                                                        $badge_class = 'badge-success';
                                                        $badge_lbl = __('Paid', 'caretochina-medical');
                                                    } elseif ($status === 'refunded' || $status === 'refund_full') {
                                                        $badge_class = 'badge-danger';
                                                        $badge_lbl = __('Refunded', 'caretochina-medical');
                                                    } elseif ($status === 'partially_refunded' || $status === 'refund_partial') {
                                                        $badge_class = 'badge-warning';
                                                        $badge_lbl = __('Partial Refund', 'caretochina-medical');
                                                    } elseif ($status === 'payment_failed') {
                                                        $badge_class = 'badge-danger';
                                                        $badge_lbl = __('Failed', 'caretochina-medical');
                                                    }
                                                    ?>
                                                    <tr>
                                                        <td class="ctc-td-code" data-label="<?php esc_attr_e('Booking Code', 'caretochina-medical'); ?>">#<?php echo esc_html($pb->booking_code); ?></td>
                                                        <td data-label="<?php esc_attr_e('Specialty & Hospital', 'caretochina-medical'); ?>">
                                                            <strong><?php echo esc_html($pb->specialty); ?></strong>
                                                            <?php if (!empty($pb->hospital_name)) : ?>
                                                                <br><span style="font-size:11px; color:#64748B;"><?php echo esc_html($pb->hospital_name); ?></span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td data-label="<?php esc_attr_e('Amount', 'caretochina-medical'); ?>" style="font-weight:700; color:#0F766E;">
                                                            <?php 
                                                            $pb_curr = $pb->currency ?: 'USD';
                                                            $pb_sym = class_exists('CareToChina_Packages') ? CareToChina_Packages::get_currency_symbol($pb_curr) : '$';
                                                            echo esc_html($pb_sym . number_format((float)$pb->amount, 2) . ' ' . $pb_curr); 
                                                            ?>
                                                        </td>
                                                        <td data-label="<?php esc_attr_e('Payment Gateway', 'caretochina-medical'); ?>">
                                                            <?php 
                                                            $is_paid_status = in_array(strtolower($status), ['confirmed', 'paid', 'completed', 'refunded', 'partially_refunded']);
                                                            if ($is_paid_status && !empty($pb->payment_gateway)) {
                                                                if (strtolower($pb->payment_gateway) === 'stripe') {
                                                                    echo '<span style="color:#635BFF; font-weight:700;"><i class="fa-brands fa-stripe"></i> Stripe</span>';
                                                                } elseif (strtolower($pb->payment_gateway) === 'paypal') {
                                                                    echo '<span style="color:#003087; font-weight:700;"><i class="fa-brands fa-paypal"></i> PayPal</span>';
                                                                } else {
                                                                    echo '<span style="font-weight:700; color:#0F766E;">' . esc_html(ucfirst($pb->payment_gateway)) . '</span>';
                                                                }
                                                            } else {
                                                                echo '<span style="color:#94A3B8; font-size:13px;">—</span>';
                                                            }
                                                            ?>
                                                        </td>
                                                        <td data-label="<?php esc_attr_e('Status', 'caretochina-medical'); ?>"><span class="ctc-badge-pill <?php echo esc_attr($badge_class); ?>"><?php echo esc_html($badge_lbl); ?></span></td>
                                                        <td data-label="<?php esc_attr_e('Date', 'caretochina-medical'); ?>" style="font-size:12px; color:#64748B;"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($pb->created_at))); ?></td>
                                                        <td data-label="<?php esc_attr_e('Action', 'caretochina-medical'); ?>">
                                                            <?php if ($status === 'confirmed' || $status === 'paid' || $status === 'refunded' || $status === 'partially_refunded') : ?>
                                                                <button type="button" onclick="window.ctcOpenReceiptModal(<?php echo esc_attr(json_encode([
                                                                    'code'      => $pb->booking_code,
                                                                    'name'      => $pb->full_name,
                                                                    'email'     => $pb->email,
                                                                    'specialty' => $pb->specialty,
                                                                    'hospital'  => $pb->hospital_name,
                                                                    'amount'    => number_format((float)$pb->amount, 2),
                                                                    'currency'  => $pb->currency ?: 'USD',
                                                                    'gateway'   => ucfirst($pb->payment_gateway ?: 'Online'),
                                                                    'date'      => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($pb->paid_at ?: $pb->created_at)),
                                                                    'status'    => $badge_lbl
                                                                ])); ?>)" class="ctc-btn-receipt" style="background:#F0FDFA; color:#0F766E; border:1px solid #99F6E4; padding:6px 12px; border-radius:8px; font-size:12px; font-weight:700; cursor:pointer;" aria-label="<?php esc_attr_e('View receipt for this booking', 'caretochina-medical'); ?>">
                                                                    <i class="fa-solid fa-receipt"></i> <?php esc_html_e('View Receipt', 'caretochina-medical'); ?>
                                                                </button>
                                                            <?php elseif ($status === 'pending' && floatval($pb->amount) > 0) : ?>
                                                                <button type="button" onclick="CareToChinaPayment.openPaymentModal(<?php echo esc_attr(intval($pb->id)); ?>, <?php echo esc_attr(floatval($pb->amount)); ?>, '<?php echo esc_js($pb->currency ?: 'USD'); ?>', '<?php echo esc_js($pb->specialty); ?>')" class="ctc-btn-pay" style="background:#0F766E; color:#FFF; border:none; padding:6px 14px; border-radius:8px; font-size:12px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:6px;" aria-label="<?php esc_attr_e('Pay deposit for this booking', 'caretochina-medical'); ?>">
                                                                    <i class="fa-solid fa-lock"></i> <?php esc_html_e('Pay Now', 'caretochina-medical'); ?>
                                                                </button>
                                                            <?php else : ?>
                                                                <span style="color:#94A3B8; font-size:12px;">—</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <?php
                                                }
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else : ?>
                                <div style="text-align:center; padding:40px 20px; color:#64748B;">
                                    <i class="fa-solid fa-file-invoice-dollar" style="font-size:40px; color:#CBD5E1; margin-bottom:12px;"></i>
                                    <p><?php esc_html_e('No payment history available yet. Once you complete a booking or accept a clinic payment request, your invoices will appear here.', 'caretochina-medical'); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- TAB 3: ACCOUNT SETTINGS (PREMIUM STYLED FORM) -->
                    <div class="ctc-dash-panel <?php echo ($active_tab === 'account') ? 'active' : ''; ?>" id="dash-panel-account" style="display:<?php echo ($active_tab === 'account') ? 'block' : 'none'; ?>;">
                        <div class="ctc-panel-card ctc-profile-card">
                            <div class="ctc-profile-header" style="display: flex; margin-bottom: 40px !important; align-items: center; gap: 20px; flex-wrap: wrap;">
                                <div class="ctc-profile-badge-avatar" style="width: 80px; height: 80px; border-radius: 50%; border: 3px solid #14B8A6; overflow: hidden; position: relative; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                                    <img src="<?php echo esc_url($avatar_url); ?>" alt="Patient Avatar" class="ctc-profile-avatar-img" style="width: 100%; height: 100%; object-fit: cover;">
                                    <div class="ctc-avatar-upload-overlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s; color: #fff;">
                                        <i class="fa-solid fa-camera" style="font-size: 20px;"></i>
                                    </div>
                                </div>
                                <input type="file" id="ctc-avatar-file-input" style="display: none;" accept="image/png, image/jpeg, image/jpg, image/webp">
                                <div>
                                    <h3 class="ctc-card-title"><?php esc_html_e('Patient Account Profile & Settings', 'caretochina-medical'); ?></h3>
                                    <p class="ctc-card-subtitle" style="margin: 0 0 6px 0;"><?php esc_html_e('Manage your patient credentials, contact phone, and profile photo.', 'caretochina-medical'); ?></p>
                                    <button type="button" class="ctc-change-avatar-btn" style="background: none; border: none; color: #0F766E; font-weight: 700; font-size: 13px; cursor: pointer; padding: 0; text-decoration: underline; display: flex; align-items: center; gap: 6px;">
                                        <i class="fa-solid fa-upload"></i> <?php esc_html_e('Upload Photo (PNG, JPG, WEBP - Max 2MB)', 'caretochina-medical'); ?>
                                    </button>
                                    <span id="avatar-upload-status" style="display: none; font-size: 13px; font-weight: 600; margin-left: 10px;"></span>
                                </div>
                            </div>
                            <form id="patient-profile-form">
                                <div class="ctc-form-grid-2" style="margin-bottom: 20px !important;">
                                    <div class="form-group">
                                        <label class="form-label"><i class="fa-solid fa-user"></i> <?php esc_html_e('Full Name *', 'caretochina-medical'); ?></label>
                                        <input type="text" name="display_name" class="form-input" value="<?php echo esc_attr($display_name); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label"><i class="fa-solid fa-phone"></i> <?php esc_html_e('Phone Number *', 'caretochina-medical'); ?></label>
                                        <?php 
                                        if (class_exists('CareToChina_Country_Helper')) { 
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped HTML from CareToChina_Country_Helper.
                                            echo CareToChina_Country_Helper::render_phone_input_group('phone', $phone, true, '+1 (800) 555-0199', 'profile_phone'); 
                                        } else { 
                                            echo '<input type="tel" name="phone" id="profile_phone" class="form-input" value="' . esc_attr($phone) . '" required>'; 
                                        } 
                                        ?>
                                    </div>
                                </div>

                                <div class="ctc-form-grid-2" style="margin-bottom: 20px !important;">
                                    <div class="form-group">
                                        <label class="form-label"><i class="fa-solid fa-calendar-day"></i> <?php esc_html_e('Age', 'caretochina-medical'); ?></label>
                                        <input type="number" name="age" class="form-input" value="<?php echo esc_attr($age); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label"><i class="fa-solid fa-venus-mars"></i> <?php esc_html_e('Gender *', 'caretochina-medical'); ?></label>
                                        <select name="gender" class="form-select" style="background:var(--cymb-bg-card);" required>
                                            <option value=""><?php esc_html_e('Select Gender', 'caretochina-medical'); ?></option>
                                            <option value="Male" <?php selected($gender, 'Male'); ?>><?php esc_html_e('Male', 'caretochina-medical'); ?></option>
                                            <option value="Female" <?php selected($gender, 'Female'); ?>><?php esc_html_e('Female', 'caretochina-medical'); ?></option>
                                        </select>
                                    </div>
                                </div>

                                <h4 style="margin: 20px 0 16px 0; font-family:'Manrope'; font-size:15px; border: 1px solid transparent; border-bottom: 1px solid var(--cymb-border-color); padding-bottom: 6px; color: var(--cymb-text-dark);"><?php esc_html_e('Social Accounts (Optional)', 'caretochina-medical'); ?></h4>

                                <div class="ctc-form-grid-2" style="margin-bottom: 20px !important;">
                                    <div class="form-group">
                                        <label class="form-label"><i class="fa-brands fa-whatsapp"></i> <?php esc_html_e('WhatsApp', 'caretochina-medical'); ?></label>
                                        <?php 
                                        if (class_exists('CareToChina_Country_Helper')) { 
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped HTML from CareToChina_Country_Helper.
                                            echo CareToChina_Country_Helper::render_phone_input_group('whatsapp', $whatsapp, false, '+1 (800) 555-0199', 'profile_whatsapp'); 
                                        } else { 
                                            echo '<input type="tel" name="whatsapp" id="profile_whatsapp" class="form-input" value="' . esc_attr($whatsapp) . '" placeholder="+1 (800) 555-0199">'; 
                                        } 
                                        ?>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label"><i class="fa-brands fa-weixin"></i> <?php esc_html_e('WeChat ID', 'caretochina-medical'); ?></label>
                                        <input type="text" name="wechat" class="form-input" value="<?php echo esc_attr($wechat); ?>" placeholder="WeChat Username">
                                    </div>
                                </div>

                                <div class="ctc-form-grid-2" style="margin-bottom: 20px !important;">
                                    <div class="form-group">
                                        <label class="form-label"><i class="fa-brands fa-facebook-messenger"></i> <?php esc_html_e('Messenger', 'caretochina-medical'); ?></label>
                                        <input type="text" name="messenger" class="form-input" value="<?php echo esc_attr($messenger); ?>" placeholder="Messenger ID">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label"><i class="fa-brands fa-linkedin"></i> <?php esc_html_e('LinkedIn', 'caretochina-medical'); ?></label>
                                        <input type="text" name="linkedin" class="form-input" value="<?php echo esc_attr($linkedin); ?>" placeholder="LinkedIn Profile URL">
                                    </div>
                                </div>

                                <div class="ctc-form-grid-2" style="margin-bottom: 24px !important;">
                                    <div class="form-group">
                                        <label class="form-label"><i class="fa-solid fa-envelope"></i> <?php esc_html_e('Email Address (Account ID)', 'caretochina-medical'); ?></label>
                                        <input type="email" class="form-input" value="<?php echo esc_attr($email); ?>" disabled style="opacity:0.7; cursor:not-allowed; background:var(--cymb-bg-light);">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label"><i class="fa-solid fa-id-badge"></i> <?php esc_html_e('Account Role', 'caretochina-medical'); ?></label>
                                        <input type="text" class="form-input" value="Patient Account (Patient)" disabled style="opacity:0.7; cursor:not-allowed; background:var(--cymb-bg-light);">
                                    </div>
                                </div>

                                <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
                                    <button type="submit" id="save_profile_btn" class="ctc-solid-btn btn-teal-primary">
                                        <i class="fa-solid fa-floppy-disk"></i> <?php esc_html_e('Save Profile Changes', 'caretochina-medical'); ?>
                                    </button>
                                    <button type="button" id="delete_own_profile_btn" class="ctc-solid-btn btn-danger-solid" style="background:#EF4444; border-color:#EF4444; color:#FFF;">
                                        <i class="fa-solid fa-trash-can"></i> <?php esc_html_e('Delete My Account', 'caretochina-medical'); ?>
                                    </button>
                                    <div id="profile-response-box" class="ctc-response-msg" style="display:none;"></div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- TAB 4: TIMELINE -->
                    <div class="ctc-dash-panel <?php echo ($active_tab === 'milestones') ? 'active' : ''; ?>" id="dash-panel-milestones" style="display:<?php echo ($active_tab === 'milestones') ? 'block' : 'none'; ?>;">
                        <div class="ctc-panel-card">
                            <h3 class="ctc-card-title" style="margin-bottom: 24px !important;"><?php esc_html_e('Treatment Roadmap Milestones', 'caretochina-medical'); ?></h3>
                            
                            <?php if ($active_booking) : ?>
                                <div class="ctc-timeline-vertical">
                                    <div class="ctc-timeline-step <?php echo ($stage >= 1) ? 'is-complete' : ''; ?>">
                                        <div class="ctc-timeline-icon">
                                            <i class="fa-solid <?php echo ($stage >= 1) ? 'fa-circle-check' : 'fa-circle'; ?>"></i>
                                        </div>
                                        <div class="ctc-timeline-content">
                                            <h4 class="ctc-timeline-heading"><?php esc_html_e('Stage 1: Medical Assessment Completed', 'caretochina-medical'); ?></h4>
                                            <p class="ctc-timeline-desc"><?php esc_html_e('Report matching & surgeon video call completed.', 'caretochina-medical'); ?></p>
                                        </div>
                                    </div>
                                    <div class="ctc-timeline-step <?php echo ($stage >= 2) ? 'is-complete' : ''; ?>">
                                        <div class="ctc-timeline-icon">
                                            <i class="fa-solid <?php echo ($stage >= 2) ? 'fa-circle-check' : 'fa-circle'; ?>"></i>
                                        </div>
                                        <div class="ctc-timeline-content">
                                            <h4 class="ctc-timeline-heading"><?php esc_html_e('Stage 2: Hospital Guarantee & Embassy Visa Issued', 'caretochina-medical'); ?></h4>
                                            <p class="ctc-timeline-desc"><?php esc_html_e('Embassy confirmation and invitation letter generated.', 'caretochina-medical'); ?></p>
                                        </div>
                                    </div>
                                    <div class="ctc-timeline-step <?php echo ($stage >= 3) ? 'is-complete' : ''; ?>">
                                        <div class="ctc-timeline-icon">
                                            <i class="fa-solid <?php echo ($stage >= 3) ? 'fa-circle-check' : 'fa-circle'; ?>"></i>
                                        </div>
                                        <div class="ctc-timeline-content">
                                            <h4 class="ctc-timeline-heading"><?php esc_html_e('Stage 3: Airport Arrival & Chauffeur Transfer', 'caretochina-medical'); ?></h4>
                                            <p class="ctc-timeline-desc"><?php esc_html_e('Coordinator meets patient at Beijing/Shanghai airport.', 'caretochina-medical'); ?></p>
                                        </div>
                                    </div>
                                    <div class="ctc-timeline-step <?php echo ($stage >= 4) ? 'is-complete' : ''; ?>">
                                        <div class="ctc-timeline-icon">
                                            <i class="fa-solid <?php echo ($stage >= 4) ? 'fa-circle-check' : 'fa-circle'; ?>"></i>
                                        </div>
                                        <div class="ctc-timeline-content">
                                            <h4 class="ctc-timeline-heading"><?php esc_html_e('Stage 4: Surgical Procedure at Partner Hospital', 'caretochina-medical'); ?></h4>
                                            <p class="ctc-timeline-desc"><?php esc_html_e('In-patient stay and operations conducted by expert surgeons.', 'caretochina-medical'); ?></p>
                                        </div>
                                    </div>
                                    <div class="ctc-timeline-step <?php echo ($stage >= 5) ? 'is-complete' : ''; ?>">
                                        <div class="ctc-timeline-icon">
                                            <i class="fa-solid <?php echo ($stage >= 5) ? 'fa-circle-check' : 'fa-circle'; ?>"></i>
                                        </div>
                                        <div class="ctc-timeline-content">
                                            <h4 class="ctc-timeline-heading"><?php esc_html_e('Stage 5: Post-Op Recovery & Lifetime Telehealth', 'caretochina-medical'); ?></h4>
                                            <p class="ctc-timeline-desc"><?php esc_html_e('12 months post-surgery virtual consultation package.', 'caretochina-medical'); ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php else : ?>
                                <div style="text-align:center; padding:40px 20px; color:#64748B;">
                                    <i class="fa-solid fa-route" style="font-size:40px; color:#CBD5E1; margin-bottom:12px;"></i>
                                    <p><?php esc_html_e('Timeline awaiting booking submission. Request a quote to activate your roadmap.', 'caretochina-medical'); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>                    <!-- TAB 5: MESSAGES (PREVIEWING DELIVERED & SEEN TICK FEATURES) -->
                    <div class="ctc-dash-panel <?php echo ($active_tab === 'messages') ? 'active' : ''; ?>" id="dash-panel-messages" style="display:<?php echo ($active_tab === 'messages') ? 'block' : 'none'; ?>;">
                        <div class="ctc-panel-card">
                            <div class="ctc-card-header-row" style="margin-bottom:18px !important;">
                                <h3 class="ctc-card-title"><?php esc_html_e('Care Coordinator Live Chat', 'caretochina-medical'); ?></h3>
                                <span class="ctc-sync-indicator"><i class="fa-solid fa-circle" style="font-size:8px;"></i> <?php esc_html_e('Live Synchronized', 'caretochina-medical'); ?></span>
                            </div>

                            <?php if ($active_booking) : ?>
                                <div id="patient-chat-box" class="ctc-chat-viewport">
                                    <!-- Populated dynamically by JS AJAX Polling -->
                                </div>
                                <div id="patient-chat-typing-indicator" class="ctc-chat-typing-indicator" style="display:none;"></div>

                                <?php
                                $is_restricted = get_user_meta($user_id, 'patient_restricted', true) ? true : false;
                                $restriction_reason = get_user_meta($user_id, 'patient_restriction_reason', true);
                                ?>

                                <?php if ($is_restricted) : ?>
                                    <div class="ctc-chat-restricted-banner">
                                        <i class="fa-solid fa-ban"></i>
                                        <?php
                                        /* translators: %s: Restriction reason */
                                        printf(wp_kses(__('Your live chat feature has been restricted by the administrator. Reason: %s', 'caretochina-medical'), ['em' => []]), '<em>' . esc_html($restriction_reason ?: __('Violation of portal guidelines.', 'caretochina-medical')) . '</em>');
                                        ?><br>
                                        <span class="ctc-restricted-contact">
                                            <?php
                                            /* translators: %s: Admin email */
                                            printf(wp_kses(__('Please contact us at %s to resolve this issue and restore your chat access.', 'caretochina-medical'), ['strong' => []]), '<strong>' . esc_html(get_option('admin_email')) . '</strong>');
                                            ?>
                                        </span>
                                    </div>
                                <?php else : ?>
                                    <form id="patient-message-form" class="ctc-chat-form" enctype="multipart/form-data">
                                        <input type="hidden" name="booking_id" value="<?php echo esc_attr($active_booking->id); ?>">
                                        
                                        <div id="patient_attachment_preview" class="ctc-attachment-preview-box" style="display:none;">
                                            <span id="patient_attachment_name" class="ctc-attachment-name"></span>
                                            <button type="button" onclick="appChat.clearAttachment()" class="ctc-attachment-clear-btn" aria-label="<?php esc_attr_e('Remove attached file', 'caretochina-medical'); ?>" role="button">&times;</button>
                                        </div>

                                        <div class="ctc-chat-input-bar">
                                            <label for="patient_chat_file_input" class="ctc-chat-attach-btn" title="<?php esc_attr_e('Attach Image or PDF (Max 2MB)', 'caretochina-medical'); ?>" aria-label="<?php esc_attr_e('Attach medical report or image (Max 2MB)', 'caretochina-medical'); ?>" role="button">
                                                <i class="fa-solid fa-paperclip"></i>
                                            </label>
                                            <input type="file" id="patient_chat_file_input" name="attachment" accept="image/jpeg,image/png,image/webp,image/gif,application/pdf" style="display:none;" onchange="appChat.handleFileSelected(this)">
                                            <input type="text" name="message" id="patient_msg_input" class="ctc-chat-text-input" placeholder="<?php esc_html_e('Type a message to your coordinator...', 'caretochina-medical'); ?>" autocomplete="off">
                                            <button type="submit" id="patient_send_btn" class="ctc-solid-btn btn-teal-primary ctc-chat-send-btn" aria-label="<?php esc_attr_e('Send message', 'caretochina-medical'); ?>">
                                                <i class="fa-solid fa-paper-plane"></i>
                                                <span class="ctc-btn-send-text"><?php esc_html_e('Send', 'caretochina-medical'); ?></span>
                                            </button>
                                        </div>
                                        <div class="ctc-chat-allowed-hint">
                                            <?php esc_html_e('Allowed: Images & PDFs (Max 2MB)', 'caretochina-medical'); ?>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            <?php else : ?>
                                <div class="text-center ctc-chat-empty-state">
                                    <i class="fa-solid fa-comments"></i>
                                    <p><?php esc_html_e('Please request a treatment package consultation to start a chat with our coordinators.', 'caretochina-medical'); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- TAB 6: LOGOUT -->
                    <div class="ctc-dash-panel <?php echo ($active_tab === 'logout') ? 'active' : ''; ?>" id="dash-panel-logout" style="display:<?php echo ($active_tab === 'logout') ? 'block' : 'none'; ?>;">
                        <div class="ctc-panel-card ctc-logout-card">
                            <div class="ctc-logout-icon-wrap">
                                <i class="fa-solid fa-right-from-bracket"></i>
                            </div>
                            <h3 class="ctc-card-title" style="margin-bottom:10px;"><?php esc_html_e('Sign Out of Patient Account', 'caretochina-medical'); ?></h3>
                            <p class="ctc-logout-desc" style="margin-bottom:24px;"><?php esc_html_e('Are you sure you want to log out of your CareToChina patient portal?', 'caretochina-medical'); ?></p>
                            <a href="<?php echo esc_url($logout_url); ?>" class="ctc-solid-btn btn-danger-solid">
                                <i class="fa-solid fa-right-from-bracket"></i> <?php esc_html_e('Log Out Now', 'caretochina-medical'); ?>
                            </a>
                        </div>
                    </div>

                </div>
            </div>

            <!-- PRINTABLE RECEIPT MODAL -->
            <div id="patient-receipt-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.7); z-index:999999; align-items:center; justify-content:center; padding:20px; box-sizing:border-box;">
                <div style="background:#FFF; border-radius:20px; max-width:540px; width:100%; padding:32px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); font-family:'Manrope', sans-serif; position:relative; max-height:90vh; overflow-y:auto;" id="ctc-printable-receipt-area">
                    <div style="text-align:center; margin-bottom:24px; border-bottom:2px dashed #E2E8F0; padding-bottom:20px;">
                        <div style="display:inline-flex; width:52px; height:52px; border-radius:50%; background:#CCFBF1; color:#0F766E; align-items:center; justify-content:center; font-size:22px; margin-bottom:10px;">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                        <h2 style="margin:0 0 4px 0; color:#0F172A; font-size:20px; font-weight:800;"><?php esc_html_e('CareToChina Medical Services', 'caretochina-medical'); ?></h2>
                        <p style="margin:0; color:#64748B; font-size:13px;"><?php esc_html_e('Official Payment Receipt & Confirmation', 'caretochina-medical'); ?></p>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:20px; font-size:13px;">
                        <div>
                            <span style="color:#64748B; font-size:11px; text-transform:uppercase; font-weight:700;"><?php esc_html_e('Receipt No:', 'caretochina-medical'); ?></span>
                            <div id="rcpt-number" style="font-weight:700; color:#0F172A;">—</div>
                        </div>
                        <div>
                            <span style="color:#64748B; font-size:11px; text-transform:uppercase; font-weight:700;"><?php esc_html_e('Payment Date:', 'caretochina-medical'); ?></span>
                            <div id="rcpt-date" style="font-weight:600; color:#1E293B;">—</div>
                        </div>
                        <div>
                            <span style="color:#64748B; font-size:11px; text-transform:uppercase; font-weight:700;"><?php esc_html_e('Patient Name:', 'caretochina-medical'); ?></span>
                            <div id="rcpt-patient" style="font-weight:600; color:#1E293B;">—</div>
                        </div>
                        <div>
                            <span style="color:#64748B; font-size:11px; text-transform:uppercase; font-weight:700;"><?php esc_html_e('Payment Gateway:', 'caretochina-medical'); ?></span>
                            <div id="rcpt-gateway" style="font-weight:600; color:#1E293B;">—</div>
                        </div>
                    </div>

                    <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:12px; padding:16px; margin-bottom:20px;">
                        <span style="color:#64748B; font-size:11px; text-transform:uppercase; font-weight:700; display:block; margin-bottom:4px;"><?php esc_html_e('Medical Service & Facility:', 'caretochina-medical'); ?></span>
                        <div id="rcpt-service" style="font-weight:700; color:#0F172A; font-size:14px; margin-bottom:2px;">—</div>
                        <div id="rcpt-hospital" style="font-size:12px; color:#64748B;">—</div>
                    </div>

                    <div style="border-top:2px solid #0F172A; padding-top:14px; display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
                        <span style="font-size:15px; font-weight:800; color:#0F172A;"><?php esc_html_e('Total Amount Paid:', 'caretochina-medical'); ?></span>
                        <span id="rcpt-amount" style="font-size:22px; font-weight:900; color:#0F766E;">$0.00 USD</span>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:10px;" class="ctc-receipt-actions">
                        <button type="button" onclick="jQuery('#patient-receipt-modal').hide()" class="ctc-solid-btn" style="background:#F1F5F9; color:#475569; padding:10px 18px; border-radius:10px; border:none; cursor:pointer; font-weight:600; font-size:13px;"><?php esc_html_e('Close', 'caretochina-medical'); ?></button>
                        <button type="button" onclick="window.print()" class="ctc-solid-btn btn-teal-primary" style="padding:10px 20px; border-radius:10px; cursor:pointer; font-weight:700; font-size:13px;">
                            <i class="fa-solid fa-print"></i> <?php esc_html_e('Print Official Receipt', 'caretochina-medical'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        if (typeof appDash === 'undefined') {
            var appDash = {
                switchTab: function(btn, tabId) {
                    jQuery('.ctc-sidebar-tab').removeClass('active');
                    jQuery(btn).addClass('active');

                    jQuery('.ctc-dash-panel').removeClass('active').hide();
                    jQuery('#dash-panel-' + tabId).addClass('active').show();
                },
                switchTabDirect: function(tabId) {
                    jQuery('.ctc-sidebar-tab').removeClass('active');
                    jQuery('.ctc-sidebar-tab:nth-child(5)').addClass('active');

                    jQuery('.ctc-dash-panel').removeClass('active').hide();
                    jQuery('#dash-panel-' + tabId).addClass('active').show();
                }
            };
        }
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Dedicated Guest Consultation Live Chat View
     */
    public function render_guest_dashboard($guest_booking, $raw_token) {
        ob_start();
        $base_auth_url = class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_url('patient_login') : home_url('/patient-login/');
        $register_url = add_query_arg([
            'tab'               => 'register',
            'prefill_name'      => $guest_booking->full_name,
            'prefill_email'     => $guest_booking->email,
            'prefill_phone'     => $guest_booking->phone,
            'prefill_gender'    => $guest_booking->gender,
            'prefill_age'       => $guest_booking->age,
            'prefill_whatsapp'  => $guest_booking->whatsapp,
            'prefill_wechat'    => $guest_booking->wechat,
            'prefill_specialty' => $guest_booking->specialty,
            'booking_code'      => $guest_booking->booking_code,
        ], $base_auth_url);

        $brand_logo_url = get_option('ctc_brand_logo_url', '');
        $expiry_days = intval(get_option('ctc_guest_token_expiry_days', 90));
        $created_time = !empty($guest_booking->created_at) ? strtotime($guest_booking->created_at) : time();
        $expiry_timestamp = $created_time + ($expiry_days * 86400);
        $remaining_secs = max(0, $expiry_timestamp - time());
        ?>
        <div class="careyou-dashboard-wrapper caretochina-dashboard-wrapper caretochina-guest-dashboard" data-booking-id="<?php echo esc_attr($guest_booking->id); ?>" data-is-guest="1" data-guest-token="<?php echo esc_attr($raw_token); ?>">
            <!-- GUEST CONSULTATION HEADER BANNER -->
            <div class="ctc-guest-header-banner">
                <div class="ctc-guest-header-left">
                    <div class="ctc-guest-header-icon">
                        <i class="fa-solid fa-user-clock"></i>
                    </div>
                    <div class="ctc-guest-header-title-wrap">
                        <h2 class="ctc-guest-header-title"><?php esc_html_e('Medical Consultation Desk', 'caretochina-medical'); ?></h2>
                        <p class="ctc-guest-header-subtitle">
                            <span class="ctc-status-dot"></span>
                            <?php esc_html_e('Active Inquiry:', 'caretochina-medical'); ?> <strong>#<?php echo esc_html($guest_booking->booking_code); ?></strong>
                            <span class="ctc-hide-mobile">&nbsp;•&nbsp; <?php echo esc_html($guest_booking->full_name); ?></span>
                        </p>
                    </div>
                </div>
                <div class="ctc-guest-header-right">
                    <!-- Theme Toggle Button -->
                    <button type="button" class="ctc-hdr-btn ctc-hdr-btn-glass" onclick="window.appToggleTheme()" title="<?php esc_html_e('Toggle Dark/Light Mode', 'caretochina-medical'); ?>">
                        <i class="fa-solid fa-circle-half-stroke"></i>
                    </button>
                    <a href="<?php echo esc_url($register_url); ?>" class="ctc-solid-btn btn-teal-primary ctc-guest-signin-btn">
                        <i class="fa-solid fa-arrow-right-to-bracket"></i>
                        <span><?php esc_html_e('Sign In / Register', 'caretochina-medical'); ?></span>
                    </a>
                </div>
            </div>

            <!-- PERSISTENT IN-CHAT AUTH BANNER WITH LIVE COUNTDOWN TIMER -->
            <div class="ctc-guest-auth-banner">
                <div class="ctc-guest-auth-info">
                    <div class="ctc-guest-countdown-icon-box">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                    <div class="ctc-guest-countdown-details">
                        <div class="ctc-guest-countdown-headline">
                            <span class="ctc-guest-countdown-title"><?php esc_html_e('Guest Chat Session Access Remaining:', 'caretochina-medical'); ?></span>
                            <span id="ctc-guest-countdown-display" class="ctc-guest-countdown-badge" data-expires="<?php echo esc_attr($expiry_timestamp); ?>">
                                <?php
                                /* translators: %d: Remaining days */
                                printf(esc_html__('%d Days remaining', 'caretochina-medical'), (int) ceil($remaining_secs / 86400));
                                ?>
                            </span>
                        </div>
                        <p class="ctc-guest-countdown-subtext">
                            <?php esc_html_e('This temporary guest session will expire. Register an account now to permanently preserve your chat history, medical records, and unlock treatment payments.', 'caretochina-medical'); ?>
                        </p>
                    </div>
                </div>
                <a href="<?php echo esc_url($register_url); ?>" class="ctc-guest-auth-link ctc-solid-btn btn-teal-primary">
                    <i class="fa-solid fa-user-plus"></i>
                    <span><?php esc_html_e('Save to Account / Register', 'caretochina-medical'); ?> &rarr;</span>
                </a>
            </div>

            <!-- GUEST CONSULTATION GRID LAYOUT -->
            <div class="ctc-guest-layout">
                <!-- Left Sidebar: Booking Summary -->
                <div class="ctc-panel-card ctc-guest-summary-card">
                    <div class="ctc-guest-summary-header" onclick="jQuery('.ctc-guest-summary-body').slideToggle(200); jQuery(this).find('.ctc-summary-collapse-icon').toggleClass('rotated');">
                        <h3>
                            <i class="fa-solid fa-clipboard-list"></i> <?php esc_html_e('Inquiry Summary', 'caretochina-medical'); ?>
                        </h3>
                        <span class="ctc-summary-collapse-icon ctc-show-mobile"><i class="fa-solid fa-chevron-down"></i></span>
                    </div>
                    
                    <div class="ctc-guest-summary-body">
                        <div class="ctc-summary-row">
                            <span class="ctc-summary-row-lbl"><?php esc_html_e('Case Code', 'caretochina-medical'); ?></span>
                            <span class="ctc-summary-row-val ctc-highlight-code">#<?php echo esc_html($guest_booking->booking_code); ?></span>
                        </div>
                        <div class="ctc-summary-row">
                            <span class="ctc-summary-row-lbl"><?php esc_html_e('Patient Name', 'caretochina-medical'); ?></span>
                            <strong class="ctc-summary-row-val"><?php echo esc_html($guest_booking->full_name); ?></strong>
                        </div>
                        <div class="ctc-summary-row">
                            <span class="ctc-summary-row-lbl"><?php esc_html_e('Hospital Preferred', 'caretochina-medical'); ?></span>
                            <span class="ctc-summary-row-val"><?php echo esc_html($guest_booking->hospital_name); ?></span>
                        </div>
                        <div class="ctc-summary-row">
                            <span class="ctc-summary-row-lbl"><?php esc_html_e('Specialty', 'caretochina-medical'); ?></span>
                            <span class="ctc-summary-row-val"><?php echo esc_html($guest_booking->specialty); ?></span>
                        </div>
                        <div class="ctc-summary-row">
                            <span class="ctc-summary-row-lbl"><?php esc_html_e('Timing', 'caretochina-medical'); ?></span>
                            <span class="ctc-summary-row-val"><?php echo esc_html($guest_booking->treatment_timing); ?></span>
                        </div>
                        <div class="ctc-summary-row">
                            <span class="ctc-summary-row-lbl"><?php esc_html_e('Estimated Package', 'caretochina-medical'); ?></span>
                            <span class="ctc-summary-row-val ctc-highlight-price"><?php 
                                $g_curr = $guest_booking->currency ?: 'USD';
                                $g_sym = class_exists('CareToChina_Packages') ? CareToChina_Packages::get_currency_symbol($g_curr) : '$';
                                echo esc_html($g_sym . number_format($guest_booking->amount, 2) . ' ' . $g_curr); 
                            ?></span>
                        </div>

                        <div class="ctc-guest-continuity-notice">
                            <i class="fa-solid fa-envelope-open-text"></i> <?php esc_html_e('Check your email confirmation to reopen this live chat anytime from any device.', 'caretochina-medical'); ?>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Live Chat Panel -->
                <div class="ctc-panel-card ctc-guest-chat-panel">
                    <div class="ctc-chat-panel-header">
                        <div class="ctc-chat-panel-title">
                            <i class="fa-solid fa-comments"></i>
                            <div>
                                <h4><?php esc_html_e('Live Coordinator Chat', 'caretochina-medical'); ?></h4>
                                <span class="ctc-chat-partner-status"><?php esc_html_e('Care Coordinator on Duty', 'caretochina-medical'); ?></span>
                            </div>
                        </div>
                        <span class="ctc-sync-indicator">
                            <i class="fa-solid fa-circle"></i> <?php esc_html_e('Live', 'caretochina-medical'); ?>
                        </span>
                    </div>

                    <div id="patient-chat-box" class="ctc-chat-viewport">
                        <!-- Loaded dynamically via AJAX -->
                    </div>
                    <div id="patient-chat-typing-indicator" class="ctc-chat-typing-indicator" style="display:none;"></div>

                    <form id="patient-message-form" class="ctc-chat-form" enctype="multipart/form-data">
                        <input type="hidden" name="booking_id" value="<?php echo esc_attr($guest_booking->id); ?>">
                        <input type="hidden" name="guest_token" value="<?php echo esc_attr($raw_token); ?>">

                        <div id="patient_attachment_preview" class="ctc-attachment-preview-box" style="display:none;">
                            <span id="patient_attachment_name" class="ctc-attachment-name"></span>
                            <button type="button" onclick="appChat.clearAttachment()" class="ctc-attachment-clear-btn" aria-label="<?php esc_attr_e('Remove attached file', 'caretochina-medical'); ?>" role="button">&times;</button>
                        </div>

                        <div class="ctc-chat-input-bar">
                            <label for="patient_chat_file_input" class="ctc-chat-attach-btn" title="<?php esc_attr_e('Attach Image or PDF (Max 2MB)', 'caretochina-medical'); ?>" aria-label="<?php esc_attr_e('Attach medical report or image (Max 2MB)', 'caretochina-medical'); ?>" role="button">
                                <i class="fa-solid fa-paperclip"></i>
                            </label>
                            <input type="file" id="patient_chat_file_input" name="attachment" accept="image/jpeg,image/png,image/webp,image/gif,application/pdf" style="display:none;" onchange="appChat.handleFileSelected(this)">
                            
                            <input type="text" name="message" id="patient_msg_input" class="ctc-chat-text-input" placeholder="<?php esc_html_e('Type a message to your coordinator...', 'caretochina-medical'); ?>" autocomplete="off">
                            
                            <button type="submit" id="patient_send_btn" class="ctc-solid-btn btn-teal-primary ctc-chat-send-btn" aria-label="<?php esc_attr_e('Send message', 'caretochina-medical'); ?>">
                                <i class="fa-solid fa-paper-plane"></i>
                                <span class="ctc-btn-send-text"><?php esc_html_e('Send', 'caretochina-medical'); ?></span>
                            </button>
                        </div>
                        <div class="ctc-chat-allowed-hint">
                            <?php esc_html_e('Images & PDFs up to 2MB', 'caretochina-medical'); ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function handle_update_patient_profile() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'caretochina_booking_nonce') && !wp_verify_nonce($nonce, 'careyou_booking_nonce')) {
            wp_send_json_error(['message' => __('Security verification failed.', 'caretochina-medical')]);
        }

        $current_user = wp_get_current_user();
        if (!$current_user->exists()) {
            wp_send_json_error(['message' => __('Not logged in.', 'caretochina-medical')]);
        }

        $display_name = isset($_POST['display_name']) ? sanitize_text_field(wp_unslash($_POST['display_name'])) : '';
        $phone        = class_exists('CareToChina_Country_Helper') ? CareToChina_Country_Helper::extract_submitted_phone($_POST, 'phone') : (isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '');
        $gender       = isset($_POST['gender']) ? sanitize_text_field(wp_unslash($_POST['gender'])) : '';
        $age          = isset($_POST['age']) && $_POST['age'] !== '' ? absint(wp_unslash($_POST['age'])) : null;
        $whatsapp     = class_exists('CareToChina_Country_Helper') ? CareToChina_Country_Helper::extract_submitted_phone($_POST, 'whatsapp') : (isset($_POST['whatsapp']) ? sanitize_text_field(wp_unslash($_POST['whatsapp'])) : '');
        $wechat       = isset($_POST['wechat']) ? sanitize_text_field(wp_unslash($_POST['wechat'])) : '';
        $messenger    = isset($_POST['messenger']) ? sanitize_text_field(wp_unslash($_POST['messenger'])) : '';
        $linkedin     = isset($_POST['linkedin']) ? sanitize_text_field(wp_unslash($_POST['linkedin'])) : '';

        if (empty($display_name) || empty($phone) || empty($gender)) {
            wp_send_json_error(['message' => __('Name, phone number and gender are required.', 'caretochina-medical')]);
        }

        wp_update_user(['ID' => $current_user->ID, 'display_name' => $display_name]);
        update_user_meta($current_user->ID, 'patient_phone', $phone);
        update_user_meta($current_user->ID, 'patient_gender', $gender);
        if ($age !== null) {
            update_user_meta($current_user->ID, 'patient_age', $age);
        } else {
            delete_user_meta($current_user->ID, 'patient_age');
        }
        update_user_meta($current_user->ID, 'patient_whatsapp', $whatsapp);
        update_user_meta($current_user->ID, 'patient_wechat', $wechat);
        update_user_meta($current_user->ID, 'patient_messenger', $messenger);
        update_user_meta($current_user->ID, 'patient_linkedin', $linkedin);

        $has_custom_avatar = !empty(get_user_meta($current_user->ID, 'patient_avatar', true));
        $new_avatar_url = '';
        if (!$has_custom_avatar) {
            $uploads = wp_get_upload_dir();
            $base_url = !empty($uploads['baseurl']) ? $uploads['baseurl'] : content_url('/uploads');
            if (strcasecmp($gender, 'Female') === 0) {
                $new_avatar_url = $base_url . '/2026/08/placeholder_female.webp';
            } else {
                $new_avatar_url = $base_url . '/2026/08/placeholder_male.webp';
            }
        }

        wp_send_json_success([
            'message' => __('Profile updated successfully!', 'caretochina-medical'),
            'has_custom_avatar' => $has_custom_avatar,
            'new_avatar_url' => $new_avatar_url
        ]);
    }

    public function get_patient_chat_html($booking_id) {
        global $wpdb;
        // Only run write update if there are unread coordinator messages
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $has_unread = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}caretochina_messages WHERE booking_id = %d AND sender_type = %s AND is_read = %d",
            $booking_id, 'coordinator', 0
        ));
        if ($has_unread > 0) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}caretochina_messages SET is_read = 1 WHERE booking_id = %d AND sender_type = %s AND is_read = 0", $booking_id, 'coordinator'));
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $messages = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}caretochina_messages WHERE booking_id = %d ORDER BY id ASC", $booking_id));

        $chat_html = '';
        if (empty($messages)) {
            $chat_html .= '<div class="chat-msg coordinator mb-14"><img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=100&q=80" alt="Coordinator" class="chat-coordinator-avatar"><div class="msg-bubble"><strong class="staff-prefix">Elena (Care Coordinator):</strong> ' . esc_html__('Hello! I am Elena, your assigned Care Coordinator. How can I assist with your medical itinerary today?', 'caretochina-medical') . '</div></div>';
        } else {
            foreach ($messages as $m) {
                if ($m->is_read == 1) {
                    $read_tick = '<span class="chat-tick chat-tick-seen" title="' . esc_attr(__('Seen by Coordinator', 'caretochina-medical')) . '">✓✓ Seen</span>';
                } else {
                    $read_tick = '<span class="chat-tick chat-tick-delivered" title="' . esc_attr(__('Delivered', 'caretochina-medical')) . '">✓ Delivered</span>';
                }

                // Check for Payment Request message type
                if (isset($m->message_type) && $m->message_type === 'payment_request' && !empty($m->payment_request_id)) {
                    if (class_exists('CareToChina_Payment_Request_Manager')) {
                        $chat_html .= '<div class="chat-msg payment-request-msg mb-14">';
                        $chat_html .= CareToChina_Payment_Request_Manager::render_card($m->payment_request_id, false);
                        $chat_html .= '</div>';
                        continue;
                    }
                }

                // Render File Attachment if present
                $attachment_html = '';
                if (!empty($m->attachment_url)) {
                    if ($m->attachment_type === 'image' || preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $m->attachment_url)) {
                        $attachment_html = '<div class="chat-attachment-image"><a href="' . esc_url($m->attachment_url) . '" target="_blank" rel="noopener noreferrer"><img src="' . esc_url($m->attachment_url) . '" alt="' . esc_attr($m->attachment_name ?: 'Image Attachment') . '"></a></div>';
                    } elseif ($m->attachment_type === 'pdf' || preg_match('/\.pdf$/i', $m->attachment_url)) {
                        $attachment_html = '<div class="chat-attachment-pdf"><a href="' . esc_url($m->attachment_url) . '" target="_blank" rel="noopener noreferrer" class="chat-pdf-pill"><i class="fa-solid fa-file-pdf"></i> <span class="chat-pdf-name">' . esc_html($m->attachment_name ?: 'Medical_Document.pdf') . '</span> <i class="fa-solid fa-arrow-down-to-bracket"></i></a></div>';
                    }
                }

                if ($m->sender_type === 'coordinator') {
                    $staff_name = 'Staff';
                    if (preg_match('/Staff \((.+)\)/', $m->sender_name, $matches)) {
                        $staff_name = $matches[1];
                    }
                    $msg_text_html = !empty($m->message) ? esc_html($m->message) : '';
                    $chat_html .= '<div class="chat-msg coordinator mb-14">
                        <div class="msg-bubble">
                            <span class="staff-prefix">Staff(' . esc_html($staff_name) . '):</span> ' . $msg_text_html . '
                            ' . $attachment_html . '
                        </div>
                    </div>';
                } else {
                    $pat_name = $m->sender_name ?: 'Patient';
                    $msg_text_html = !empty($m->message) ? esc_html($m->message) : '';
                    $chat_html .= '<div class="chat-msg patient mb-14">
                        <div class="msg-bubble">
                            <span class="patient-msg-text">' . $msg_text_html . '</span>
                            <span class="patient-name-tag">(' . esc_html($pat_name) . ')</span>
                            ' . $attachment_html . '
                            <div class="chat-msg-meta">' . $read_tick . '</div>
                        </div>
                    </div>';
                }
            }
        }
        return $chat_html;
    }

    public function handle_patient_message() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'caretochina_booking_nonce') && !wp_verify_nonce($nonce, 'careyou_booking_nonce')) {
            wp_send_json_error(['message' => __('Security verification failed.', 'caretochina-medical')]);
        }

        $user_id = get_current_user_id();
        if ($user_id > 0 && get_user_meta($user_id, 'patient_restricted', true)) {
            wp_send_json_error(['message' => __('You have been restricted from sending messages. Please contact support.', 'caretochina-medical')]);
        }

        $booking_id = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;
        $raw_token = isset($_POST['guest_token']) ? sanitize_text_field(wp_unslash($_POST['guest_token'])) : (isset($_COOKIE['ctc_guest_token']) ? sanitize_text_field(wp_unslash($_COOKIE['ctc_guest_token'])) : '');

        $booking = $this->resolve_booking_access($booking_id, $raw_token);
        if (!$booking) {
            wp_send_json_error(['message' => __('Access denied. You do not own this booking case.', 'caretochina-medical')]);
        }

        global $wpdb;
        $table_messages = $wpdb->prefix . 'caretochina_messages';
        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';

        // Determine sender name
        $current_user = wp_get_current_user();
        if ($current_user->exists()) {
            $sender_name = $current_user->display_name ?: 'Patient';
        } else {
            $sender_name = $booking->full_name ?: 'Guest Patient';
        }

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

        if ($booking_id > 0 && (!empty($message) || !empty($attachment_url))) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->insert($table_messages, [
                'booking_id'      => $booking_id,
                'sender_type'     => 'patient',
                'sender_name'     => $sender_name,
                'message'         => $message,
                'attachment_url'  => $attachment_url,
                'attachment_name' => $attachment_name,
                'attachment_type' => $attachment_type,
                'is_read'         => 0,
                'created_at'      => current_time('mysql'),
            ]);
            
            $html = $this->get_patient_chat_html($booking_id);
            wp_send_json_success(['message' => $message, 'html' => $html]);
        }
        wp_send_json_error(['message' => __('Please enter a message or select a file to send.', 'caretochina-medical')]);
    }

    public function handle_patient_typing() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'caretochina_booking_nonce') && !wp_verify_nonce($nonce, 'careyou_booking_nonce')) {
            wp_send_json_error(['message' => __('Security verification failed.', 'caretochina-medical')]);
        }

        $user_id = get_current_user_id();
        if ($user_id > 0 && get_user_meta($user_id, 'patient_restricted', true)) {
            wp_send_json_error(['message' => __('Restricted.', 'caretochina-medical')]);
        }

        $booking_id = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;
        $raw_token = isset($_POST['guest_token']) ? sanitize_text_field(wp_unslash($_POST['guest_token'])) : (isset($_COOKIE['ctc_guest_token']) ? sanitize_text_field(wp_unslash($_COOKIE['ctc_guest_token'])) : '');

        $booking = $this->resolve_booking_access($booking_id, $raw_token);
        if (!$booking) {
            wp_send_json_error(['message' => __('Access denied.', 'caretochina-medical')]);
        }

        if ($booking_id > 0) {
            set_transient('ctc_typing_' . $booking_id . '_patient', 1, 4);
            wp_send_json_success();
        }
        wp_send_json_error();
    }

    public function handle_get_patient_chat() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'caretochina_booking_nonce') && !wp_verify_nonce($nonce, 'careyou_booking_nonce')) {
            wp_send_json_error(['message' => __('Security verification failed.', 'caretochina-medical')]);
        }

        $booking_id = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;
        $raw_token = isset($_POST['guest_token']) ? sanitize_text_field(wp_unslash($_POST['guest_token'])) : (isset($_COOKIE['ctc_guest_token']) ? sanitize_text_field(wp_unslash($_COOKIE['ctc_guest_token'])) : '');

        $booking = $this->resolve_booking_access($booking_id, $raw_token);
        if (!$booking) {
            wp_send_json_error(['message' => __('Access denied. You do not own this booking case.', 'caretochina-medical')]);
        }

        if ($booking_id > 0) {
            $chat_html = $this->get_patient_chat_html($booking_id);
            // Check if coordinator is typing
            $is_typing = get_transient('ctc_typing_' . $booking_id . '_coordinator') ? true : false;

            wp_send_json_success([
                'html' => $chat_html,
                'is_typing' => $is_typing,
                'typing_name' => 'Coordinator'
            ]);
        }
        wp_send_json_error(['message' => __('Invalid booking ID', 'caretochina-medical')]);
    }

    public function handle_patient_avatar_upload() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'caretochina_booking_nonce') && !wp_verify_nonce($nonce, 'careyou_booking_nonce')) {
            wp_send_json_error(['message' => __('Security verification failed.', 'caretochina-medical')]);
        }

        $current_user = wp_get_current_user();
        if (!$current_user->exists()) {
            wp_send_json_error(['message' => __('Not logged in.', 'caretochina-medical')]);
        }
        $user_id = $current_user->ID;

        if (empty($_FILES['avatar'])) {
            wp_send_json_error(['message' => __('No file uploaded.', 'caretochina-medical')]);
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $file = $_FILES['avatar'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(['message' => __('Upload failed with error code: ', 'caretochina-medical') . $file['error']]);
        }

        // Limit size: 2MB (2 * 1024 * 1024 bytes)
        $max_size = 2 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            wp_send_json_error(['message' => __('Image size must be less than 2 MB.', 'caretochina-medical')]);
        }

        // Allowed extensions
        $allowed_exts = ['png', 'jpg', 'jpeg', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_exts)) {
            wp_send_json_error(['message' => __('Only PNG, JPG, and WEBP images are allowed.', 'caretochina-medical')]);
        }

        // Allowed mime types
        $allowed_mimes = ['image/png', 'image/jpeg', 'image/pjpeg', 'image/x-png', 'image/webp'];
        if (!in_array($file['type'], $allowed_mimes)) {
            wp_send_json_error(['message' => __('Invalid file type. Only PNG, JPG, and WEBP images are allowed.', 'caretochina-medical')]);
        }

        // Ensure WordPress upload helper files are loaded
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }

        // Delete old custom avatar file if it exists, to avoid clutter
        $old_avatar_url = get_user_meta($user_id, 'patient_avatar', true);
        if (!empty($old_avatar_url)) {
            $upload_dir = wp_upload_dir();
            $base_url = $upload_dir['baseurl'];
            $base_dir = $upload_dir['basedir'];
            if (strpos($old_avatar_url, $base_url) === 0) {
                $old_file_path = str_replace($base_url, $base_dir, $old_avatar_url);
                if (file_exists($old_file_path)) {
                    wp_delete_file($old_file_path);
                }
            }
        }

        $upload_overrides = ['test_form' => false];
        $movefile = wp_handle_upload($file, $upload_overrides);

        if ($movefile && !isset($movefile['error'])) {
            $new_avatar_url = $movefile['url'];
            update_user_meta($user_id, 'patient_avatar', $new_avatar_url);
            wp_send_json_success([
                'message' => __('Profile photo uploaded successfully!', 'caretochina-medical'),
                'avatar_url' => $new_avatar_url
            ]);
        } else {
            wp_send_json_error(['message' => $movefile['error']]);
        }
    }

    public function handle_patient_delete_own_account() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'caretochina_booking_nonce') && !wp_verify_nonce($nonce, 'careyou_booking_nonce')) {
            wp_send_json_error(['message' => __('Security verification failed.', 'caretochina-medical')]);
        }

        $current_user = wp_get_current_user();
        if (!$current_user->exists()) {
            wp_send_json_error(['message' => __('Not logged in.', 'caretochina-medical')]);
        }
        $user_id = $current_user->ID;

        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';
        $table_messages = $wpdb->prefix . 'caretochina_messages';

        // 1. Fetch user bookings
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $bookings = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}caretochina_bookings WHERE patient_id = %d", $user_id));
        if (!empty($bookings)) {
            // Delete messages for each booking
            foreach ($bookings as $b_id) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->delete($table_messages, ['booking_id' => $b_id]);
            }
            // Delete bookings
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->delete($table_bookings, ['patient_id' => $user_id]);
        }

        // 2. Delete avatar file if exists
        $avatar_url = get_user_meta($user_id, 'patient_avatar', true);
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

        // 3. Log out current user session
        wp_logout();

        // 4. Delete the WordPress user account
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($user_id);

        wp_send_json_success([
            'message' => __('Your account and data have been permanently deleted.', 'caretochina-medical'),
            'redirect' => home_url('/')
        ]);
    }
}