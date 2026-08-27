<?php
if (!defined('ABSPATH')) {
    exit;
}

class CareToChina_Booking_Auth {
    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('init', [$this, 'ensure_patient_role']);

        // Security & Access Control: Restrict Patients from /wp-admin & Hide Admin Bar
        add_action('admin_init', [$this, 'restrict_patient_admin_access']);
        add_action('after_setup_theme', [$this, 'hide_admin_bar_for_patients']);
        add_action('wp', [$this, 'hide_admin_bar_for_patients']);
        add_filter('login_redirect', [$this, 'custom_login_redirect'], 10, 3);
        add_action('template_redirect', [$this, 'redirect_logged_in_user']);
        add_action('wp_login', [$this, 'on_user_logged_in'], 10, 2);

        // Shortcodes
        add_shortcode('caretochina_auth_portal', [$this, 'render_auth_portal']);
        add_shortcode('careyou_auth', [$this, 'render_auth_portal']);

        add_shortcode('caretochina_booking_login', [$this, 'render_login_form']);
        add_shortcode('careyou_booking_login', [$this, 'render_login_form']);

        add_shortcode('caretochina_booking_register', [$this, 'render_register_form']);
        add_shortcode('careyou_booking_register', [$this, 'render_register_form']);

        // AJAX Actions
        add_action('wp_ajax_caretochina_user_login', [$this, 'handle_login']);
        add_action('wp_ajax_nopriv_caretochina_user_login', [$this, 'handle_login']);

        add_action('wp_ajax_caretochina_user_register', [$this, 'handle_register']);
        add_action('wp_ajax_nopriv_caretochina_user_register', [$this, 'handle_register']);

