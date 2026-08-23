<?php
if (!defined('ABSPATH')) {
    exit;
}

class CareToChina_Payment_Admin_Settings {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this, 'handle_save_settings']);
    }

    public static function get_mode() {
        if (defined('CARETOCHINA_PAYMENT_MODE')) {
            return CARETOCHINA_PAYMENT_MODE;
        }
        return get_option('ctc_payment_environment_mode', 'test');
    }

    public function register_admin_menu() {
        add_submenu_page(
            'caretochina-staff-desk',
            __('Payment Gateway Settings', 'caretochina-medical'),
            __('Payment Settings', 'caretochina-medical'),
            'manage_options',
            'caretochina-payment-settings',
            [$this, 'render_settings_page']
        );
    }

    public function handle_save_settings() {
        if (!isset($_POST['ctc_payment_settings_nonce'])) {
            return;
        }

        $nonce = isset($_POST['ctc_payment_settings_nonce']) ? sanitize_text_field(wp_unslash($_POST['ctc_payment_settings_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'ctc_save_payment_settings')) {
            wp_die(__('Security verification failed.', 'caretochina-medical'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied.', 'caretochina-medical'));
        }

        $mode = isset($_POST['ctc_payment_environment_mode']) ? sanitize_text_field(wp_unslash($_POST['ctc_payment_environment_mode'])) : 'test';
        update_option('ctc_payment_environment_mode', $mode);

        $currency = isset($_POST['ctc_payment_currency']) ? sanitize_text_field(wp_unslash($_POST['ctc_payment_currency'])) : 'USD';
        update_option('ctc_payment_currency', $currency);

        // Stripe Test Keys
        if (isset($_POST['ctc_stripe_test_pub_key'])) {
            update_option('ctc_stripe_test_pub_key', sanitize_text_field(wp_unslash($_POST['ctc_stripe_test_pub_key'])));
        }
        if (!empty($_POST['ctc_stripe_test_sec_key']) && strpos($_POST['ctc_stripe_test_sec_key'], '••••') === false) {
            $enc = CareToChina_Payment_Security::encrypt_secret(sanitize_text_field(wp_unslash($_POST['ctc_stripe_test_sec_key'])));
            update_option('ctc_stripe_test_sec_key', $enc);
        }
        if (!empty($_POST['ctc_stripe_test_wh_secret']) && strpos($_POST['ctc_stripe_test_wh_secret'], '••••') === false) {
            $enc = CareToChina_Payment_Security::encrypt_secret(sanitize_text_field(wp_unslash($_POST['ctc_stripe_test_wh_secret'])));
            update_option('ctc_stripe_test_wh_secret', $enc);
        }

        // Stripe Live Keys
        if (isset($_POST['ctc_stripe_live_pub_key'])) {
            update_option('ctc_stripe_live_pub_key', sanitize_text_field(wp_unslash($_POST['ctc_stripe_live_pub_key'])));
        }
        if (!empty($_POST['ctc_stripe_live_sec_key']) && strpos($_POST['ctc_stripe_live_sec_key'], '••••') === false) {
            $enc = CareToChina_Payment_Security::encrypt_secret(sanitize_text_field(wp_unslash($_POST['ctc_stripe_live_sec_key'])));
            update_option('ctc_stripe_live_sec_key', $enc);
        }
        if (!empty($_POST['ctc_stripe_live_wh_secret']) && strpos($_POST['ctc_stripe_live_wh_secret'], '••••') === false) {
            $enc = CareToChina_Payment_Security::encrypt_secret(sanitize_text_field(wp_unslash($_POST['ctc_stripe_live_wh_secret'])));
            update_option('ctc_stripe_live_wh_secret', $enc);
        }

        // PayPal Test Keys
        if (isset($_POST['ctc_paypal_test_client_id'])) {
            update_option('ctc_paypal_test_client_id', sanitize_text_field(wp_unslash($_POST['ctc_paypal_test_client_id'])));
        }
        if (!empty($_POST['ctc_paypal_test_client_secret']) && strpos($_POST['ctc_paypal_test_client_secret'], '••••') === false) {
            $enc = CareToChina_Payment_Security::encrypt_secret(sanitize_text_field(wp_unslash($_POST['ctc_paypal_test_client_secret'])));
            update_option('ctc_paypal_test_client_secret', $enc);
        }

        // PayPal Live Keys
        if (isset($_POST['ctc_paypal_live_client_id'])) {
            update_option('ctc_paypal_live_client_id', sanitize_text_field(wp_unslash($_POST['ctc_paypal_live_client_id'])));
        }
        if (!empty($_POST['ctc_paypal_live_client_secret']) && strpos($_POST['ctc_paypal_live_client_secret'], '••••') === false) {
            $enc = CareToChina_Payment_Security::encrypt_secret(sanitize_text_field(wp_unslash($_POST['ctc_paypal_live_client_secret'])));
            update_option('ctc_paypal_live_client_secret', $enc);
        }

        // Google OAuth 2.0 Keys & Toggle
        update_option('ctc_google_login_enabled', isset($_POST['ctc_google_login_enabled']) ? 1 : 0);
        if (isset($_POST['ctc_google_client_id'])) {
            update_option('ctc_google_client_id', sanitize_text_field(wp_unslash($_POST['ctc_google_client_id'])));
        }
        if (!empty($_POST['ctc_google_client_secret']) && strpos($_POST['ctc_google_client_secret'], '••••') === false) {
            $enc = CareToChina_Payment_Security::encrypt_secret(sanitize_text_field(wp_unslash($_POST['ctc_google_client_secret'])));
            update_option('ctc_google_client_secret', $enc);
        }

        // Google reCAPTCHA Settings
        update_option('ctc_recaptcha_master_enabled', isset($_POST['ctc_recaptcha_master_enabled']) ? 1 : 0);
        if (isset($_POST['ctc_recaptcha_version'])) {
            update_option('ctc_recaptcha_version', sanitize_text_field(wp_unslash($_POST['ctc_recaptcha_version'])));
        }
        if (isset($_POST['ctc_recaptcha_v2_site_key'])) {
            update_option('ctc_recaptcha_v2_site_key', sanitize_text_field(wp_unslash($_POST['ctc_recaptcha_v2_site_key'])));
        }
        if (!empty($_POST['ctc_recaptcha_v2_secret_key']) && strpos($_POST['ctc_recaptcha_v2_secret_key'], '••••') === false) {
            $enc = CareToChina_Payment_Security::encrypt_secret(sanitize_text_field(wp_unslash($_POST['ctc_recaptcha_v2_secret_key'])));
            update_option('ctc_recaptcha_v2_secret_key', $enc);
        }
        if (isset($_POST['ctc_recaptcha_v3_site_key'])) {
            update_option('ctc_recaptcha_v3_site_key', sanitize_text_field(wp_unslash($_POST['ctc_recaptcha_v3_site_key'])));
        }
        if (!empty($_POST['ctc_recaptcha_v3_secret_key']) && strpos($_POST['ctc_recaptcha_v3_secret_key'], '••••') === false) {
            $enc = CareToChina_Payment_Security::encrypt_secret(sanitize_text_field(wp_unslash($_POST['ctc_recaptcha_v3_secret_key'])));
            update_option('ctc_recaptcha_v3_secret_key', $enc);
        }
        if (isset($_POST['ctc_recaptcha_v3_threshold'])) {
            update_option('ctc_recaptcha_v3_threshold', floatval(wp_unslash($_POST['ctc_recaptcha_v3_threshold'])));
        }
        update_option('ctc_recaptcha_enable_login', isset($_POST['ctc_recaptcha_enable_login']) ? 1 : 0);
        update_option('ctc_recaptcha_enable_register', isset($_POST['ctc_recaptcha_enable_register']) ? 1 : 0);
        update_option('ctc_recaptcha_enable_guest_booking', isset($_POST['ctc_recaptcha_enable_guest_booking']) ? 1 : 0);
        update_option('ctc_recaptcha_hide_badge', isset($_POST['ctc_recaptcha_hide_badge']) ? 1 : 0);

        // Global Brand Logo & Assets
        if (isset($_POST['ctc_brand_logo_url'])) {
            update_option('ctc_brand_logo_url', esc_url_raw(wp_unslash($_POST['ctc_brand_logo_url'])));
            update_option('ctc_email_logo_url', esc_url_raw(wp_unslash($_POST['ctc_brand_logo_url'])));
        }

        // Guest Token Expiration Setting (in Days)
        if (isset($_POST['ctc_guest_token_expiry_days'])) {
            $days = max(1, min(365, intval(wp_unslash($_POST['ctc_guest_token_expiry_days']))));
            update_option('ctc_guest_token_expiry_days', $days);
        }

        // International Telephone Input Feature Toggle
        update_option('ctc_enable_intl_phone_flags', isset($_POST['ctc_enable_intl_phone_flags']) ? 1 : 0);
        if (isset($_POST['ctc_phone_selector_format'])) {
            update_option('ctc_phone_selector_format', sanitize_text_field(wp_unslash($_POST['ctc_phone_selector_format'])));
        }

        // Data Safety / Uninstall Option
        update_option('ctc_delete_data_on_uninstall', isset($_POST['ctc_delete_data_on_uninstall']) ? 1 : 0);

        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Payment, Security, Input & Google login settings saved successfully (secrets encrypted at rest).', 'caretochina-medical') . '</p></div>';
        });
    }

    public function render_settings_page() {
        $mode = self::get_mode();
        $currency = get_option('ctc_payment_currency', 'USD');

        $stripe_test_pub = get_option('ctc_stripe_test_pub_key', '');
        $stripe_test_sec_masked = CareToChina_Payment_Security::mask_secret(get_option('ctc_stripe_test_sec_key', ''));
        $stripe_test_wh_masked = CareToChina_Payment_Security::mask_secret(get_option('ctc_stripe_test_wh_secret', ''));

        $stripe_live_pub = get_option('ctc_stripe_live_pub_key', '');
        $stripe_live_sec_masked = CareToChina_Payment_Security::mask_secret(get_option('ctc_stripe_live_sec_key', ''));
        $stripe_live_wh_masked = CareToChina_Payment_Security::mask_secret(get_option('ctc_stripe_live_wh_secret', ''));

        $paypal_test_client = get_option('ctc_paypal_test_client_id', '');
        $paypal_test_sec_masked = CareToChina_Payment_Security::mask_secret(get_option('ctc_paypal_test_client_secret', ''));

        $paypal_live_client = get_option('ctc_paypal_live_client_id', '');
        $paypal_live_sec_masked = CareToChina_Payment_Security::mask_secret(get_option('ctc_paypal_live_client_secret', ''));

        $google_enabled = intval(get_option('ctc_google_login_enabled', 1));
        $google_client_id = get_option('ctc_google_client_id', '');
        $google_client_sec_masked = CareToChina_Payment_Security::mask_secret(get_option('ctc_google_client_secret', ''));
        $google_redirect_uri = home_url('/?ctc_google_callback=1');

        $rc_master_enabled = intval(get_option('ctc_recaptcha_master_enabled', 1));
        $recaptcha_ver = get_option('ctc_recaptcha_version', 'v2');
        $rc_v2_site = get_option('ctc_recaptcha_v2_site_key', '');
        $rc_v2_sec_masked = CareToChina_Payment_Security::mask_secret(get_option('ctc_recaptcha_v2_secret_key', ''));
        $rc_v3_site = get_option('ctc_recaptcha_v3_site_key', '');
        $rc_v3_sec_masked = CareToChina_Payment_Security::mask_secret(get_option('ctc_recaptcha_v3_secret_key', ''));
        $rc_v3_threshold = floatval(get_option('ctc_recaptcha_v3_threshold', 0.5));
        $rc_login = intval(get_option('ctc_recaptcha_enable_login', 0));
        $rc_reg = intval(get_option('ctc_recaptcha_enable_register', 0));
        $rc_book = intval(get_option('ctc_recaptcha_enable_booking', 0));
        $rc_guest_book = intval(get_option('ctc_recaptcha_enable_guest_booking', 0));
        $rc_hide_badge = intval(get_option('ctc_recaptcha_hide_badge', 0));

        $intl_phone_enabled = intval(get_option('ctc_enable_intl_phone_flags', 1));

        $delete_on_uninstall = intval(get_option('ctc_delete_data_on_uninstall', 0));
        $export_nonce = wp_create_nonce('ctc_export_data_nonce');

        $constant_override = defined('CARETOCHINA_STRIPE_SECRET_KEY') || defined('CARETOCHINA_STRIPE_TEST_SECRET_KEY');

        ?>
        <div class="wrap" style="max-width: 960px; font-family:'Manrope', sans-serif;">
            <h1 style="display:flex; align-items:center; gap:10px;"><i class="fa-solid fa-credit-card" style="color:#0F766E;"></i> <?php _e('CareToChina Payment & Auth Settings', 'caretochina-medical'); ?></h1>
            <p><?php _e('Configure Stripe, PayPal gateways, Google Sign-In, and Google reCAPTCHA protection. Secrets are automatically encrypted at rest in the database.', 'caretochina-medical'); ?></p>

            <?php if ($constant_override) : ?>
                <div class="notice notice-info inline" style="margin-bottom:20px;">
                    <p><strong><i class="fa-solid fa-lock"></i> <?php _e('wp-config.php Constants Active:', 'caretochina-medical'); ?></strong> <?php _e('One or more payment API constants are defined in wp-config.php and will take precedence over UI options.', 'caretochina-medical'); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <?php wp_nonce_field('ctc_save_payment_settings', 'ctc_payment_settings_nonce'); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="ctc_payment_environment_mode"><?php _e('Environment Mode', 'caretochina-medical'); ?></label></th>
                            <td>
                                <select name="ctc_payment_environment_mode" id="ctc_payment_environment_mode" class="regular-text" style="font-weight:700;">
                                    <option value="test" <?php selected($mode, 'test'); ?>><?php _e('Test / Sandbox Mode (Recommended for Development)', 'caretochina-medical'); ?></option>
                                    <option value="live" <?php selected($mode, 'live'); ?>><?php _e('Live / Production Mode', 'caretochina-medical'); ?></option>
                                </select>
                                <p class="description"><?php _e('Switches active Stripe and PayPal credentials between Sandbox and Production.', 'caretochina-medical'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="ctc_payment_currency"><?php _e('Default Currency', 'caretochina-medical'); ?></label></th>
                            <td>
                                <select name="ctc_payment_currency" id="ctc_payment_currency" class="regular-text">
                                    <option value="USD" <?php selected($currency, 'USD'); ?>>USD ($)</option>
                                    <option value="EUR" <?php selected($currency, 'EUR'); ?>>EUR (€)</option>
                                    <option value="GBP" <?php selected($currency, 'GBP'); ?>>GBP (£)</option>
                                    <option value="AUD" <?php selected($currency, 'AUD'); ?>>AUD ($)</option>
                                    <option value="CAD" <?php selected($currency, 'CAD'); ?>>CAD ($)</option>
                                    <option value="CNY" <?php selected($currency, 'CNY'); ?>>CNY (¥)</option>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <h2 style="margin-top:30px; border-bottom:1px solid #CBD5E1; padding-bottom:10px;"><i class="fa-brands fa-google" style="color:#4285F4; font-size:22px;"></i> <?php _e('Google Sign-In for Patients', 'caretochina-medical'); ?></h2>
                
                <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:10px; padding:16px 20px; margin-bottom:20px; font-size:13px; color:#475569;">
                    <h4 style="margin:0 0 10px 0; color:#0F766E; font-size:14px; font-weight:700;"><i class="fa-solid fa-circle-info"></i> <?php _e('How to set up Google OAuth 2.0 in Google Cloud Console:', 'caretochina-medical'); ?></h4>
                    <ol style="margin:0 0 10px 20px; line-height:1.6;">
                        <li><?php _e('Go to the <strong>Google Cloud Console</strong> (console.cloud.google.com) and create or select your project.', 'caretochina-medical'); ?></li>
                        <li><?php _e('Navigate to <strong>APIs & Services &gt; OAuth consent screen</strong>. Choose <strong>External</strong> and fill in your App Name and support email.', 'caretochina-medical'); ?></li>
                        <li><?php _e('Navigate to <strong>Credentials &gt; Create Credentials &gt; OAuth client ID</strong>.', 'caretochina-medical'); ?></li>
                        <li><?php _e('Select <strong>Web application</strong> as the Application type.', 'caretochina-medical'); ?></li>
                        <li><?php _e('Under <strong>Authorized redirect URIs</strong>, paste the exact URI shown below.', 'caretochina-medical'); ?></li>
                        <li><?php _e('Copy the generated <strong>Client ID</strong> and <strong>Client Secret</strong> into the fields below and click Save.', 'caretochina-medical'); ?></li>
                    </ol>
                    <p style="margin:0;"><strong><?php _e('Authorized Redirect URI:', 'caretochina-medical'); ?></strong> <code style="background:#E2E8F0; padding:3px 8px; border-radius:4px; font-weight:700; color:#0F172A;"><?php echo esc_html($google_redirect_uri); ?></code></p>
                </div>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><?php _e('Enable Google Sign-In', 'caretochina-medical'); ?></th>
                            <td>
                                <label style="font-weight:700; display:flex; align-items:center; gap:8px;">
                                    <input type="checkbox" name="ctc_google_login_enabled" value="1" <?php checked($google_enabled, 1); ?>>
                                    <?php _e('Enable Google Sign-In on Patient Login & Registration', 'caretochina-medical'); ?>
                                </label>
                                <p class="description"><?php _e('Master switch. When disabled, "Continue with Google" buttons are hidden and OAuth callbacks are rejected, keeping your credentials intact.', 'caretochina-medical'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ctc_google_client_id"><?php _e('Google Client ID', 'caretochina-medical'); ?></label></th>
                            <td>
                                <input type="text" name="ctc_google_client_id" id="ctc_google_client_id" value="<?php echo esc_attr($google_client_id); ?>" class="regular-text" placeholder="xxxx.apps.googleusercontent.com" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ctc_google_client_secret"><?php _e('Google Client Secret', 'caretochina-medical'); ?></label></th>
                            <td>
                                <input type="password" name="ctc_google_client_secret" id="ctc_google_client_secret" value="<?php echo esc_attr($google_client_sec_masked); ?>" class="regular-text" placeholder="GOCSPX-..." />
                                <p class="description"><?php _e('Encrypted at rest in database using AES-256-GCM / Sodium.', 'caretochina-medical'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <h2 style="margin-top:30px; border-bottom:1px solid #CBD5E1; padding-bottom:10px;"><i class="fa-solid fa-shield-halved" style="color:#0F766E; font-size:22px;"></i> <?php _e('Google reCAPTCHA Protection', 'caretochina-medical'); ?></h2>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><?php _e('Master Protection Switch', 'caretochina-medical'); ?></th>
                            <td>
                                <label style="font-weight:700; display:flex; align-items:center; gap:8px;">
                                    <input type="checkbox" name="ctc_recaptcha_master_enabled" value="1" <?php checked($rc_master_enabled, 1); ?>>
                                    <?php _e('Enable Google reCAPTCHA Protection System', 'caretochina-medical'); ?>
                                </label>
                                <p class="description"><?php _e('Global kill-switch. When disabled, reCAPTCHA verification is instantly bypassed across all forms on the site.', 'caretochina-medical'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('reCAPTCHA Version', 'caretochina-medical'); ?></th>
                            <td>
                                <label style="margin-right:20px; font-weight:600;">
                                    <input type="radio" name="ctc_recaptcha_version" value="v2" <?php checked($recaptcha_ver, 'v2'); ?> onchange="jQuery('#rc-v2-row').show(); jQuery('#rc-v3-row').hide();">
                                    <?php _e('v2 (Checkbox)', 'caretochina-medical'); ?>
                                </label>
                                <label style="font-weight:600;">
                                    <input type="radio" name="ctc_recaptcha_version" value="v3" <?php checked($recaptcha_ver, 'v3'); ?> onchange="jQuery('#rc-v3-row').show(); jQuery('#rc-v2-row').hide();">
                                    <?php _e('v3 (Invisible / Score-based)', 'caretochina-medical'); ?>
                                </label>
                            </td>
                        </tr>

                        <!-- v2 keys -->
                        <tr id="rc-v2-row" style="<?php echo $recaptcha_ver === 'v2' ? '' : 'display:none;'; ?>">
                            <th scope="row"><?php _e('reCAPTCHA v2 Credentials', 'caretochina-medical'); ?></th>
                            <td>
                                <input type="text" name="ctc_recaptcha_v2_site_key" value="<?php echo esc_attr($rc_v2_site); ?>" class="regular-text" placeholder="v2 Site Key" style="margin-bottom:8px; display:block;" />
                                <input type="password" name="ctc_recaptcha_v2_secret_key" value="<?php echo esc_attr($rc_v2_sec_masked); ?>" class="regular-text" placeholder="v2 Secret Key" />
                            </td>
                        </tr>

                        <!-- v3 keys -->
                        <tr id="rc-v3-row" style="<?php echo $recaptcha_ver === 'v3' ? '' : 'display:none;'; ?>">
                            <th scope="row"><?php _e('reCAPTCHA v3 Credentials & Threshold', 'caretochina-medical'); ?></th>
                            <td>
                                <input type="text" name="ctc_recaptcha_v3_site_key" value="<?php echo esc_attr($rc_v3_site); ?>" class="regular-text" placeholder="v3 Site Key" style="margin-bottom:8px; display:block;" />
                                <input type="password" name="ctc_recaptcha_v3_secret_key" value="<?php echo esc_attr($rc_v3_sec_masked); ?>" class="regular-text" placeholder="v3 Secret Key" style="margin-bottom:8px; display:block;" />
                                <label style="font-size:12px; color:#64748B;">
                                    <?php _e('Pass Score Threshold (Default 0.5):', 'caretochina-medical'); ?>
                                    <input type="number" step="0.05" min="0.1" max="1.0" name="ctc_recaptcha_v3_threshold" value="<?php echo esc_attr($rc_v3_threshold); ?>" style="width:100px; margin-left:8px;" />
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php _e('Form Protection Locations', 'caretochina-medical'); ?></th>
                            <td>
                                <fieldset>
                                    <label style="display:block; margin-bottom:6px;"><input type="checkbox" name="ctc_recaptcha_enable_login" value="1" <?php checked($rc_login, 1); ?>> <?php _e('Patient Login Form', 'caretochina-medical'); ?></label>
                                    <label style="display:block; margin-bottom:6px;"><input type="checkbox" name="ctc_recaptcha_enable_register" value="1" <?php checked($rc_reg, 1); ?>> <?php _e('Patient Registration Form', 'caretochina-medical'); ?></label>
                                    <label style="display:block; margin-bottom:6px;"><input type="checkbox" name="ctc_recaptcha_enable_booking" value="1" <?php checked($rc_book, 1); ?>> <?php _e('Booking Wizard Submission (Logged In)', 'caretochina-medical'); ?></label>
                                    <label style="display:block;"><input type="checkbox" name="ctc_recaptcha_enable_guest_booking" value="1" <?php checked($rc_guest_book, 1); ?>> <?php _e('Guest Booking & Live Chat Submission', 'caretochina-medical'); ?></label>
                                </fieldset>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php _e('Badge Visibility & Attribution', 'caretochina-medical'); ?></th>
                            <td>
                                <label style="font-weight:600; display:flex; align-items:flex-start; gap:8px; cursor:pointer;">
                                    <input type="checkbox" name="ctc_recaptcha_hide_badge" value="1" <?php checked($rc_hide_badge, 1); ?> style="margin-top:2px;">
                                    <span>
                                        <?php _e('Hide floating reCAPTCHA badge', 'caretochina-medical'); ?>
                                        <span class="description" style="display:block; margin-top:4px; font-weight:normal; color:#64748B;">
                                            <?php _e('Enabling this hides the floating badge via CSS and automatically displays the required Google attribution text ("This site is protected by reCAPTCHA and the Google Privacy Policy and Terms of Service apply") below active protected forms in compliance with Google Terms of Service.', 'caretochina-medical'); ?>
                                        </span>
                                    </span>
                                </label>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <h2 style="margin-top:30px; border-bottom:1px solid #CBD5E1; padding-bottom:10px;"><i class="fa-solid fa-paintbrush" style="color:#0F766E; font-size:22px;"></i> <?php _e('Brand Assets & Global Logo', 'caretochina-medical'); ?></h2>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="ctc_brand_logo_url"><?php _e('Global Brand Logo URL', 'caretochina-medical'); ?></label></th>
                            <td>
                                <input type="url" name="ctc_brand_logo_url" id="ctc_brand_logo_url" value="<?php echo esc_attr(get_option('ctc_brand_logo_url', '')); ?>" class="large-text" placeholder="https://yourdomain.com/wp-content/uploads/logo.png">
                                <p class="description"><?php _e('Enter the full image URL of your brand logo. This logo is used across all outbound emails, booking wizard header, patient portal, and staff desk.', 'caretochina-medical'); ?></p>
                                <?php $logo_prev = get_option('ctc_brand_logo_url', ''); if (!empty($logo_prev)) : ?>
                                    <div style="margin-top:10px; padding:10px; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; display:inline-block;">
                                        <img src="<?php echo esc_url($logo_prev); ?>" alt="Brand Logo Preview" style="max-height:45px; max-width:220px; display:block;">
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <h2 style="margin-top:30px; border-bottom:1px solid #CBD5E1; padding-bottom:10px;"><i class="fa-solid fa-clock-rotate-left" style="color:#0F766E; font-size:22px;"></i> <?php _e('Guest Chat Session & Token Expiration', 'caretochina-medical'); ?></h2>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="ctc_guest_token_expiry_days"><?php _e('Guest Session Duration (Days)', 'caretochina-medical'); ?></label></th>
                            <td>
                                <input type="number" name="ctc_guest_token_expiry_days" id="ctc_guest_token_expiry_days" value="<?php echo esc_attr(get_option('ctc_guest_token_expiry_days', 90)); ?>" min="1" max="365" step="1" class="small-text" style="font-weight:700;">
                                <span><?php _e('Days (Default: 90 Days)', 'caretochina-medical'); ?></span>
                                <p class="description"><?php _e('How many days a guest chat session and token remain accessible before expiring. A real-time countdown timer is displayed in the guest chat to encourage them to register.', 'caretochina-medical'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <h2 style="margin-top:30px; border-bottom:1px solid #CBD5E1; padding-bottom:10px;"><i class="fa-solid fa-phone" style="color:#0F766E; font-size:22px;"></i> <?php _e('International Phone & Input Features', 'caretochina-medical'); ?></h2>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><?php _e('Country Flag & Code Dropdown', 'caretochina-medical'); ?></th>
                            <td>
                                <label style="font-weight:600; display:flex; align-items:flex-start; gap:8px; cursor:pointer;">
                                    <input type="checkbox" name="ctc_enable_intl_phone_flags" value="1" <?php checked($intl_phone_enabled, 1); ?> style="margin-top:2px;">
                                    <span>
                                        <?php _e('Enable Country Flag & Country Code Selector for Phone Inputs', 'caretochina-medical'); ?>
                                        <span class="description" style="display:block; margin-top:4px; font-weight:normal; color:#64748B;">
                                            <?php _e('When enabled, all phone number fields (Registration, Booking Wizard, Patient Profile) will display an international country selector with auto-formatting and dialing codes.', 'caretochina-medical'); ?>
                                        </span>
                                    </span>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ctc_phone_selector_format"><?php _e('Selector Display Format', 'caretochina-medical'); ?></label></th>
                            <td>
                                <select name="ctc_phone_selector_format" id="ctc_phone_selector_format" class="regular-text" style="font-weight:700;">
                                    <option value="both" <?php selected(get_option('ctc_phone_selector_format', 'both'), 'both'); ?>><?php _e('Both (Flag & Dial Code — e.g. 🇧🇩 +880)', 'caretochina-medical'); ?></option>
                                    <option value="flag" <?php selected(get_option('ctc_phone_selector_format', 'both'), 'flag'); ?>><?php _e('Flag Only (e.g. 🇧🇩)', 'caretochina-medical'); ?></option>
                                    <option value="code" <?php selected(get_option('ctc_phone_selector_format', 'both'), 'code'); ?>><?php _e('Code Only (e.g. +880)', 'caretochina-medical'); ?></option>
                                </select>
                                <p class="description"><?php _e('Controls whether phone input group fields display both flag and dial code, flag only, or dial code only. When "Both" or "Code" is active, duplicate dial codes typed into the phone field are automatically removed.', 'caretochina-medical'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <h2 style="margin-top:30px; border-bottom:1px solid #CBD5E1; padding-bottom:10px;"><i class="fa-brands fa-stripe" style="color:#635BFF; font-size:24px;"></i> <?php _e('Stripe Gateway Settings', 'caretochina-medical'); ?></h2>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="ctc_stripe_test_pub_key"><?php _e('Test Publishable Key', 'caretochina-medical'); ?></label></th>
                            <td><input type="text" name="ctc_stripe_test_pub_key" id="ctc_stripe_test_pub_key" value="<?php echo esc_attr($stripe_test_pub); ?>" class="regular-text" placeholder="pk_test_..." /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ctc_stripe_test_sec_key"><?php _e('Test Secret Key', 'caretochina-medical'); ?></label></th>
                            <td><input type="text" name="ctc_stripe_test_sec_key" id="ctc_stripe_test_sec_key" value="<?php echo esc_attr($stripe_test_sec_masked); ?>" class="regular-text" placeholder="sk_test_..." /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ctc_stripe_test_wh_secret"><?php _e('Test Webhook Signing Secret', 'caretochina-medical'); ?></label></th>
                            <td><input type="text" name="ctc_stripe_test_wh_secret" id="ctc_stripe_test_wh_secret" value="<?php echo esc_attr($stripe_test_wh_masked); ?>" class="regular-text" placeholder="whsec_test_..." /></td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="ctc_stripe_live_pub_key"><?php _e('Live Publishable Key', 'caretochina-medical'); ?></label></th>
                            <td><input type="text" name="ctc_stripe_live_pub_key" id="ctc_stripe_live_pub_key" value="<?php echo esc_attr($stripe_live_pub); ?>" class="regular-text" placeholder="pk_live_..." /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ctc_stripe_live_sec_key"><?php _e('Live Secret Key', 'caretochina-medical'); ?></label></th>
                            <td><input type="text" name="ctc_stripe_live_sec_key" id="ctc_stripe_live_sec_key" value="<?php echo esc_attr($stripe_live_sec_masked); ?>" class="regular-text" placeholder="sk_live_..." /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ctc_stripe_live_wh_secret"><?php _e('Live Webhook Signing Secret', 'caretochina-medical'); ?></label></th>
                            <td><input type="text" name="ctc_stripe_live_wh_secret" id="ctc_stripe_live_wh_secret" value="<?php echo esc_attr($stripe_live_wh_masked); ?>" class="regular-text" placeholder="whsec_..." /></td>
                        </tr>
                    </tbody>
                </table>

                <h2 style="margin-top:30px; border-bottom:1px solid #CBD5E1; padding-bottom:10px;"><i class="fa-brands fa-paypal" style="color:#003087; font-size:24px;"></i> <?php _e('PayPal Gateway Settings', 'caretochina-medical'); ?></h2>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="ctc_paypal_test_client_id"><?php _e('Test Client ID', 'caretochina-medical'); ?></label></th>
                            <td><input type="text" name="ctc_paypal_test_client_id" id="ctc_paypal_test_client_id" value="<?php echo esc_attr($paypal_test_client); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ctc_paypal_test_client_secret"><?php _e('Test Client Secret', 'caretochina-medical'); ?></label></th>
                            <td><input type="text" name="ctc_paypal_test_client_secret" id="ctc_paypal_test_client_secret" value="<?php echo esc_attr($paypal_test_sec_masked); ?>" class="regular-text" /></td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="ctc_paypal_live_client_id"><?php _e('Live Client ID', 'caretochina-medical'); ?></label></th>
                            <td><input type="text" name="ctc_paypal_live_client_id" id="ctc_paypal_live_client_id" value="<?php echo esc_attr($paypal_live_client); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ctc_paypal_live_client_secret"><?php _e('Live Client Secret', 'caretochina-medical'); ?></label></th>
                            <td><input type="text" name="ctc_paypal_live_client_secret" id="ctc_paypal_live_client_secret" value="<?php echo esc_attr($paypal_live_sec_masked); ?>" class="regular-text" /></td>
                        </tr>
                    </tbody>
                </table>

                <h2 style="margin-top:30px; border-bottom:1px solid #CBD5E1; padding-bottom:10px;"><i class="fa-solid fa-database" style="color:#0F766E; font-size:22px;"></i> <?php _e('Data Safety & Uninstall Management', 'caretochina-medical'); ?></h2>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><?php _e('Export Plugin Database Dump', 'caretochina-medical'); ?></th>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin-post.php?action=ctc_export_plugin_data&_wpnonce=' . $export_nonce)); ?>" class="button button-secondary" style="font-weight:700;">
                                    <i class="fa-solid fa-download"></i> <?php _e('Export Data Now (SQL)', 'caretochina-medical'); ?>
                                </a>
                                <p class="description"><?php _e('Generates a full SQL dump of bookings, pricing plans, chat payment requests, and audit logs.', 'caretochina-medical'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Uninstall Data Cleanup', 'caretochina-medical'); ?></th>
                            <td>
                                <label style="font-weight:600; color:#B91C1C;">
                                    <input type="checkbox" name="ctc_delete_data_on_uninstall" value="1" <?php checked($delete_on_uninstall, 1); ?> />
                                    <?php _e('Delete all plugin tables and settings when uninstalling plugin', 'caretochina-medical'); ?>
                                </label>
                                <p class="description" style="color:#64748B;"><?php _e('Default is OFF (data preserved). If enabled, a verified safety-net backup is created in <code>wp-content/uploads/caretochina-backups/</code> before tables are dropped.', 'caretochina-medical'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'caretochina-medical'); ?>">
                </p>
            </form>
        </div>
        <?php
    }
}
