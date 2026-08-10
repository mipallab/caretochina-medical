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
                wp_redirect(home_url('/patient-dashboard/'));
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
                wp_redirect(home_url('/patient-dashboard/'));
                exit;
            }
        }
    }

    public function custom_login_redirect($redirect_to, $request, $user) {
        if (isset($user->roles) && is_array($user->roles)) {
            if (in_array('patient', $user->roles) && !current_user_can('manage_options')) {
                return home_url('/patient-dashboard/');
            }
        }
        return $redirect_to;
    }

    public function render_auth_portal() {
        ob_start();
        $default_tab = isset($_GET['tab']) && $_GET['tab'] === 'register' ? 'register' : 'login';
        ?>
        <div class="careyou-auth-container caretochina-auth-container">
            <!-- MAIN AUTH CARD CONTAINER -->
            <div class="ctc-auth-card">
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

                    <form id="careyou-auth-login-form">
                        <div class="form-group">
                            <label class="form-label"><?php _e('Email Address or Username *', 'caretochina-booking'); ?></label>
                            <input type="text" name="log" class="form-input" placeholder="e.g. sarah@example.com" required>
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

                    <form id="careyou-auth-register-form">
                        <div class="ctc-form-grid-2">
                            <div class="form-group">
                                <label class="form-label"><?php _e('Full Name *', 'caretochina-booking'); ?></label>
                                <input type="text" name="user_name" class="form-input" placeholder="e.g. Sarah Jenkins" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?php _e('Email Address *', 'caretochina-booking'); ?></label>
                                <input type="email" name="user_email" class="form-input" placeholder="sarah@example.com" required>
                            </div>
                        </div>

                        <div class="ctc-form-grid-2">
                            <div class="form-group">
                                <label class="form-label"><?php _e('Phone Number *', 'caretochina-booking'); ?></label>
                                <input type="tel" name="user_phone" class="form-input" placeholder="+1 (800) 555-0199" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?php _e('Age', 'caretochina-booking'); ?></label>
                                <input type="number" name="user_age" class="form-input" placeholder="e.g. 28">
                            </div>
                        </div>

                        <div class="ctc-form-grid-2">
                            <div class="form-group">
                                <label class="form-label"><?php _e('Gender *', 'caretochina-booking'); ?></label>
                                <select name="user_gender" class="form-select" required>
                                    <option value=""><?php _e('Select Gender', 'caretochina-booking'); ?></option>
                                    <option value="Male"><?php _e('Male', 'caretochina-booking'); ?></option>
                                    <option value="Female"><?php _e('Female', 'caretochina-booking'); ?></option>
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
                                            echo '<option value="' . esc_attr($spec->name) . '">' . esc_html($spec->name) . '</option>';
                                        }
                                    } else {
                                        echo '<option value="Cardiology & Heart">' . esc_html__('Cardiology & Heart', 'caretochina-booking') . '</option>';
                                        echo '<option value="Orthopedics & Joints">' . esc_html__('Orthopedics & Joints', 'caretochina-booking') . '</option>';
                                        echo '<option value="Oncology & Cancer">' . esc_html__('Oncology & Cancer', 'caretochina-booking') . '</option>';
                                        echo '<option value="Dental Implants">' . esc_html__('Dental Implants', 'caretochina-booking') . '</option>';
                                        echo '<option value="Neurosurgery">' . esc_html__('Neurosurgery', 'caretochina-booking') . '</option>';
                                        echo '<option value="Fertility & IVF">' . esc_html__('Fertility & IVF', 'caretochina-booking') . '</option>';
                                        echo '<option value="General Consultation">' . esc_html__('General Consultation', 'caretochina-booking') . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <h4 style="margin: 10px 0 16px 0; font-family:var(--cymb-font-heading); font-size:15px; border:1px solid transparent; border-bottom: 1px solid var(--cymb-border-color); padding-bottom: 6px; color: var(--cymb-text-dark);"><?php _e('Social Accounts (Optional)', 'caretochina-booking'); ?></h4>

                        <div class="ctc-form-grid-2">
                            <div class="form-group">
                                <label class="form-label"><?php _e('WhatsApp', 'caretochina-booking'); ?></label>
                                <input type="text" name="user_whatsapp" class="form-input" placeholder="+1 (800) 555-0199">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?php _e('WeChat', 'caretochina-booking'); ?></label>
                                <input type="text" name="user_wechat" class="form-input" placeholder="WeChat ID">
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
                                <input type="password" name="user_pass" class="form-input" placeholder="••••••••" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?php _e('Confirm Password *', 'caretochina-booking'); ?></label>
                                <input type="password" name="user_pass_confirm" class="form-input" placeholder="••••••••" required>
                            </div>
                        </div>

                        <button type="submit" id="reg_submit_btn" class="auth-submit-btn">
                            <i class="fa-solid fa-user-plus"></i> <?php _e('Register Patient Account', 'caretochina-booking'); ?>
                        </button>
                    </form>
                    <div id="reg-response-box" class="auth-response-box" style="display:none;"></div>
                </div>
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
            wp_send_json_success([
                'message'  => __('Login successful! Redirecting to Patient Dashboard...', 'caretochina-booking'),
                'redirect' => home_url('/patient-dashboard/')
            ]);
        }
    }

    public function handle_register() {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_booking_nonce') && !wp_verify_nonce($nonce, 'careyou_booking_nonce')) {
            wp_send_json_error(['message' => __('Security verification failed.', 'caretochina-booking')]);
        }

        $name         = sanitize_text_field($_POST['user_name'] ?? '');
        $email        = sanitize_email($_POST['user_email'] ?? '');
        $phone        = sanitize_text_field($_POST['user_phone'] ?? '');
        $specialty    = sanitize_text_field($_POST['user_specialty'] ?? 'General Consultation');
        $gender       = sanitize_text_field($_POST['user_gender'] ?? '');
        $age          = isset($_POST['user_age']) && $_POST['user_age'] !== '' ? intval($_POST['user_age']) : null;
        $whatsapp     = sanitize_text_field($_POST['user_whatsapp'] ?? '');
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

        if (strlen($pass) < 6) {
            wp_send_json_error(['message' => __('Password must be at least 6 characters long.', 'caretochina-booking')]);
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

            // Link existing guest bookings to this new patient ID
            global $wpdb;
            $table_bookings = $wpdb->prefix . 'caretochina_bookings';
            $wpdb->update($table_bookings, ['patient_id' => $user_id], ['email' => $email, 'patient_id' => 0]);

            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id, true);

            wp_send_json_success([
                'message'  => __('Patient Account created successfully! Redirecting to Patient Dashboard...', 'caretochina-booking'),
                'redirect' => home_url('/patient-dashboard/')
            ]);
        }
    }
}