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
        add_action('wp_ajax_caretochina_get_patient_chat', [$this, 'handle_get_patient_chat']);
        add_action('wp_ajax_caretochina_update_patient_profile', [$this, 'handle_update_patient_profile']);
        add_action('wp_ajax_caretochina_upload_patient_avatar', [$this, 'handle_patient_avatar_upload']);
        add_action('wp_ajax_caretochina_patient_send_typing', [$this, 'handle_patient_typing']);
        add_action('wp_ajax_caretochina_patient_delete_own_account', [$this, 'handle_patient_delete_own_account']);
        add_action('wp_ajax_careyou_patient_delete_own_account', [$this, 'handle_patient_delete_own_account']);

        // Backward compatibility AJAX aliases
        add_action('wp_ajax_careyou_send_patient_message', [$this, 'handle_patient_message']);
        add_action('wp_ajax_careyou_get_patient_chat', [$this, 'handle_get_patient_chat']);
        add_action('wp_ajax_careyou_upload_patient_avatar', [$this, 'handle_patient_avatar_upload']);
        add_action('template_redirect', [$this, 'restrict_guest_access']);
    }

    public function restrict_guest_access() {
        if (!is_user_logged_in()) {
            if (is_page('patient-dashboard') || strpos($_SERVER['REQUEST_URI'], 'patient-dashboard') !== false) {
                wp_redirect(home_url('/patient-login/'));
                exit;
            }
        }
    }

    public function render_dashboard() {
        ob_start();
        if (!is_user_logged_in()) {
            echo '<script>window.location.href = "' . esc_js(home_url('/patient-login/')) . '";</script>';
            return ob_get_clean();
        }
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';

        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;
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
            $bookings = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_bookings WHERE patient_id = %d OR email = %s ORDER BY id DESC", $user_id, $email));
        }

        $active_booking = null;
        if (!empty($bookings)) {
            $active_booking = $bookings[0];
            $stage = intval($active_booking->timeline_stage ?? 1);
            $stage_pct = min(100, max(20, $stage * 20));
        }

        $logout_url = wp_logout_url(home_url('/patient-login/'));
        ?>
        <div class="careyou-dashboard-wrapper caretochina-dashboard-wrapper" data-booking-id="<?php echo esc_attr($active_booking ? $active_booking->id : 0); ?>">
            <!-- 1. DASHBOARD HEADER BANNER -->
            <div class="ctc-dash-banner">
                <div class="ctc-dash-banner-left">
                    <img src="<?php echo esc_url($avatar_url); ?>" alt="Patient Avatar" class="ctc-dash-avatar">
                    <div class="ctc-dash-banner-info">
                        <h2 class="ctc-dash-welcome-text"><?php printf(__('Welcome back, %s', 'caretochina-booking'), esc_html($display_name)); ?></h2>
                        <p class="ctc-dash-subtitle-text">
                            <span class="ctc-status-dot"></span> 
                            <?php if ($active_booking) : ?>
                                <?php _e('Care Case:', 'caretochina-booking'); ?> <strong>#<?php echo esc_html($active_booking->booking_code); ?></strong> 
                            <?php else : ?>
                                <?php _e('No Active Travel Case', 'caretochina-booking'); ?>
                            <?php endif; ?>
                            &nbsp;•&nbsp; <?php _e('Role:', 'caretochina-booking'); ?> <strong><?php _e('Patient', 'caretochina-booking'); ?></strong>
                        </p>
                    </div>
                </div>
                <div class="ctc-dash-banner-actions">
                    <?php if ($active_booking) : ?>
                        <button type="button" class="ctc-hdr-btn ctc-hdr-btn-glass" onclick="appDash.switchTabDirect('messages')">
                            <i class="fa-solid fa-headset"></i> <?php _e('Care Coordinator Chat', 'caretochina-booking'); ?>
                        </button>
                    <?php endif; ?>
                    <a href="<?php echo esc_url($logout_url); ?>" class="ctc-hdr-btn ctc-hdr-btn-glass">
                        <i class="fa-solid fa-right-from-bracket"></i> <?php _e('Logout', 'caretochina-booking'); ?>
                    </a>
                </div>
            </div>

            <!-- 2. DASHBOARD CONTAINER GRID (SIDEBAR TABS + PANELS) -->
            <div class="ctc-dash-grid">
                <!-- SIDEBAR NAVIGATION TABS -->
                <div class="ctc-dash-sidebar">
                    <button type="button" class="ctc-sidebar-toggle-btn" onclick="appDash.toggleSidebar()"><i class="fa-solid fa-angles-left"></i></button>
                    <button type="button" class="ctc-sidebar-tab active" onclick="appDash.switchTab(this, 'overview')">
                        <i class="fa-solid fa-chart-line"></i> <span><?php _e('Account Overview', 'caretochina-booking'); ?></span>
                    </button>
                    <button type="button" class="ctc-sidebar-tab" onclick="appDash.switchTab(this, 'invoices')">
                        <i class="fa-solid fa-file-invoice-dollar"></i> <span><?php _e('Payment History', 'caretochina-booking'); ?></span>
                    </button>
                    <button type="button" class="ctc-sidebar-tab" onclick="appDash.switchTab(this, 'account')">
                        <i class="fa-solid fa-user-gear"></i> <span><?php _e('Account Settings', 'caretochina-booking'); ?></span>
                    </button>
                    <button type="button" class="ctc-sidebar-tab" onclick="appDash.switchTab(this, 'milestones')">
                        <i class="fa-solid fa-list-check"></i> <span><?php _e('Treatment Timeline', 'caretochina-booking'); ?></span>
                    </button>
                    <button type="button" class="ctc-sidebar-tab" onclick="appDash.switchTab(this, 'messages')">
                        <i class="fa-solid fa-comments"></i> <span><?php _e('Coordinator Messages', 'caretochina-booking'); ?></span>
                    </button>
                    <button type="button" class="ctc-sidebar-tab tab-logout-item" onclick="appDash.switchTab(this, 'logout')">
                        <i class="fa-solid fa-right-from-bracket"></i> <span><?php _e('Log Out', 'caretochina-booking'); ?></span>
                    </button>
                </div>

                <!-- MAIN CONTENT PANELS -->
                <div class="ctc-dash-content">
                    
                    <!-- TAB 1: Account Overview -->
                    <div class="ctc-dash-panel active" id="dash-panel-overview">
                        <?php if ($active_booking) : ?>
                            <!-- STAT CARDS GRID -->
                            <div class="ctc-stat-grid">
                                <div class="ctc-stat-card">
                                    <div class="ctc-stat-icon icon-teal"><i class="fa-solid fa-hospital"></i></div>
                                    <div class="ctc-stat-details">
                                        <h4 class="ctc-stat-val"><?php echo esc_html($active_booking->hospital_name); ?></h4>
                                        <span class="ctc-stat-lbl"><?php _e('Assigned Hospital', 'caretochina-booking'); ?></span>
                                    </div>
                                </div>
                                <div class="ctc-stat-card">
                                    <div class="ctc-stat-icon icon-green"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
                                    <div class="ctc-stat-details">
                                        <h4 class="ctc-stat-val"><?php echo esc_html($active_booking->specialty); ?></h4>
                                        <span class="ctc-stat-lbl"><?php _e('Specialty', 'caretochina-booking'); ?></span>
                                    </div>
                                </div>
                                <div class="ctc-stat-card">
                                    <div class="ctc-stat-icon icon-amber"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                                    <div class="ctc-stat-details">
                                        <h4 class="ctc-stat-val text-teal-accent"><?php echo esc_html($active_booking->invoice_status); ?></h4>
                                        <span class="ctc-stat-lbl"><?php _e('Payment status', 'caretochina-booking'); ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- PROGRESS BAR CARD -->
                            <div class="ctc-panel-card" style="margin-bottom:24px;">
                                <div class="ctc-card-header-row">
                                    <h3 class="ctc-card-title"><?php _e('Treatment Journey Progress', 'caretochina-booking'); ?></h3>
                                    <span class="ctc-progress-pct"><?php echo $stage_pct; ?>% <?php _e('Completed', 'caretochina-booking'); ?></span>
                                </div>
                                <div class="ctc-progress-track">
                                    <div class="ctc-progress-bar-fill" style="width: <?php echo $stage_pct; ?>%;"></div>
                                </div>
                            </div>

                            <!-- BOOKINGS LIST TABLE CARD -->
                            <div class="ctc-panel-card">
                                <h3 class="ctc-card-title" style="margin-bottom:18px !important;"><?php _e('Active Medical Travel Bookings', 'caretochina-booking'); ?></h3>
                                <div class="ctc-table-responsive">
                                    <table class="ctc-custom-table">
                                        <thead>
                                            <tr>
                                                <th><?php _e('Case Code', 'caretochina-booking'); ?></th>
                                                <th><?php _e('Specialty', 'caretochina-booking'); ?></th>
                                                <th><?php _e('Hospital Preferred', 'caretochina-booking'); ?></th>
                                                <th><?php _e('Timing', 'caretochina-booking'); ?></th>
                                                <th><?php _e('Status', 'caretochina-booking'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($bookings as $b): ?>
                                                <tr>
                                                    <td class="ctc-td-code"><?php echo esc_html($b->booking_code); ?></td>
                                                    <td><?php echo esc_html($b->specialty); ?></td>
                                                    <td><?php echo esc_html($b->hospital_name); ?></td>
                                                    <td><?php echo esc_html($b->treatment_timing); ?></td>
                                                    <td><span class="ctc-badge-pill badge-success"><?php echo strtoupper(esc_html($b->status)); ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php else : ?>
                            <!-- GET A FREE QUOTE BANNER (No bookings found) -->
                            <div class="ctc-panel-card text-center" style="text-align:center; padding:50px 30px; background:#F0FDF4; border:1px dashed #2DD4BF; border-radius:24px;">
                                <div style="width:70px; height:70px; border-radius:50%; background:#CCFBF1; color:#0F766E; display:inline-flex; align-items:center; justify-content:center; font-size:32px; margin-bottom:20px;">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                                </div>
                                <h3 style="font-family:'Manrope'; font-size:24px; font-weight:800; color: var(--cymb-text-dark); margin:0 0 10px 0;"><?php _e('No Active Treatment Cases Found', 'caretochina-booking'); ?></h3>
                                <p style="color:#64748B; font-size:15px; max-width:500px; margin:0 auto 24px auto; line-height:1.6;"><?php _e('Connect with top JCI-certified hospitals in China. Start your personalized medical consultation roadmap today.', 'caretochina-booking'); ?></p>
                                <button type="button" class="ctc-solid-btn btn-teal-primary btn-lg" onclick="appWizard.openScenario1()" style="padding:16px 36px; border-radius:999px; font-size:16px; font-weight:700;"><i class="fa-solid fa-calendar-plus"></i> <?php _e('Request Free Quote & Plan Now', 'caretochina-booking'); ?></button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- TAB 2: PAYMENT HISTORY -->
                    <div class="ctc-dash-panel" id="dash-panel-invoices">
                        <div class="ctc-panel-card" style="margin-bottom: 24px !important;">
                            <h3 class="ctc-card-title" style="margin-bottom: 18px !important;"><?php _e('Payment History & Billing Invoices', 'caretochina-booking'); ?></h3>
                            
                            <?php if ($active_booking) : ?>
                                <div class="ctc-summary-grid" style="margin-bottom: 24px !important;">
                                    <div class="ctc-summary-box">
                                        <span class="ctc-summary-lbl"><?php _e('Treatment Package Cost', 'caretochina-booking'); ?></span>
                                        <h3 class="ctc-summary-val">$14,500.00</h3>
                                    </div>
                                    <div class="ctc-summary-box">
                                        <span class="ctc-summary-lbl"><?php _e('Payment Status', 'caretochina-booking'); ?></span>
                                        <h3 class="ctc-summary-val text-teal-accent" style="font-size:16px; font-weight:800;"><?php echo esc_html($active_booking->invoice_status); ?></h3>
                                    </div>
                                </div>
                                <div class="ctc-table-responsive">
                                    <table class="ctc-custom-table">
                                        <thead>
                                            <tr>
                                                <th><?php _e('Invoice ID', 'caretochina-booking'); ?></th>
                                                <th><?php _e('Description', 'caretochina-booking'); ?></th>
                                                <th><?php _e('Total Package Price', 'caretochina-booking'); ?></th>
                                                <th><?php _e('Billing Milestone', 'caretochina-booking'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>#INV-9284</td>
                                                <td><?php echo esc_html($active_booking->specialty); ?> at <?php echo esc_html($active_booking->hospital_name); ?></td>
                                                <td>$14,500.00</td>
                                                <td><span class="ctc-badge-pill badge-success"><?php echo esc_html($active_booking->invoice_status); ?></span></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else : ?>
                                <div style="text-align:center; padding:40px 20px; color:#64748B;">
                                    <i class="fa-solid fa-file-invoice" style="font-size:40px; color:#CBD5E1; margin-bottom:12px;"></i>
                                    <p><?php _e('No billing invoices available. Submit a booking request to generate estimates.', 'caretochina-booking'); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- TAB 3: ACCOUNT SETTINGS (PREMIUM STYLED FORM) -->
                    <div class="ctc-dash-panel" id="dash-panel-account">
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
                                    <h3 class="ctc-card-title"><?php _e('Patient Account Profile & Settings', 'caretochina-booking'); ?></h3>
                                    <p class="ctc-card-subtitle" style="margin: 0 0 6px 0;"><?php _e('Manage your patient credentials, contact phone, and profile photo.', 'caretochina-booking'); ?></p>
                                    <button type="button" class="ctc-change-avatar-btn" style="background: none; border: none; color: #0F766E; font-weight: 700; font-size: 13px; cursor: pointer; padding: 0; text-decoration: underline; display: flex; align-items: center; gap: 6px;">
                                        <i class="fa-solid fa-upload"></i> <?php _e('Upload Photo (PNG, JPG, WEBP - Max 2MB)', 'caretochina-booking'); ?>
                                    </button>
                                    <span id="avatar-upload-status" style="display: none; font-size: 13px; font-weight: 600; margin-left: 10px;"></span>
                                </div>
                            </div>
                            <form id="patient-profile-form">
                                <div class="ctc-form-grid-2" style="margin-bottom: 20px !important;">
                                    <div class="form-group">
                                        <label class="form-label"><i class="fa-solid fa-user"></i> <?php _e('Full Name *', 'caretochina-booking'); ?></label>
                                        <input type="text" name="display_name" class="form-input" value="<?php echo esc_attr($display_name); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label"><i class="fa-solid fa-phone"></i> <?php _e('Phone Number *', 'caretochina-booking'); ?></label>
                                        <input type="text" name="phone" class="form-input" value="<?php echo esc_attr($phone); ?>" required>
                                    </div>
                                </div>

                                <div class="ctc-form-grid-2" style="margin-bottom: 20px !important;">
                                    <div class="form-group">
                                        <label class="form-label"><i class="fa-solid fa-calendar-day"></i> <?php _e('Age', 'caretochina-booking'); ?></label>
                                        <input type="number" name="age" class="form-input" value="<?php echo esc_attr($age); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label"><i class="fa-solid fa-venus-mars"></i> <?php _e('Gender *', 'caretochina-booking'); ?></label>
                                        <select name="gender" class="form-select" style="background:var(--cymb-bg-card);" required>
                                            <option value=""><?php _e('Select Gender', 'caretochina-booking'); ?></option>
                                            <option value="Male" <?php selected($gender, 'Male'); ?>><?php _e('Male', 'caretochina-booking'); ?></option>
                                            <option value="Female" <?php selected($gender, 'Female'); ?>><?php _e('Female', 'caretochina-booking'); ?></option>
                                        </select>
                                    </div>
                                </div>

                                <h4 style="margin: 20px 0 16px 0; font-family:'Manrope'; font-size:15px; border: 1px solid transparent; border-bottom: 1px solid var(--cymb-border-color); padding-bottom: 6px; color: var(--cymb-text-dark);"><?php _e('Social Accounts (Optional)', 'caretochina-booking'); ?></h4>

                                <div class="ctc-form-grid-2" style="margin-bottom: 20px !important;">
                                    <div class="form-group">
                                        <label class="form-label"><i class="fa-brands fa-whatsapp"></i> <?php _e('WhatsApp', 'caretochina-booking'); ?></label>
                                        <input type="text" name="whatsapp" class="form-input" value="<?php echo esc_attr($whatsapp); ?>" placeholder="+1 (800) 555-0199">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label"><i class="fa-brands fa-weixin"></i> <?php _e('WeChat ID', 'caretochina-booking'); ?></label>
                                        <input type="text" name="wechat" class="form-input" value="<?php echo esc_attr($wechat); ?>" placeholder="WeChat Username">
                                    </div>
                                </div>

                                <div class="ctc-form-grid-2" style="margin-bottom: 20px !important;">
                                    <div class="form-group">
                                        <label class="form-label"><i class="fa-brands fa-facebook-messenger"></i> <?php _e('Messenger', 'caretochina-booking'); ?></label>
                                        <input type="text" name="messenger" class="form-input" value="<?php echo esc_attr($messenger); ?>" placeholder="Messenger ID">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label"><i class="fa-brands fa-linkedin"></i> <?php _e('LinkedIn', 'caretochina-booking'); ?></label>
                                        <input type="text" name="linkedin" class="form-input" value="<?php echo esc_attr($linkedin); ?>" placeholder="LinkedIn Profile URL">
                                    </div>
                                </div>

                                <div class="ctc-form-grid-2" style="margin-bottom: 24px !important;">
                                    <div class="form-group">
                                        <label class="form-label"><i class="fa-solid fa-envelope"></i> <?php _e('Email Address (Account ID)', 'caretochina-booking'); ?></label>
                                        <input type="email" class="form-input" value="<?php echo esc_attr($email); ?>" disabled style="opacity:0.7; cursor:not-allowed; background:var(--cymb-bg-light);">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label"><i class="fa-solid fa-id-badge"></i> <?php _e('Account Role', 'caretochina-booking'); ?></label>
                                        <input type="text" class="form-input" value="Patient Account (Patient)" disabled style="opacity:0.7; cursor:not-allowed; background:var(--cymb-bg-light);">
                                    </div>
                                </div>

                                <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
                                    <button type="submit" id="save_profile_btn" class="ctc-solid-btn btn-teal-primary">
                                        <i class="fa-solid fa-floppy-disk"></i> <?php _e('Save Profile Changes', 'caretochina-booking'); ?>
                                    </button>
                                    <button type="button" id="delete_own_profile_btn" class="ctc-solid-btn btn-danger-solid" style="background:#EF4444; border-color:#EF4444; color:#FFF;">
                                        <i class="fa-solid fa-trash-can"></i> <?php _e('Delete My Account', 'caretochina-booking'); ?>
                                    </button>
                                    <div id="profile-response-box" class="ctc-response-msg" style="display:none;"></div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- TAB 4: TIMELINE -->
                    <div class="ctc-dash-panel" id="dash-panel-milestones">
                        <div class="ctc-panel-card">
                            <h3 class="ctc-card-title" style="margin-bottom: 24px !important;"><?php _e('Treatment Roadmap Milestones', 'caretochina-booking'); ?></h3>
                            
                            <?php if ($active_booking) : ?>
                                <div class="ctc-timeline-vertical">
                                    <div class="ctc-timeline-step <?php echo ($stage >= 1) ? 'is-complete' : ''; ?>">
                                        <div class="ctc-timeline-icon">
                                            <i class="fa-solid <?php echo ($stage >= 1) ? 'fa-circle-check' : 'fa-circle'; ?>"></i>
                                        </div>
                                        <div class="ctc-timeline-content">
                                            <h4 class="ctc-timeline-heading"><?php _e('Stage 1: Medical Assessment Completed', 'caretochina-booking'); ?></h4>
                                            <p class="ctc-timeline-desc"><?php _e('Report matching & surgeon video call completed.', 'caretochina-booking'); ?></p>
                                        </div>
                                    </div>
                                    <div class="ctc-timeline-step <?php echo ($stage >= 2) ? 'is-complete' : ''; ?>">
                                        <div class="ctc-timeline-icon">
                                            <i class="fa-solid <?php echo ($stage >= 2) ? 'fa-circle-check' : 'fa-circle'; ?>"></i>
                                        </div>
                                        <div class="ctc-timeline-content">
                                            <h4 class="ctc-timeline-heading"><?php _e('Stage 2: Hospital Guarantee & Embassy Visa Issued', 'caretochina-booking'); ?></h4>
                                            <p class="ctc-timeline-desc"><?php _e('Embassy confirmation and invitation letter generated.', 'caretochina-booking'); ?></p>
                                        </div>
                                    </div>
                                    <div class="ctc-timeline-step <?php echo ($stage >= 3) ? 'is-complete' : ''; ?>">
                                        <div class="ctc-timeline-icon">
                                            <i class="fa-solid <?php echo ($stage >= 3) ? 'fa-circle-check' : 'fa-circle'; ?>"></i>
                                        </div>
                                        <div class="ctc-timeline-content">
                                            <h4 class="ctc-timeline-heading"><?php _e('Stage 3: Airport Arrival & Chauffeur Transfer', 'caretochina-booking'); ?></h4>
                                            <p class="ctc-timeline-desc"><?php _e('Coordinator meets patient at Beijing/Shanghai airport.', 'caretochina-booking'); ?></p>
                                        </div>
                                    </div>
                                    <div class="ctc-timeline-step <?php echo ($stage >= 4) ? 'is-complete' : ''; ?>">
                                        <div class="ctc-timeline-icon">
                                            <i class="fa-solid <?php echo ($stage >= 4) ? 'fa-circle-check' : 'fa-circle'; ?>"></i>
                                        </div>
                                        <div class="ctc-timeline-content">
                                            <h4 class="ctc-timeline-heading"><?php _e('Stage 4: Surgical Procedure at Partner Hospital', 'caretochina-booking'); ?></h4>
                                            <p class="ctc-timeline-desc"><?php _e('In-patient stay and operations conducted by expert surgeons.', 'caretochina-booking'); ?></p>
                                        </div>
                                    </div>
                                    <div class="ctc-timeline-step <?php echo ($stage >= 5) ? 'is-complete' : ''; ?>">
                                        <div class="ctc-timeline-icon">
                                            <i class="fa-solid <?php echo ($stage >= 5) ? 'fa-circle-check' : 'fa-circle'; ?>"></i>
                                        </div>
                                        <div class="ctc-timeline-content">
                                            <h4 class="ctc-timeline-heading"><?php _e('Stage 5: Post-Op Recovery & Lifetime Telehealth', 'caretochina-booking'); ?></h4>
                                            <p class="ctc-timeline-desc"><?php _e('12 months post-surgery virtual consultation package.', 'caretochina-booking'); ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php else : ?>
                                <div style="text-align:center; padding:40px 20px; color:#64748B;">
                                    <i class="fa-solid fa-route" style="font-size:40px; color:#CBD5E1; margin-bottom:12px;"></i>
                                    <p><?php _e('Timeline awaiting booking submission. Request a quote to activate your roadmap.', 'caretochina-booking'); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>                    <!-- TAB 5: MESSAGES (PREVIEWING DELIVERED & SEEN TICK FEATURES) -->
                    <div class="ctc-dash-panel" id="dash-panel-messages">
                        <div class="ctc-panel-card">
                            <div class="ctc-card-header-row" style="margin-bottom:18px !important;">
                                <h3 class="ctc-card-title"><?php _e('Care Coordinator Live Chat', 'caretochina-booking'); ?></h3>
                                <span class="ctc-sync-indicator"><i class="fa-solid fa-circle text-success" style="font-size:10px;"></i> <?php _e('Real-Time Synchronized (Zero Reload)', 'caretochina-booking'); ?></span>
                            </div>

                            <?php if ($active_booking) : ?>
                                <div id="patient-chat-box" class="ctc-chat-viewport" style="max-height:350px; margin-bottom:18px; overflow-y:auto; padding:16px; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:12px;">
                                    <!-- Populated dynamically by JS AJAX Polling -->
                                </div>
                                <div id="patient-chat-typing-indicator" style="padding:0 16px 8px 16px; font-size:12px; color:#64748B; font-style:italic; display:none;"></div>

                                <form id="patient-message-form">
                                    <input type="hidden" name="booking_id" value="<?php echo esc_attr($active_booking->id); ?>">
                                    <div class="ctc-chat-input-group">
                                        <input type="text" name="message" id="patient_msg_input" class="ctc-field-input" placeholder="<?php _e('Type a message to your coordinator...', 'caretochina-booking'); ?>" required autocomplete="off">
                                        <button type="submit" id="patient_send_btn" class="ctc-solid-btn btn-teal-primary"><i class="fa-solid fa-paper-plane"></i> <?php _e('Send', 'caretochina-booking'); ?></button>
                                    </div>
                                </form>
                            <?php else : ?>
                                <div class="text-center" style="text-align:center; margin-top: 30px; padding:40px 20px; background: var(--cymb-white); border:1px dashed var(--cymb-border-color); border-radius:16px;">
                                    <i class="fa-solid fa-comments" style="font-size:32px; color:#94A3B8; margin-bottom:12px; display:inline-block;"></i>
                                    <p style="color:#64748B; margin:0; font-size:14px;"><?php _e('Please request a treatment package consultation to start a chat with our coordinators.', 'caretochina-booking'); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- TAB 6: LOGOUT -->
                    <div class="ctc-dash-panel" id="dash-panel-logout">
                        <div class="ctc-panel-card ctc-logout-card">
                            <div class="ctc-logout-icon-wrap">
                                <i class="fa-solid fa-right-from-bracket"></i>
                            </div>
                            <h3 class="ctc-card-title" style="margin-bottom:10px;"><?php _e('Sign Out of Patient Account', 'caretochina-booking'); ?></h3>
                            <p class="ctc-logout-desc" style="margin-bottom:24px;"><?php _e('Are you sure you want to log out of your CareToChina patient portal?', 'caretochina-booking'); ?></p>
                            <a href="<?php echo esc_url($logout_url); ?>" class="ctc-solid-btn btn-danger-solid">
                                <i class="fa-solid fa-right-from-bracket"></i> <?php _e('Log Out Now', 'caretochina-booking'); ?>
                            </a>
                        </div>
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

    public function handle_update_patient_profile() {
        $current_user = wp_get_current_user();
        if (!$current_user->exists()) {
            wp_send_json_error(['message' => __('Not logged in.', 'caretochina-booking')]);
        }

        $display_name = sanitize_text_field($_POST['display_name'] ?? '');
        $phone        = sanitize_text_field($_POST['phone'] ?? '');
        $gender       = sanitize_text_field($_POST['gender'] ?? '');
        $age          = isset($_POST['age']) && $_POST['age'] !== '' ? intval($_POST['age']) : null;
        $whatsapp     = sanitize_text_field($_POST['whatsapp'] ?? '');
        $wechat       = sanitize_text_field($_POST['wechat'] ?? '');
        $messenger    = sanitize_text_field($_POST['messenger'] ?? '');
        $linkedin     = sanitize_text_field($_POST['linkedin'] ?? '');

        if (empty($display_name) || empty($phone) || empty($gender)) {
            wp_send_json_error(['message' => __('Name, phone number and gender are required.', 'caretochina-booking')]);
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
            'message' => __('Profile updated successfully!', 'caretochina-booking'),
            'has_custom_avatar' => $has_custom_avatar,
            'new_avatar_url' => $new_avatar_url
        ]);
    }

    public function get_patient_chat_html($booking_id) {
        global $wpdb;
        $table_messages = $wpdb->prefix . 'caretochina_messages';

        $wpdb->query($wpdb->prepare("UPDATE $table_messages SET is_read = 1 WHERE booking_id = %d AND sender_type = %s", $booking_id, 'coordinator'));

        $messages = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_messages WHERE booking_id = %d ORDER BY id ASC", $booking_id));

        $chat_html = '';
        if (empty($messages)) {
            $chat_html .= '<div class="chat-msg coordinator mb-14" style="display:flex; gap:12px; align-items:flex-start; margin-bottom:14px;"><img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=100&q=80" alt="Coordinator" style="width:36px; height:36px; border-radius:50%;"><div class="msg-bubble" style="background:#0F766E; color:#FFF; border:none; padding:12px 18px; border-radius:18px; font-size:13px; line-height:1.4;"><strong>Elena (Care Coordinator):</strong> ' . __('Hello! I am Elena, your assigned Care Coordinator. How can I assist with your medical itinerary today?', 'caretochina-booking') . '</div></div>';
        } else {
            foreach ($messages as $m) {
                if ($m->is_read == 1) {
                    $read_tick = '<span style="color:#3B82F6; margin-left:6px; font-weight:800;" title="' . esc_attr(__('Seen by Coordinator', 'caretochina-booking')) . '">✓✓ Seen</span>';
                } else {
                    $read_tick = '<span style="color:#94A3B8; margin-left:6px; font-weight:600;" title="' . esc_attr(__('Delivered', 'caretochina-booking')) . '">✓ Delivered</span>';
                }

                if ($m->sender_type === 'coordinator') {
                    $staff_name = 'roji';
                    if (preg_match('/Staff \((.+)\)/', $m->sender_name, $matches)) {
                        $staff_name = $matches[1];
                    }
                    $chat_html .= '<div class="chat-msg coordinator mb-14" style="display:flex; justify-content:flex-start; margin-bottom:14px; font-family:\'Inter\', sans-serif; width:100%;">
                        <div class="msg-bubble" style="background:#E2E8F0; color:#0F172A; padding:10px 16px; border-radius:18px 18px 18px 2px; font-size:13px; max-width:80%; line-height:1.4;">
                            <span style="font-weight:700; color:#0F766E; margin-right:4px;">Staff(' . esc_html($staff_name) . '):</span> ' . esc_html($m->message) . '
                        </div>
                    </div>';
                } else {
                    $pat_name = $m->sender_name;
                    $chat_html .= '<div class="chat-msg patient mb-14" style="display:flex; justify-content:flex-end; margin-bottom:14px; text-align:right; font-family:\'Inter\', sans-serif; width:100%;">
                        <div class="msg-bubble" style="background:#0F766E; color:#FFF; padding:10px 16px; border-radius:18px 18px 2px 18px; font-size:13px; max-width:80%; line-height:1.4; display:inline-block; text-align:left; border:none;">
                            ' . esc_html($m->message) . ' <span style="font-size:11px; font-weight:700; color:#CCFBF1; margin-left:6px;">Patient(' . esc_html($pat_name) . ')</span>
                            <div style="font-size:9px; text-align:right; margin-top:4px; opacity:0.8;">' . $read_tick . '</div>
                        </div>
                    </div>';
                }
            }
        }
        return $chat_html;
    }

    public function handle_patient_message() {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_booking_nonce') && !wp_verify_nonce($nonce, 'careyou_booking_nonce')) {
            wp_send_json_error(['message' => __('Security verification failed.', 'caretochina-booking')]);
        }

        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';
        $booking_id = intval($_POST['booking_id'] ?? 0);
        
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_bookings WHERE id = %d", $booking_id));
        if (!$booking || $booking->patient_id != get_current_user_id()) {
            wp_send_json_error(['message' => __('Access denied. You do not own this booking case.', 'caretochina-booking')]);
        }

        $table_messages = $wpdb->prefix . 'caretochina_messages';
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        $current_user = wp_get_current_user();
        $sender_name = $current_user->exists() ? $current_user->display_name : 'Patient';

        if ($booking_id > 0 && !empty($message)) {
            $wpdb->insert($table_messages, [
                'booking_id'  => $booking_id,
                'sender_type' => 'patient',
                'sender_name' => $sender_name,
                'message'     => $message,
                'is_read'     => 0,
                'created_at'  => current_time('mysql'),
            ]);
            
            $html = $this->get_patient_chat_html($booking_id);
            wp_send_json_success(['message' => $message, 'html' => $html]);
        }
        wp_send_json_error(['message' => __('Invalid message submission.', 'caretochina-booking')]);
    }

    public function handle_patient_typing() {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_booking_nonce') && !wp_verify_nonce($nonce, 'careyou_booking_nonce')) {
            wp_send_json_error(['message' => __('Security verification failed.', 'caretochina-booking')]);
        }
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';
        $booking_id = intval($_POST['booking_id'] ?? 0);
        
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_bookings WHERE id = %d", $booking_id));
        if (!$booking || $booking->patient_id != get_current_user_id()) {
            wp_send_json_error(['message' => __('Access denied.', 'caretochina-booking')]);
        }
        if ($booking_id > 0) {
            set_transient('ctc_typing_' . $booking_id . '_patient', 1, 4);
            wp_send_json_success();
        }
        wp_send_json_error();
    }

    public function handle_get_patient_chat() {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_booking_nonce') && !wp_verify_nonce($nonce, 'careyou_booking_nonce')) {
            wp_send_json_error(['message' => __('Security verification failed.', 'caretochina-booking')]);
        }

        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';
        $booking_id = intval($_POST['booking_id'] ?? 0);
        
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_bookings WHERE id = %d", $booking_id));
        if (!$booking || $booking->patient_id != get_current_user_id()) {
            wp_send_json_error(['message' => __('Access denied. You do not own this booking case.', 'caretochina-booking')]);
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
        wp_send_json_error(['message' => __('Invalid booking ID', 'caretochina-booking')]);
    }

    public function handle_patient_avatar_upload() {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_booking_nonce') && !wp_verify_nonce($nonce, 'careyou_booking_nonce')) {
            wp_send_json_error(['message' => __('Security verification failed.', 'caretochina-booking')]);
        }

        $current_user = wp_get_current_user();
        if (!$current_user->exists()) {
            wp_send_json_error(['message' => __('Not logged in.', 'caretochina-booking')]);
        }
        $user_id = $current_user->ID;

        if (empty($_FILES['avatar'])) {
            wp_send_json_error(['message' => __('No file uploaded.', 'caretochina-booking')]);
        }

        $file = $_FILES['avatar'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(['message' => __('Upload failed with error code: ', 'caretochina-booking') . $file['error']]);
        }

        // Limit size: 2MB (2 * 1024 * 1024 bytes)
        $max_size = 2 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            wp_send_json_error(['message' => __('Image size must be less than 2 MB.', 'caretochina-booking')]);
        }

        // Allowed extensions
        $allowed_exts = ['png', 'jpg', 'jpeg', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_exts)) {
            wp_send_json_error(['message' => __('Only PNG, JPG, and WEBP images are allowed.', 'caretochina-booking')]);
        }

        // Allowed mime types
        $allowed_mimes = ['image/png', 'image/jpeg', 'image/pjpeg', 'image/x-png', 'image/webp'];
        if (!in_array($file['type'], $allowed_mimes)) {
            wp_send_json_error(['message' => __('Invalid file type. Only PNG, JPG, and WEBP images are allowed.', 'caretochina-booking')]);
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
                    @unlink($old_file_path);
                }
            }
        }

        $upload_overrides = ['test_form' => false];
        $movefile = wp_handle_upload($file, $upload_overrides);

        if ($movefile && !isset($movefile['error'])) {
            $new_avatar_url = $movefile['url'];
            update_user_meta($user_id, 'patient_avatar', $new_avatar_url);
            wp_send_json_success([
                'message' => __('Profile photo uploaded successfully!', 'caretochina-booking'),
                'avatar_url' => $new_avatar_url
            ]);
        } else {
            wp_send_json_error(['message' => $movefile['error']]);
        }
    }

    public function handle_patient_delete_own_account() {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_booking_nonce') && !wp_verify_nonce($nonce, 'careyou_booking_nonce')) {
            wp_send_json_error(['message' => __('Security verification failed.', 'caretochina-booking')]);
        }

        $current_user = wp_get_current_user();
        if (!$current_user->exists()) {
            wp_send_json_error(['message' => __('Not logged in.', 'caretochina-booking')]);
        }
        $user_id = $current_user->ID;

        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';
        $table_messages = $wpdb->prefix . 'caretochina_messages';

        // 1. Fetch user bookings
        $bookings = $wpdb->get_col($wpdb->prepare("SELECT id FROM $table_bookings WHERE patient_id = %d", $user_id));
        if (!empty($bookings)) {
            // Delete messages for each booking
            foreach ($bookings as $b_id) {
                $wpdb->delete($table_messages, ['booking_id' => $b_id]);
            }
            // Delete bookings
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
                    @unlink($old_file_path);
                }
            }
        }

        // 3. Log out current user session
        wp_logout();

        // 4. Delete the WordPress user account
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($user_id);

        wp_send_json_success([
            'message' => __('Your account and data have been permanently deleted.', 'caretochina-booking'),
            'redirect' => home_url('/')
        ]);
    }
}