        // Legacy AJAX aliases
        add_action('wp_ajax_careyou_user_login', [$this, 'handle_login']);
        add_action('wp_ajax_nopriv_careyou_user_login', [$this, 'handle_login']);
        add_action('wp_ajax_careyou_user_register', [$this, 'handle_register']);
        add_action('wp_ajax_nopriv_careyou_user_register', [$this, 'handle_register']);
    }

    public function ensure_patient_role() {
        if (!get_role('patient')) {
            add_role('patient', __('Patient', 'caretochina-medical'), [
                'read'         => true,
                'upload_files' => true,
                'edit_posts'   => false,
            ]);
        }
    }

    public function restrict_patient_admin_access() {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if (in_array('patient', (array) $user->roles) && !current_user_can('manage_options')) {
                $dash_url = class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_url('patient_dashboard') : home_url('/patient-dashboard/');
                wp_safe_redirect($dash_url);
                exit;
            }
        }
    }

    public function hide_admin_bar_for_patients() {
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if (in_array('patient', (array) $user->roles) && !current_user_can('manage_options')) {
                show_admin_bar(false);
            }
        }
    }

    public function redirect_logged_in_user() {
        if (is_user_logged_in()) {
            $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
            if (is_page('patient-login') || strpos($request_uri, 'patient-login') !== false) {
                $dash_url = class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_url('patient_dashboard') : home_url('/patient-dashboard/');
                wp_safe_redirect($dash_url);
                exit;
            }
        }
    }

    public function custom_login_redirect($redirect_to, $request, $user) {
        if (isset($user->roles) && is_array($user->roles)) {
            if (in_array('patient', $user->roles) && !current_user_can('manage_options')) {
                return class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_url('patient_dashboard') : home_url('/patient-dashboard/');
            }
        }
        return $redirect_to;
    }

    public function on_user_logged_in($user_login, $user) {
        if (isset($user->ID, $user->user_email)) {
            self::link_guest_bookings_to_user($user->ID, $user->user_email);
        }
    }

    public function render_auth_portal() {
        ob_start();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $default_tab       = (isset($_GET['tab']) && sanitize_key(wp_unslash($_GET['tab'])) === 'register') ? 'register' : 'login';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $prefill_name      = isset($_GET['prefill_name']) ? sanitize_text_field(wp_unslash($_GET['prefill_name'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $prefill_email     = isset($_GET['prefill_email']) ? sanitize_email(wp_unslash($_GET['prefill_email'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $prefill_phone     = isset($_GET['prefill_phone']) ? sanitize_text_field(wp_unslash($_GET['prefill_phone'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $prefill_gender    = isset($_GET['prefill_gender']) ? sanitize_text_field(wp_unslash($_GET['prefill_gender'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $prefill_age       = (isset($_GET['prefill_age']) && $_GET['prefill_age'] !== '') ? absint(wp_unslash($_GET['prefill_age'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $prefill_specialty = isset($_GET['prefill_specialty']) ? sanitize_text_field(wp_unslash($_GET['prefill_specialty'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $prefill_whatsapp  = isset($_GET['prefill_whatsapp']) ? sanitize_text_field(wp_unslash($_GET['prefill_whatsapp'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $prefill_wechat    = isset($_GET['prefill_wechat']) ? sanitize_text_field(wp_unslash($_GET['prefill_wechat'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $prefill_code      = isset($_GET['booking_code']) ? sanitize_text_field(wp_unslash($_GET['booking_code'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $auth_error        = isset($_GET['ctc_auth_error']) ? sanitize_text_field(wp_unslash($_GET['ctc_auth_error'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $link_email        = isset($_GET['email']) ? sanitize_email(wp_unslash($_GET['email'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $link_token        = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $link_error        = isset($_GET['ctc_link_error']) ? sanitize_text_field(wp_unslash($_GET['ctc_link_error'])) : '';
        ?>
        <div class="careyou-auth-container caretochina-auth-container">
            <!-- MAIN AUTH CARD CONTAINER -->
            <div class="ctc-auth-card">
                <?php if (!empty($auth_error)) : ?>
                    <div class="auth-error-alert" style="background:#FEE2E2; color:#991B1B; border:1px solid #FCA5A5; padding:12px 16px; border-radius:10px; font-size:13px; margin-bottom:18px; display:flex; align-items:center; gap:10px;">
                        <i class="fa-solid fa-triangle-exclamation" style="font-size:18px;"></i>
                        <span><?php echo esc_html($auth_error); ?></span>
                    </div>
                <?php endif; ?>

                <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                if (!empty($_GET['ctc_link_account']) && !empty($link_token)) : ?>
                    <!-- EXPLICIT ACCOUNT LINKING / PASSWORD CONFIRMATION PANEL -->
                    <div id="auth-panel-link-account" class="auth-panel" style="display:block;">
                        <div class="auth-header">
                            <div class="auth-icon-badge" style="background:#CCFBF1; color:#0F766E;">
                                <i class="fa-solid fa-shield-halved"></i>
                            </div>
                            <h3 class="auth-title"><?php esc_html_e('Link Google Account', 'caretochina-medical'); ?></h3>
                            <p class="auth-subtitle"><?php esc_html_e('Confirm your patient password to link Google Sign-In securely', 'caretochina-medical'); ?></p>
                        </div>

                        <?php if (!empty($link_error)) : ?>
                            <div style="background:#FEF2F2; color:#991B1B; border:1px solid #F87171; padding:10px 14px; border-radius:8px; font-size:13px; margin-bottom:16px;">
                                <i class="fa-solid fa-circle-exclamation"></i> <?php echo esc_html($link_error); ?>
                            </div>
                        <?php endif; ?>

                        <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:12px; padding:14px; margin-bottom:18px; font-size:13px; color:#475569; line-height:1.5;">
                            <i class="fa-solid fa-circle-info" style="color:#0F766E;"></i> 
                            <?php
                            /* translators: %s: Patient email */
                            printf(wp_kses(__('An existing patient account exists for <strong>%s</strong>. To prevent account takeover and enable instant 1-click Google Sign-In for future visits, please enter your existing account password once:', 'caretochina-medical'), ['strong' => []]), esc_html($link_email));
                            ?>
                        </div>

                        <form method="post" action="">
                            <?php wp_nonce_field('ctc_link_account_action', 'ctc_link_nonce'); ?>
                            <input type="hidden" name="link_token" value="<?php echo esc_attr($link_token); ?>">

                            <div class="form-group" style="margin-bottom:16px;">
                                <label class="form-label"><?php esc_html_e('Existing Account Password *', 'caretochina-medical'); ?></label>
                                <input type="password" name="account_password" class="form-input" placeholder="••••••••" required autofocus>
                            </div>

                            <button type="submit" name="ctc_submit_link_account" value="1" class="auth-submit-btn" style="width:100%; margin-bottom:12px;">
                                <i class="fa-solid fa-link"></i> <?php esc_html_e('Verify Password & Link Google', 'caretochina-medical'); ?>
                            </button>

                            <div style="text-align:center;">
                                <a href="<?php echo esc_url(home_url('/patient-login/')); ?>" style="color:#64748B; font-size:13px; text-decoration:none;">
                                    <i class="fa-solid fa-arrow-left"></i> <?php esc_html_e('Cancel and return to Sign In', 'caretochina-medical'); ?>
                                </a>
                            </div>
                        </form>
                    </div>
                <?php else : ?>

                <!-- TABBED SWITCHER -->
                <div class="auth-tab-bar">
                    <button type="button" class="auth-tab-btn <?php echo ($default_tab === 'login') ? 'active' : ''; ?>" onclick="switchAuthTab('login')" id="tab-btn-login">
                        <i class="fa-solid fa-right-to-bracket"></i> <?php esc_html_e('Sign In', 'caretochina-medical'); ?>
                    </button>
                    <button type="button" class="auth-tab-btn <?php echo ($default_tab === 'register') ? 'active' : ''; ?>" onclick="switchAuthTab('register')" id="tab-btn-register">
                        <i class="fa-solid fa-user-plus"></i> <?php esc_html_e('Register Patient', 'caretochina-medical'); ?>
                    </button>
                </div>

                <!-- LOGIN TAB PANEL -->
                <div id="auth-panel-login" class="auth-panel" style="display:<?php echo ($default_tab === 'login') ? 'block' : 'none'; ?>;">
                    <div class="auth-header">
                        <div class="auth-icon-badge">
                            <i class="fa-solid fa-hospital-user"></i>
                        </div>
                        <h3 class="auth-title"><?php esc_html_e('Sign In to Patient Portal', 'caretochina-medical'); ?></h3>
                        <p class="auth-subtitle"><?php esc_html_e('Access your medical travel itinerary, timeline & coordinator chat', 'caretochina-medical'); ?></p>
                    </div>

                    <?php if (class_exists('CareToChina_Google_Login') && CareToChina_Google_Login::is_enabled()) : ?>
                        <div class="google-auth-wrapper" style="margin-bottom:20px; text-align:center;">
                            <a href="<?php echo esc_url(CareToChina_Google_Login::get_auth_url()); ?>" class="ctc-google-btn" style="display:flex; align-items:center; justify-content:center; gap:12px; width:100%; background:#FFFFFF; color:#1F2937; border:1.5px solid #E5E7EB; padding:12px 18px; border-radius:12px; font-weight:700; font-size:14px; text-decoration:none; box-shadow:0 2px 6px rgba(0,0,0,0.06); transition:all 0.2s;">
                                <svg width="18" height="18" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
                                <?php esc_html_e('Continue with Google', 'caretochina-medical'); ?>
                            </a>
                            <div class="auth-divider" style="display:flex; align-items:center; text-align:center; margin:18px 0; color:#9CA3AF; font-size:12px; font-weight:600;">
                                <span style="flex:1; border-bottom:1px solid #E5E7EB;"></span>
                                <span style="padding:0 12px; text-transform:uppercase; letter-spacing:0.5px;"><?php esc_html_e('or sign in with email', 'caretochina-medical'); ?></span>
                                <span style="flex:1; border-bottom:1px solid #E5E7EB;"></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form id="careyou-auth-login-form">
                        <div class="form-group">
                            <label class="form-label"><?php esc_html_e('Email Address or Username *', 'caretochina-medical'); ?></label>
                            <input type="text" name="log" class="form-input" value="<?php echo esc_attr($prefill_email); ?>" placeholder="e.g. sarah@example.com" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php esc_html_e('Password *', 'caretochina-medical'); ?></label>
                            <input type="password" name="pwd" class="form-input" placeholder="••••••••" required>
                        </div>
                        <div class="auth-aux-row" style="margin-bottom:14px">
                            <label class="remember-me-label">
                                <input type="checkbox" name="remember" value="forever" checked> <?php esc_html_e('Remember me', 'caretochina-medical'); ?>
                            </label>
                        </div>

                        <?php if (class_exists('CareToChina_Recaptcha')) { echo wp_kses_post(CareToChina_Recaptcha::render_field('login')); } ?>

                        <button type="submit" id="login_submit_btn" class="auth-submit-btn">
                            <i class="fa-solid fa-right-to-bracket"></i> <?php esc_html_e('Sign In to Account', 'caretochina-medical'); ?>
                        </button>
                    </form>
                    <div id="login-response-box" class="auth-response-box" style="display:none;"></div>
                </div>

                <!-- REGISTER TAB PANEL -->
                <div id="auth-panel-register" class="auth-panel" style="display:<?php echo ($default_tab === 'register') ? 'block' : 'none'; ?>;">
                    <div class="auth-header">
                        <div class="auth-icon-badge">
                            <i class="fa-solid fa-user-plus"></i>
                        </div>
                        <h3 class="auth-title"><?php esc_html_e('Create Patient Account', 'caretochina-medical'); ?></h3>
                        <p class="auth-subtitle"><?php esc_html_e('Registered as Patient • Track treatment roadmap & medical vault', 'caretochina-medical'); ?></p>
                    </div>

                    <?php if (!empty($prefill_code)) : ?>
                        <div class="ctc-guest-prefill-alert" style="background:#F0FDF4; border:1px solid #BBF7D0; padding:12px 16px; border-radius:12px; margin-bottom:20px; color:#166534; font-size:13px; display:flex; align-items:center; gap:10px;">
                            <i class="fa-solid fa-circle-check" style="font-size:18px; color:#16A34A; flex-shrink:0;"></i>
                            <div>
                                <strong><?php
                                /* translators: %s: Case code */
                                printf(esc_html__('Linking Case #%s', 'caretochina-medical'), esc_html($prefill_code));
                                ?></strong><br>
                                <span><?php esc_html_e('Your inquiry details have been prefilled. Set a password to save your profile and continue your live chat.', 'caretochina-medical'); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (class_exists('CareToChina_Google_Login') && CareToChina_Google_Login::is_enabled()) : ?>
                        <div class="google-auth-wrapper" style="margin-bottom:20px; text-align:center;">
                            <a href="<?php echo esc_url(CareToChina_Google_Login::get_auth_url()); ?>" class="ctc-google-btn" style="display:flex; align-items:center; justify-content:center; gap:12px; width:100%; background:#FFFFFF; color:#1F2937; border:1.5px solid #E5E7EB; padding:12px 18px; border-radius:12px; font-weight:700; font-size:14px; text-decoration:none; box-shadow:0 2px 6px rgba(0,0,0,0.06); transition:all 0.2s;">
                                <svg width="18" height="18" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
                                <?php esc_html_e('Sign up with Google', 'caretochina-medical'); ?>
                            </a>
                            <div class="auth-divider" style="display:flex; align-items:center; text-align:center; margin:18px 0; color:#9CA3AF; font-size:12px; font-weight:600;">
                                <span style="flex:1; border-bottom:1px solid #E5E7EB;"></span>
                                <span style="padding:0 12px; text-transform:uppercase; letter-spacing:0.5px;"><?php esc_html_e('or create with email', 'caretochina-medical'); ?></span>
                                <span style="flex:1; border-bottom:1px solid #E5E7EB;"></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form id="careyou-auth-register-form">
                        <div class="ctc-form-grid-2">
                            <div class="form-group">
                                <label class="form-label"><?php esc_html_e('Full Name *', 'caretochina-medical'); ?></label>
                                <input type="text" name="user_name" class="form-input" value="<?php echo esc_attr($prefill_name); ?>" placeholder="e.g. Sarah Jenkins" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?php esc_html_e('Email Address *', 'caretochina-medical'); ?></label>
                                <input type="email" name="user_email" class="form-input" value="<?php echo esc_attr($prefill_email); ?>" placeholder="sarah@example.com" required>
                            </div>
                        </div>

                        <div class="ctc-form-grid-2">
                            <div class="form-group">
                                <label class="form-label"><?php esc_html_e('Phone Number *', 'caretochina-medical'); ?></label>
                                <?php 
                                if (class_exists('CareToChina_Country_Helper')) { 
                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped HTML from CareToChina_Country_Helper.
                                    echo CareToChina_Country_Helper::render_phone_input_group('user_phone', $prefill_phone, true, '+1 (800) 555-0199', 'reg_user_phone'); 
                                } else { 
                                    echo '<input type="tel" name="user_phone" class="form-input" value="' . esc_attr($prefill_phone) . '" placeholder="+1 (800) 555-0199" required>'; 
                                } 
                                ?>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?php esc_html_e('Age', 'caretochina-medical'); ?></label>
                                <input type="number" name="user_age" class="form-input" value="<?php echo esc_attr($prefill_age); ?>" placeholder="e.g. 28">
                            </div>
                        </div>

                        <div class="ctc-form-grid-2">
                            <div class="form-group">
                                <label class="form-label"><?php esc_html_e('Gender *', 'caretochina-medical'); ?></label>
                                <select name="user_gender" class="form-select" required>
                                    <option value=""><?php esc_html_e('Select Gender', 'caretochina-medical'); ?></option>
                                    <option value="Male" <?php selected($prefill_gender, 'Male'); ?>><?php esc_html_e('Male', 'caretochina-medical'); ?></option>
                                    <option value="Female" <?php selected($prefill_gender, 'Female'); ?>><?php esc_html_e('Female', 'caretochina-medical'); ?></option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?php esc_html_e('Preferred Medical Specialty', 'caretochina-medical'); ?></label>
                                <select name="user_specialty" class="form-select">
                                    <option value=""><?php esc_html_e('Select Specialty', 'caretochina-medical'); ?></option>
                                    <?php
                                    $specs = get_terms([
                                        'taxonomy' => 'hospital_specialty',
                                        'hide_empty' => false,
                                    ]);
                                    if (!is_wp_error($specs) && !empty($specs)) {
                                        foreach ($specs as $spec) {
                                            $is_sel = ($prefill_specialty && (strcasecmp($prefill_specialty, $spec->name) === 0 || stripos($prefill_specialty, $spec->name) !== false));
                                            echo '<option value="' . esc_attr($spec->name) . '" ' . ($is_sel ? 'selected="selected"' : '') . '>' . esc_html($spec->name) . '</option>';
                                        }
                                    } else {
                                        $default_specs = [
                                            'Cardiology & Heart', 'Orthopedics & Joints', 'Oncology & Cancer', 
                                            'Dental Implants', 'Neurosurgery', 'Fertility & IVF', 'General Consultation'
                                        ];
                                        foreach ($default_specs as $ds) {
                                            $is_sel = ($prefill_specialty && strcasecmp($prefill_specialty, $ds) === 0);
                                            echo '<option value="' . esc_attr($ds) . '" ' . ($is_sel ? 'selected="selected"' : '') . '>' . esc_html($ds) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <h4 style="margin: 10px 0 16px 0; font-family:var(--cymb-font-heading); font-size:15px; border:1px solid transparent; border-bottom: 1px solid var(--cymb-border-color); padding-bottom: 6px; color: var(--cymb-text-dark);"><?php esc_html_e('Social Accounts (Optional)', 'caretochina-medical'); ?></h4>

                        <div class="ctc-form-grid-2">
                            <div class="form-group">
                                <label class="form-label"><?php esc_html_e('WhatsApp', 'caretochina-medical'); ?></label>
                                <?php 
                                if (class_exists('CareToChina_Country_Helper')) { 
                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped HTML from CareToChina_Country_Helper.
                                    echo CareToChina_Country_Helper::render_phone_input_group('user_whatsapp', $prefill_whatsapp, false, '+1 (800) 555-0199', 'reg_user_whatsapp'); 
                                } else { 
                                    echo '<input type="tel" name="user_whatsapp" class="form-input" value="' . esc_attr($prefill_whatsapp) . '" placeholder="+1 (800) 555-0199">'; 
                                } 
                                ?>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?php esc_html_e('WeChat', 'caretochina-medical'); ?></label>
                                <input type="text" name="user_wechat" class="form-input" value="<?php echo esc_attr($prefill_wechat); ?>" placeholder="WeChat ID">
                            </div>
                        </div>

                        <div class="ctc-form-grid-2">
                            <div class="form-group">
                                <label class="form-label"><?php esc_html_e('Messenger', 'caretochina-medical'); ?></label>
                                <input type="text" name="user_messenger" class="form-input" placeholder="Messenger Link / ID">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?php esc_html_e('LinkedIn', 'caretochina-medical'); ?></label>
                                <input type="text" name="user_linkedin" class="form-input" placeholder="LinkedIn URL">
                            </div>
                        </div>

                        <div class="ctc-form-grid-2">
                            <div class="form-group">
                                <label class="form-label"><?php esc_html_e('Password *', 'caretochina-medical'); ?></label>
                                <input type="password" name="user_pass" id="reg_user_pass" class="form-input" placeholder="••••••••" minlength="6" maxlength="20" required autocomplete="new-password">
                                <div id="reg_pass_rules" style="font-size:11px; color:#64748B; margin-top:4px; line-height:1.3;">
                                    <i class="fa-solid fa-shield-halved"></i> <?php esc_html_e('6–20 characters (a-z, A-Z, 0-9), no spaces allowed', 'caretochina-medical'); ?>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?php esc_html_e('Confirm Password *', 'caretochina-medical'); ?></label>
                                <input type="password" name="user_pass_confirm" id="reg_user_pass_confirm" class="form-input" placeholder="••••••••" minlength="6" maxlength="20" required autocomplete="new-password">
                                <div id="reg_pass_match_msg" style="font-size:11px; margin-top:4px; display:none; line-height:1.3;"></div>
                            </div>
                        </div>

                        <?php if (class_exists('CareToChina_Recaptcha')) { echo wp_kses_post(CareToChina_Recaptcha::render_field('register')); } ?>

                        <button type="submit" id="reg_submit_btn" class="auth-submit-btn">
                            <i class="fa-solid fa-user-plus"></i> <?php esc_html_e('Register Patient Account', 'caretochina-medical'); ?>
                        </button>
                    </form>
                    <div id="reg-response-box" class="auth-response-box" style="display:none;"></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
        function switchAuthTab(tab) {
            jQuery('.auth-tab-btn').removeClass('active');
            jQuery('#tab-btn-' + tab).addClass('active');

            jQuery('.auth-panel').hide();
            jQuery('#auth-panel-' + tab).fadeIn(200);
        }
        </script>
        <?php
        return ob_get_clean();
    }

    public function render_login_form() {
        return $this->render_auth_portal();
    }

    public function render_register_form() {
        ob_start();
        echo '<script>jQuery(document).ready(function(){ switchAuthTab("register"); });</script>';
        echo wp_kses_post($this->render_auth_portal());
        return ob_get_clean();
    }

    public function handle_login() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'caretochina_booking_nonce') && !wp_verify_nonce($nonce, 'careyou_booking_nonce')) {
            wp_send_json_error(['message' => __('Security verification failed.', 'caretochina-medical')]);
        }

        // Google reCAPTCHA Verification (if enabled for login)
        if (class_exists('CareToChina_Recaptcha')) {
            $recaptcha_token = isset($_POST['g-recaptcha-response']) ? sanitize_text_field(wp_unslash($_POST['g-recaptcha-response'])) : '';
            $rc_check = CareToChina_Recaptcha::verify_submission($recaptcha_token, 'login');
            if (is_wp_error($rc_check)) {
                wp_send_json_error(['message' => $rc_check->get_error_message()]);
            }
        }

        $log      = isset($_POST['log']) ? sanitize_text_field(wp_unslash($_POST['log'])) : '';
        $pwd      = isset($_POST['pwd']) ? sanitize_text_field(wp_unslash($_POST['pwd'])) : '';
        $remember = isset($_POST['remember']) && sanitize_text_field(wp_unslash($_POST['remember'])) === 'forever';

        if (empty($log) || empty($pwd)) {
            wp_send_json_error(['message' => __('Please enter both username/email and password.', 'caretochina-medical')]);
        }

        $user = wp_signon(['user_login' => $log, 'user_password' => $pwd, 'remember' => $remember], is_ssl());

        if (is_wp_error($user)) {
            wp_send_json_error(['message' => __('Invalid credentials. Please check your username/email and password.', 'caretochina-medical')]);
        } else {
            // Auto-link any prior guest bookings by verified email
            self::link_guest_bookings_to_user($user->ID, $user->user_email);

            $dash_url = class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_url('patient_dashboard') : home_url('/patient-dashboard/');
            $dash_url = add_query_arg('tab', 'messages', $dash_url);
            wp_send_json_success([
                'message'  => __('Login successful! Redirecting to your Live Chat & Consultation...', 'caretochina-medical'),
                'redirect' => $dash_url
            ]);
        }
    }

    public function handle_register() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'caretochina_booking_nonce') && !wp_verify_nonce($nonce, 'careyou_booking_nonce')) {
            wp_send_json_error(['message' => __('Security verification failed.', 'caretochina-medical')]);
        }

        // Google reCAPTCHA Verification (if enabled for register)
        if (class_exists('CareToChina_Recaptcha')) {
            $recaptcha_token = isset($_POST['g-recaptcha-response']) ? sanitize_text_field(wp_unslash($_POST['g-recaptcha-response'])) : '';
            $rc_check = CareToChina_Recaptcha::verify_submission($recaptcha_token, 'register');
            if (is_wp_error($rc_check)) {
                wp_send_json_error(['message' => $rc_check->get_error_message()]);
            }
        }

        $name         = isset($_POST['user_name']) ? sanitize_text_field(wp_unslash($_POST['user_name'])) : '';
        $email        = isset($_POST['user_email']) ? sanitize_email(wp_unslash($_POST['user_email'])) : '';
        $phone        = class_exists('CareToChina_Country_Helper') ? CareToChina_Country_Helper::extract_submitted_phone($_POST, 'user_phone') : (isset($_POST['user_phone']) ? sanitize_text_field(wp_unslash($_POST['user_phone'])) : '');
        $specialty    = isset($_POST['user_specialty']) ? sanitize_text_field(wp_unslash($_POST['user_specialty'])) : 'General Consultation';
        $gender       = isset($_POST['user_gender']) ? sanitize_text_field(wp_unslash($_POST['user_gender'])) : '';
        $age          = isset($_POST['user_age']) && $_POST['user_age'] !== '' ? absint(wp_unslash($_POST['user_age'])) : null;
        $whatsapp     = class_exists('CareToChina_Country_Helper') ? CareToChina_Country_Helper::extract_submitted_phone($_POST, 'user_whatsapp') : (isset($_POST['user_whatsapp']) ? sanitize_text_field(wp_unslash($_POST['user_whatsapp'])) : '');
        $wechat       = isset($_POST['user_wechat']) ? sanitize_text_field(wp_unslash($_POST['user_wechat'])) : '';
        $messenger    = isset($_POST['user_messenger']) ? sanitize_text_field(wp_unslash($_POST['user_messenger'])) : '';
        $linkedin     = isset($_POST['user_linkedin']) ? sanitize_text_field(wp_unslash($_POST['user_linkedin'])) : '';
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords must preserve special characters and are hashed securely.
        $pass         = isset($_POST['user_pass']) ? wp_unslash($_POST['user_pass']) : '';
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords must preserve special characters and are hashed securely.
        $pass_confirm = isset($_POST['user_pass_confirm']) ? wp_unslash($_POST['user_pass_confirm']) : '';

        if (empty($name) || empty($email) || empty($phone) || empty($gender) || empty($pass)) {
            wp_send_json_error(['message' => __('Please fill in all required fields (Name, Email, Phone, Gender, Password).', 'caretochina-medical')]);
        }

        if ($pass !== $pass_confirm) {
            wp_send_json_error(['message' => __('Passwords do not match. Please verify your password.', 'caretochina-medical')]);
        }

        if (preg_match('/\s/', $pass)) {
            wp_send_json_error(['message' => __('Password cannot contain spaces or whitespace.', 'caretochina-medical')]);
        }

        $pass_len = strlen($pass);
        if ($pass_len < 6 || $pass_len > 20) {
            wp_send_json_error(['message' => __('Password must be between 6 and 20 characters long.', 'caretochina-medical')]);
        }

        if (!preg_match('/[a-zA-Z]/', $pass) || !preg_match('/[0-9]/', $pass)) {
            wp_send_json_error(['message' => __('Password must contain both letters and numbers.', 'caretochina-medical')]);
        }

        if (email_exists($email)) {
            wp_send_json_error(['message' => __('This email address is already registered. Please sign in instead.', 'caretochina-medical')]);
        }

        $username = sanitize_user(str_replace(' ', '', strtolower($name))) . wp_rand(100, 999);
        $user_id = wp_create_user($username, $pass, $email);

        if (is_wp_error($user_id)) {
            wp_send_json_error(['message' => $user_id->get_error_message()]);
        } else {
            $user_obj = new WP_User($user_id);
            $user_obj->set_role('patient');

            wp_update_user(['ID' => $user_id, 'display_name' => $name]);

            update_user_meta($user_id, 'patient_phone', $phone);
            update_user_meta($user_id, 'patient_specialty', $specialty);
            update_user_meta($user_id, 'patient_gender', $gender);
            if ($age !== null) {
                update_user_meta($user_id, 'patient_age', $age);
            }
            update_user_meta($user_id, 'patient_whatsapp', $whatsapp);
            update_user_meta($user_id, 'patient_wechat', $wechat);
            update_user_meta($user_id, 'patient_messenger', $messenger);
            update_user_meta($user_id, 'patient_linkedin', $linkedin);

            // Link existing guest bookings and chat history to this new patient ID
            self::link_guest_bookings_to_user($user_id, $email);

            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id, true);

            $dash_url = class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_url('patient_dashboard') : home_url('/patient-dashboard/');
            $dash_url = add_query_arg('tab', 'messages', $dash_url);
            wp_send_json_success([
                'message'  => __('Patient Account created successfully! Redirecting to your Live Chat...', 'caretochina-medical'),
                'redirect' => $dash_url
            ]);
        }
    }

    /**
     * Centralized guest account linking helper by verified email
     *
     * @param int $user_id
     * @param string $user_email
     * @return int Count of linked bookings
     */
    public static function link_guest_bookings_to_user($user_id, $user_email) {
        if (empty($user_id) || empty($user_email)) {
            return 0;
        }

        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';
        $table_requests = $wpdb->prefix . 'caretochina_payment_requests';

        // 1. Find all unlinked guest bookings matching verified email
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $guest_bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}caretochina_bookings WHERE (patient_id = 0 OR patient_id IS NULL) AND LOWER(email) = LOWER(%s)",
            $user_email
        ));

        if (empty($guest_bookings)) {
            return 0;
        }

        $booking_ids = wp_list_pluck($guest_bookings, 'id');

        // 2. Link bookings to the verified user and retire guest tokens
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}caretochina_bookings SET patient_id = %d, is_guest = 0, guest_token_hash = '' WHERE (patient_id = 0 OR patient_id IS NULL) AND LOWER(email) = LOWER(%s)",
            $user_id,
            $user_email
        ));

        // 3. Link any associated payment requests to this user
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if (!empty($booking_ids) && $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($table_requests))) === $table_requests) {
            $escaped_b_ids = implode(', ', array_map('intval', $booking_ids));
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}caretochina_payment_requests SET patient_id = %d WHERE chat_thread_booking_id IN ($escaped_b_ids)", $user_id));
        }

        // 4. Invalidate guest cookies on client browser
        if (!headers_sent()) {
            setcookie('ctc_guest_token', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
            setcookie('ctc_active_guest_token', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        }

        // 5. Send Welcome & Records Linked Email Notification
        if (class_exists('CareToChina_Email_Templates')) {
            $user_obj = get_userdata($user_id);
            $user_name = $user_obj ? $user_obj->display_name : 'Patient';
            $dash_url = class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_url('patient_dashboard') : home_url('/patient-dashboard/');
            CareToChina_Email_Templates::send_notification('guest_registered_welcome', $user_email, [
                'patient_name'   => $user_name,
                'patient_email'  => $user_email,
                'dashboard_url'  => $dash_url,
                'chat_url'       => add_query_arg('tab', 'messages', $dash_url),
            ]);
        }

        return count($guest_bookings);
    }
}