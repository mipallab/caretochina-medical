<?php
if (!defined('ABSPATH')) {
    exit;
}

class CareToChina_Recaptcha {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public static function get_version() {
        return get_option('ctc_recaptcha_version', 'v2');
    }

    public static function get_site_key() {
        $ver = self::get_version();
        return get_option('ctc_recaptcha_' . $ver . '_site_key', '');
    }

    public static function get_secret_key() {
        $ver = self::get_version();
        $enc = get_option('ctc_recaptcha_' . $ver . '_secret_key', '');
        if (class_exists('CareToChina_Payment_Security')) {
            return CareToChina_Payment_Security::decrypt_secret($enc);
        }
        return $enc;
    }

    public static function get_threshold() {
        return floatval(get_option('ctc_recaptcha_v3_threshold', 0.5));
    }

    public static function is_configured() {
        $site_key = self::get_site_key();
        $sec_key  = self::get_secret_key();
        return !empty($site_key) && !empty($sec_key);
    }

    /**
     * Check if reCAPTCHA is active for a specific location
     *
     * @param string $location 'login' | 'register' | 'booking'
     * @return bool
     */
    public static function is_enabled_for($location) {
        if (!self::is_configured()) {
            return false;
        }

        $opt = 'ctc_recaptcha_enable_' . sanitize_key($location);
        return (bool) intval(get_option($opt, 0));
    }

    public function enqueue_scripts() {
        if (!self::is_configured()) {
            return;
        }

        $ver = self::get_version();
        $site_key = self::get_site_key();

        if ($ver === 'v3' && !empty($site_key)) {
            wp_enqueue_script(
                'google-recaptcha-v3',
                'https://www.google.com/recaptcha/api.js?render=' . esc_attr($site_key),
                [],
                null,
                true
            );
        } elseif ($ver === 'v2') {
            wp_enqueue_script(
                'google-recaptcha-v2',
                'https://www.google.com/recaptcha/api.js',
                [],
                null,
                true
            );
        }
    }

    /**
     * Render the reCAPTCHA widget / hidden token field for a form
     *
     * @param string $location 'login' | 'register' | 'booking'
     * @return string
     */
    public static function render_field($location) {
        if (!self::is_enabled_for($location)) {
            return '';
        }

        $ver = self::get_version();
        $site_key = self::get_site_key();

        if ($ver === 'v2') {
            return '<div class="ctc-recaptcha-container" style="margin: 12px 0;"><div class="g-recaptcha" data-sitekey="' . esc_attr($site_key) . '"></div></div>';
        } elseif ($ver === 'v3') {
            return '<input type="hidden" name="g-recaptcha-response" class="ctc-recaptcha-v3-token" value=""><script>if(typeof grecaptcha!=="undefined"){grecaptcha.ready(function(){grecaptcha.execute("' . esc_js($site_key) . '",{action:"' . esc_js($location) . '"}).then(function(token){document.querySelectorAll(".ctc-recaptcha-v3-token").forEach(function(el){el.value=token;});});});}</script>';
        }

        return '';
    }

    /**
     * Server-side verification against Google's siteverify API
     *
     * @param string $token
     * @param string $location
     * @return true|WP_Error
     */
    public static function verify_submission($token, $location = '') {
        if (!self::is_enabled_for($location)) {
            return true;
        }

        if (empty($token)) {
            return new WP_Error(
                'recaptcha_missing',
                __('Google reCAPTCHA verification is required. Please verify that you are human and try again.', 'caretochina-medical')
            );
        }

        $secret_key = self::get_secret_key();
        if (empty($secret_key)) {
            return true; // If secret key is not configured, don't hard block
        }

        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'timeout' => 10,
            'body'    => [
                'secret'   => $secret_key,
                'response' => sanitize_text_field($token),
                'remoteip' => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
            ],
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('recaptcha_error', __('Failed to connect to Google reCAPTCHA server.', 'caretochina-medical'));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || empty($body['success'])) {
            return new WP_Error(
                'recaptcha_failed',
                __('Google reCAPTCHA security verification failed. Please try again.', 'caretochina-medical')
            );
        }

        $ver = self::get_version();
        if ($ver === 'v3') {
            $score = floatval($body['score'] ?? 0);
            $threshold = self::get_threshold();
            if ($score < $threshold) {
                return new WP_Error(
                    'recaptcha_low_score',
                    sprintf(__('Security check failed (trust score %0.2f below threshold %0.2f). Please try again.', 'caretochina-medical'), $score, $threshold)
                );
            }
        }

        return true;
    }
}
