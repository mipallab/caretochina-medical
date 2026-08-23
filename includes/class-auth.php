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
            add_role('patient', __('Patient', 'caretochina-booking'), [
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
                wp_redirect($dash_url);
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
            if (is_page('patient-login') || strpos($_SERVER['REQUEST_URI'], 'patient-login') !== false) {
                $dash_url = class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_url('patient_dashboard') : home_url('/patient-dashboard/');
                wp_redirect($dash_url);
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
        $default_tab       = isset($_GET['tab']) && $_GET['tab'] === 'register' ? 'register' : 'login';
        $prefill_name      = sanitize_text_field($_GET['prefill_name'] ?? '');
        $prefill_email     = sanitize_email($_GET['prefill_email'] ?? '');
        $prefill_phone     = sanitize_text_field($_GET['prefill_phone'] ?? '');
        $prefill_gender    = sanitize_text_field($_GET['prefill_gender'] ?? '');
        $prefill_age       = isset($_GET['prefill_age']) && $_GET['prefill_age'] !== '' ? intval($_GET['prefill_age']) : '';
        $prefill_specialty = sanitize_text_field($_GET['prefill_specialty'] ?? '');
        $prefill_whatsapp  = sanitize_text_field($_GET['prefill_whatsapp'] ?? '');
        $prefill_wechat    = sanitize_text_field($_GET['prefill_wechat'] ?? '');
        $prefill_code      = sanitize_text_field($_GET['booking_code'] ?? '');
        ?>
        <div class="careyou-auth-container caretochina-auth-container">
            <!-- MAIN AUTH CARD CONTAINER -->
            <div class="ctc-auth-card">
                <?php if (!empty($_GET['ctc_auth_error'])) : ?>
                    <div class="auth-error-alert" style="background:#FEE2E2; color:#991B1B; border:1px solid #FCA5A5; padding:12px 16px; border-radius:10px; font-size:13px; margin-bottom:18px; display:flex; align-items:center; gap:10px;">
                        <i class="fa-solid fa-triangle-exclamation" style="font-size:18px;"></i>
                        <span><?php echo esc_html(urldecode($_GET['ctc_auth_error'])); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($_GET['ctc_link_account']) && !empty($_GET['token'])) : 
                    $link_email = sanitize_email(urldecode($_GET['email'] ?? ''));
                    $link_token = sanitize_text_field($_GET['token']);
                    ?>
                    <!-- EXPLICIT ACCOUNT LINKING / PASSWORD CONFIRMATION PANEL -->
                    <div id="auth-panel-link-account" class="auth-panel" style="display:block;">
                        <div class="auth-header">
                            <div class="auth-icon-badge" style="background:#CCFBF1; color:#0F766E;">
                                <i class="fa-solid fa-shield-halved"></i>
                            </div>
                            <h3 class="auth-title"><?php _e('Link Google Account', 'caretochina-booking'); ?></h3>
                            <p class="auth-subtitle"><?php _e('Confirm your patient password to link Google Sign-In securely', 'caretochina-booking'); ?></p>
                        </div>

                        <?php if (!empty($_GET['ctc_link_error'])) : ?>
                            <div style="background:#FEF2F2; color:#991B1B; border:1px solid #F87171; padding:10px 14px; border-radius:8px; font-size:13px; margin-bottom:16px;">
                                <i class="fa-solid fa-circle-exclamation"></i> <?php echo esc_html(urldecode($_GET['ctc_link_error'])); ?>
                            </div>
                        <?php endif; ?>

                        <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:12px; padding:14px; margin-bottom:18px; font-size:13px; color:#475569; line-height:1.5;">
                            <i class="fa-solid fa-circle-info" style="color:#0F766E;"></i> 
                            <?php printf(__('An existing patient account exists for <strong>%s</strong>. To prevent account takeover and enable instant 1-click Google Sign-In for future visits, please enter your existing account password once:', 'caretochina-booking'), esc_html($link_email)); ?>
                        </div>

                        <form method="post" action="">
                            <?php wp_nonce_field('ctc_link_account_action', 'ctc_link_nonce'); ?>
                            <input type="hidden" name="link_token" value="<?php echo esc_attr($link_token); ?>">

                            <div class="form-group" style="margin-bottom:16px;">
                                <label class="form-label"><?php _e('Existing Account Password *', 'caretochina-booking'); ?></label>
                                <input type="password" name="account_password" class="form-input" placeholder="••••••••" required autofocus>
                            </div>

                            <button type="submit" name="ctc_submit_link_account" value="1" class="auth-submit-btn" style="width:100%; margin-bottom:12px;">
                                <i class="fa-solid fa-link"></i> <?php _e('Verify Password & Link Google', 'caretochina-booking'); ?>
                            </button>

                            <div style="text-align:center;">
                                <a href="<?php echo esc_url(home_url('/patient-login/')); ?>" style="color:#64748B; font-size:13px; text-decoration:none;">
                                    <i class="fa-solid fa-arrow-left"></i> <?php _e('Cancel and return to Sign In', 'caretochina-booking'); ?>
                                </a>
                            </div>
                        </form>
                    </div>
                <?php else : ?>

                <!-- TABBED SWITCHER -->
                <div class="auth-tab-bar">
                    <button type="button" class="auth-tab-btn <?php echo ($default_tab === 'login') ? 'active' : ''; ?>" onclick="switchAuthTab('login')" id="tab-btn-login">
                        <i class="fa-solid fa-right-to-bracket"></i> <?php _e('Sign In', 'caretochina-booking'); ?>
                    </button>
                    <button type="button" class="auth-tab-btn <?php echo ($default_tab === 'register') ? 'active' : ''; ?>" onclick="switchAuthTab('register')" id="tab-btn-register">
                        <i class="fa-solid fa-user-plus"></i> <?php _e('Register Patient', 'caretochina-booking'); ?>
                    </button>
                </div>

                <!-- LOGIN TAB PANEL -->
                <div id="auth-panel-login" class="auth-panel" style="display:<?php echo ($default_tab === 'login') ? 'block' : 'none'; ?>;">
                    <div class="auth-header">
                        <div class="auth-icon-badge">
                            <i class="fa-solid fa-hospital-user"></i>
                        </div>
                        <h3 class="auth-title"><?php _e('Sign In to Patient Portal', 'caretochina-booking'); ?></h3>
                        <p class="auth-subtitle"><?php _e('Access your medical travel itinerary, timeline & coordinator chat', 'caretochina-booking'); ?></p>
                    </div>

                    <?php if (class_exists('CareToChina_Google_Login') && CareToChina_Google_Login::is_enabled()) : ?>
                        <div class="google-auth-wrapper" style="margin-bottom:20px; text-align:center;">
                            <a href="<?php echo esc_url(CareToChina_Google_Login::get_auth_url()); ?>" class="ctc-google-btn" style="display:flex; align-items:center; justify-content:center; gap:12px; width:100%; background:#FFFFFF; color:#1F2937; border:1.5px solid #E5E7EB; padding:12px 18px; border-radius:12px; font-weight:700; font-size:14px; text-decoration:none; box-shadow:0 2px 6px rgba(0,0,0,0.06); transition:all 0.2s;">
                                <svg width="18" height="18" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
                                <?php _e('Continue with Google', 'caretochina-booking'); ?>
                            </a>
                            <div class="auth-divider" style="display:flex; align-items:center; text-align:center; margin:18px 0; color:#9CA3AF; font-size:12px; font-weight:600;">
                                <span style="flex:1; border-bottom:1px solid #E5E7EB;"></span>
                                <span style="padding:0 12px; text-transform:uppercase; letter-spacing:0.5px;"><?php _e('or sign in with email', 'caretochina-booking'); ?></span>
                                <span style="flex:1; border-bottom:1px solid #E5E7EB;"></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form id="careyou-auth-login-form">
                        <div class="form-group">
                            <label class="form-label"><?php _e('Email Address or Username *', 'caretochina-booking'); ?></label>
                            <input type="text" name="log" class="form-input" value="<?php echo esc_attr($prefill_email); ?>" placeholder="e.g. sarah@example.com" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php _e('Password *', 'caretochina-booking'); ?></label>
                            <input type="password" name="pwd" class="form-input" placeholder="••••••••" required>
                        </div>
                        <div class="auth-aux-row" style="margin-bottom:14px">
                            <label class="remember-me-label">
                                <input type="checkbox" name="remember" value="forever" checked> <?php _e('Remember me', 'caretochina-booking'); ?>
                            </label>
                        </div>

                        <?php if (class_exists('CareToChina_Recaptcha')) echo CareToChina_Recaptcha::render_field('login'); ?>

                        <button type="submit" id="login_submit_btn" class="auth-submit-btn">
                            <i class="fa-solid fa-right-to-bracket"></i> <?php _e('Sign In to Account', 'caretochina-booking'); ?>
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
                        <h3 class="auth-title"><?php _e('Create Patient Account', 'caretochina-booking'); ?></h3>
                        <p class="auth-subtitle"><?php _e('Registered as Patient • Track treatment roadmap & medical vault', 'caretochina-booking'); ?></p>
                    </div>

                    <?php if (!empty($prefill_code)) : ?>
                        <div class="ctc-guest-prefill-alert" style="background:#F0FDF4; border:1px solid #BBF7D0; padding:12px 16px; border-radius:12px; margin-bottom:20px; color:#166534; font-size:13px; display:flex; align-items:center; gap:10px;">
                            <i class="fa-solid fa-circle-check" style="font-size:18px; color:#16A34A; flex-shrink:0;"></i>
                            <div>
                                <strong><?php printf(__('Linking Case #%s', 'caretochina-booking'), esc_html($prefill_code)); ?></strong><br>
                                <span><?php _e('Your inquiry details have been prefilled. Set a password to save your profile and continue your live chat.', 'caretochina-booking'); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (class_exists('CareToChina_Google_Login') && CareToChina_Google_Login::is_enabled()) : ?>
                        <div class="google-auth-wrapper" style="margin-bottom:20px; text-align:center;">
                            <a href="<?php echo esc_url(CareToChina_Google_Login::get_auth_url()); ?>" class="ctc-google-btn" style="display:flex; align-items:center; justify-content:center; gap:12px; width:100%; background:#FFFFFF; color:#1F2937; border:1.5px solid #E5E7EB; padding:12px 18px; border-radius:12px; font-weight:700; font-size:14px; text-decoration:none; box-shadow:0 2px 6px rgba(0,0,0,0.06); transition:all 0.2s;">
                                <svg width="18" height="18" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
                                <?php _e('Sign up with Google', 'caretochina-booking'); ?>
                            </a>
                            <div class="auth-divider" style="display:flex; align-items:center; text-align:center; margin:18px 0; color:#9CA3AF; font-size:12px; font-weight:600;">
                                <span style="flex:1; border-bottom:1px solid #E5E7EB;"></span>
                                <span style="padding:0 12px; text-transform:uppercase; letter-spacing:0.5px;"><?php _e('or create with email', 'caretochina-booking'); ?></span>
                                <span style="flex:1; border-bottom:1px solid #E5E7EB;"></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form id="careyou-auth-register-form">
                        <div class="ctc-form-grid-2">
                            <div class="form-group">
                                <label class="form-label"><?php _e('Full Name *', 'caretochina-booking'); ?></label>
                                <input type="text" name="user_name" class="form-input" value="<?php echo esc_attr($prefill_name); ?>" placeholder="e.g. Sarah Jenkins" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?php _e('Email Address *', 'caretochina-booking'); ?></label>
                                <input type="email" name="user_email" class="form-input" value="<?php echo esc_attr($prefill_email); ?>" placeholder="sarah@example.com" required>
                            </div>
                        </div>

                        <div class="ctc-form-grid-2">
                            <div class="form-group">
                                <label class="form-label"><?php _e('Phone Number *', 'caretochina-booking'); ?></label>
                                <?php echo class_exists('CareToChina_Country_Helper') ? CareToChina_Country_Helper::render_phone_input_group('user_phone', $prefill_phone, true, '+1 (800) 555-0199', 'reg_user_phone') : '<input type="tel" name="user_phone" class="form-input" value="' . esc_attr($prefill_phone) . '" placeholder="+1 (800) 555-0199" required>'; ?>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?php _e('Age', 'caretochina-booking'); ?></label>
                                <input type="number" name="user_age" class="form-input" value="<?php echo esc_attr($prefill_age); ?>" placeholder="e.g. 28">
                            </div>
                        </div>

                        <div class="ctc-form-grid-2">
                            <div class="form-group">
                                <label class="form-label"><?php _e('Gender *', 'caretochina-booking'); ?></label>
                                <select name="user_gender" class="form-select" required>
                                    <option value=""><?php _e('Select Gender', 'caretochina-booking'); ?></option>
                                    <option value="Male" <?php selected($prefill_gender, 'Male'); ?>><?php _e('Male', 'caretochina-booking'); ?></option>
                                    <option value="Female" <?php selected($prefill_gender, 'Female'); ?>><?php _e('Female', 'caretochina-booking'); ?></option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?php _e('Preferred Medical Specialty', 'caretochina-booking'); ?></label>
                                <select name="user_specialty" class="form-select">
                                    <option value=""><?php _e('Select Specialty', 'caretochina-booking'); ?></option>
                                    <?php
                                    $specs = get_terms([
                                        'taxonomy' => 'hospital_specialty',
                                        'hide_empty' => false,
                                    ]);
                                    if (!is_wp_error($specs) && !empty($specs)) {
                                        foreach ($specs as $spec) {
                                            $sel = ($prefill_specialty && (strcasecmp($prefill_specialty, $spec->name) === 0 || stripos($prefill_specialty, $spec->name) !== false)) ? 'selected' : '';
                                            echo '<option value="' . esc_attr($spec->name) . '" ' . $sel . '>' . esc_html($spec->name) . '</option>';
                                        }
                                    } else {
                                        $default_specs = [
                                            'Cardiology & Heart', 'Orthopedics & Joints', 'Oncology & Cancer', 
                                            'Dental Implants', 'Neurosurgery', 'Fertility & IVF', 'General Consultation'
                                        ];
                                        foreach ($default_specs as $ds) {
                                            $sel = ($prefill_specialty && strcasecmp($prefill_specialty, $ds) === 0) ? 'selected' : '';
                                            echo '<option value="' . esc_attr($ds) . '" ' . $sel . '>' . esc_html($ds) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <h4 style="margin: 10px 0 16px 0; font-family:var(--cymb-font-heading); font-size:15px; border:1px solid transparent; border-bottom: 1px solid var(--cymb-border-color); padding-bottom: 6px; color: var(--cymb-text-dark);"><?php _e('Social Accounts (Optional)', 'caretochina-booking'); ?></h4>

                        <div class="ctc-form-grid-2">
                            <div class="form-group">
                                <label class="form-label"><?php _e('WhatsApp', 'caretochina-booking'); ?></label>
                                <?php echo class_exists('CareToChina_Country_Helper') ? CareToChina_Country_Helper::render_phone_input_group('user_whatsapp', $prefill_whatsapp, false, '+1 (800) 555-0199', 'reg_user_whatsapp') : '<input type="tel" name="user_whatsapp" class="form-input" value="' . esc_attr($prefill_whatsapp) . '" placeholder="+1 (800) 555-0199">'; ?>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?php _e('WeChat', 'caretochina-booking'); ?></label>
                                <input type="text" name="user_wechat" class="form-input" value="<?php echo esc_attr($prefill_wechat); ?>" placeholder="WeChat ID">
                            </div>
                        </div>

                        <div class="ctc-form-grid-2">
                            <div class="form-group">
                                <label class="form-label"><?php _e('Messenger', 'caretochina-booking'); ?></label>
                                <input type="text" name="user_messenger" class="form-input" placeholder="Messenger Link / ID">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?php _e('LinkedIn', 'caretochina-booking'); ?></label>
                                <input type="text" name="user_linkedin" class="form-input" placeholder="LinkedIn URL">
                            </div>
                        </div>

                        <div class="ctc-form-grid-2">
                            <div class="form-group">
                                <label class="form-label"><?php _e('Password *', 'caretochina-booking'); ?></label>
                                <input type="password" name="user_pass" id="reg_user_pass" class="form-input" placeholder="••••••••" minlength="6" maxlength="20" required autocomplete="new-password">
                                <div id="reg_pass_rules" style="font-size:11px; color:#64748B; margin-top:4px; line-height:1.3;">
                                    <i class="fa-solid fa-shield-halved"></i> <?php _e('6–20 characters (a-z, A-Z, 0-9), no spaces allowed', 'caretochina-booking'); ?>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?php _e('Confirm Password *', 'caretochina-booking'); ?></label>
                                <input type="password" name="user_pass_confirm" id="reg_user_pass_confirm" class="form-input" placeholder="••••••••" minlength="6" maxlength="20" required autocomplete="new-password">
                                <div id="reg_pass_match_msg" style="font-size:11px; margin-top:4px; display:none; line-height:1.3;"></div>
                            </div>
                        </div>

                        <?php if (class_exists('CareToChina_Recaptcha')) echo CareToChina_Recaptcha::render_field('register'); ?>

                        <button type="submit" id="reg_submit_btn" class="auth-submit-btn">
                            <i class="fa-solid fa-user-plus"></i> <?php _e('Register Patient Account', 'caretochina-booking'); ?>
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
        echo $this->render_auth_portal();
        return ob_get_clean();
    }

    public function handle_login() {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_booking_nonce') && !wp_verify_nonce($nonce, 'careyou_booking_nonce')) {
            wp_send_json_error(['message' => __('Security verification failed.', 'caretochina-booking')]);
        }

        // Google reCAPTCHA Verification (if enabled for login)
        if (class_exists('CareToChina_Recaptcha')) {
            $recaptcha_token = $_POST['g-recaptcha-response'] ?? '';
            $rc_check = CareToChina_Recaptcha::verify_submission($recaptcha_token, 'login');
            if (is_wp_error($rc_check)) {
                wp_send_json_error(['message' => $rc_check->get_error_message()]);
            }
        }

        $log      = sanitize_text_field($_POST['log'] ?? '');
        $pwd      = $_POST['pwd'] ?? '';
        $remember = isset($_POST['remember']) && $_POST['remember'] === 'forever';

        if (empty($log) || empty($pwd)) {
            wp_send_json_error(['message' => __('Please enter both username/email and password.', 'caretochina-booking')]);
        }

        $user = wp_signon(['user_login' => $log, 'user_password' => $pwd, 'remember' => $remember], is_ssl());

        if (is_wp_error($user)) {
            wp_send_json_error(['message' => __('Invalid credentials. Please check your username/email and password.', 'caretochina-booking')]);
        } else {
            // Auto-link any prior guest bookings by verified email
            self::link_guest_bookings_to_user($user->ID, $user->user_email);

            $dash_url = class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_url('patient_dashboard') : home_url('/patient-dashboard/');
            $dash_url = add_query_arg('tab', 'messages', $dash_url);
            wp_send_json_success([
                'message'  => __('Login successful! Redirecting to your Live Chat & Consultation...', 'caretochina-booking'),
                'redirect' => $dash_url
            ]);
        }
    }

    public function handle_register() {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_booking_nonce') && !wp_verify_nonce($nonce, 'careyou_booking_nonce')) {
            wp_send_json_error(['message' => __('Security verification failed.', 'caretochina-booking')]);
        }

        // Google reCAPTCHA Verification (if enabled for register)
        if (class_exists('CareToChina_Recaptcha')) {
            $recaptcha_token = $_POST['g-recaptcha-response'] ?? '';
            $rc_check = CareToChina_Recaptcha::verify_submission($recaptcha_token, 'register');
            if (is_wp_error($rc_check)) {
                wp_send_json_error(['message' => $rc_check->get_error_message()]);
            }
        }

        $name         = sanitize_text_field($_POST['user_name'] ?? '');
        $email        = sanitize_email($_POST['user_email'] ?? '');
        $phone        = class_exists('CareToChina_Country_Helper') ? CareToChina_Country_Helper::extract_submitted_phone($_POST, 'user_phone') : sanitize_text_field($_POST['user_phone'] ?? '');
        $specialty    = sanitize_text_field($_POST['user_specialty'] ?? 'General Consultation');
        $gender       = sanitize_text_field($_POST['user_gender'] ?? '');
        $age          = isset($_POST['user_age']) && $_POST['user_age'] !== '' ? intval($_POST['user_age']) : null;
        $whatsapp     = class_exists('CareToChina_Country_Helper') ? CareToChina_Country_Helper::extract_submitted_phone($_POST, 'user_whatsapp') : sanitize_text_field($_POST['user_whatsapp'] ?? '');
        $wechat       = sanitize_text_field($_POST['user_wechat'] ?? '');
        $messenger    = sanitize_text_field($_POST['user_messenger'] ?? '');
        $linkedin     = sanitize_text_field($_POST['user_linkedin'] ?? '');
        $pass         = $_POST['user_pass'] ?? '';
        $pass_confirm = $_POST['user_pass_confirm'] ?? '';

        if (empty($name) || empty($email) || empty($phone) || empty($gender) || empty($pass)) {
            wp_send_json_error(['message' => __('Please fill in all required fields (Name, Email, Phone, Gender, Password).', 'caretochina-booking')]);
        }

        if ($pass !== $pass_confirm) {
            wp_send_json_error(['message' => __('Passwords do not match. Please verify your password.', 'caretochina-booking')]);
        }

        if (preg_match('/\s/', $pass)) {
            wp_send_json_error(['message' => __('Password cannot contain spaces or whitespace.', 'caretochina-booking')]);
        }

        $pass_len = strlen($pass);
        if ($pass_len < 6 || $pass_len > 20) {
            wp_send_json_error(['message' => __('Password must be between 6 and 20 characters long.', 'caretochina-booking')]);
        }

        if (!preg_match('/[a-zA-Z]/', $pass) || !preg_match('/[0-9]/', $pass)) {
            wp_send_json_error(['message' => __('Password must contain both letters and numbers.', 'caretochina-booking')]);
        }

        if (email_exists($email)) {
            wp_send_json_error(['message' => __('This email address is already registered. Please sign in instead.', 'caretochina-booking')]);
        }

        $username = sanitize_user(str_replace(' ', '', strtolower($name))) . rand(100, 999);
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
                'message'  => __('Patient Account created successfully! Redirecting to your Live Chat...', 'caretochina-booking'),
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
        $guest_bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM $table_bookings WHERE (patient_id = 0 OR patient_id IS NULL) AND LOWER(email) = LOWER(%s)",
            $user_email
        ));

        if (empty($guest_bookings)) {
            return 0;
        }

        $booking_ids = wp_list_pluck($guest_bookings, 'id');

        // 2. Link bookings to the verified user and retire guest tokens
        $wpdb->query($wpdb->prepare(
            "UPDATE $table_bookings SET patient_id = %d, is_guest = 0, guest_token_hash = '' WHERE (patient_id = 0 OR patient_id IS NULL) AND LOWER(email) = LOWER(%s)",
            $user_id,
            $user_email
        ));

        // 3. Link any associated payment requests to this user
        if (!empty($booking_ids) && $wpdb->get_var("SHOW TABLES LIKE '$table_requests'") === $table_requests) {
            $ids_placeholder = implode(',', array_map('intval', $booking_ids));
            $wpdb->query($wpdb->prepare(
                "UPDATE $table_requests SET patient_id = %d WHERE chat_thread_booking_id IN ($ids_placeholder)",
                $user_id
            ));
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