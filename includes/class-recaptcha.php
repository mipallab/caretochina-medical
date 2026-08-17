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
        add_action('login_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_head', [$this, 'output_badge_hide_css'], 9999);
        add_action('login_head', [$this, 'output_badge_hide_css'], 9999);
        add_action('admin_head', [$this, 'output_badge_hide_css'], 9999);
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

    /**
     * Check if floating reCAPTCHA badge should be hidden
     *
     * @return bool
     */
    public static function is_badge_hidden() {
        return (bool) intval(get_option('ctc_recaptcha_hide_badge', 0));
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

        if (self::is_badge_hidden()) {
            wp_register_style('ctc-recaptcha-badge-hide', false);
            wp_enqueue_style('ctc-recaptcha-badge-hide');
            wp_add_inline_style('ctc-recaptcha-badge-hide', '.grecaptcha-badge { visibility: hidden !important; opacity: 0 !important; pointer-events: none !important; }');
        }
    }

    /**
     * Direct HTML head output for badge hide CSS to ensure 100% reliability across all cache/theme configs
     */
    public function output_badge_hide_css() {
        if (self::is_badge_hidden()) {
            echo "\n" . '<style id="ctc-recaptcha-badge-hide-direct-css">.grecaptcha-badge { visibility: hidden !important; opacity: 0 !important; pointer-events: none !important; }</style>' . "\n";
        }
    }

    /**
     * Get the attribution text required by Google Terms of Service when badge is hidden
     *
     * @return string
     */
    public static function get_attribution_html() {
        return '<div class="ctc-recaptcha-attribution" style="font-size: 11px; color: #64748b; line-height: 1.4; margin-top: 8px; margin-bottom: 12px;">' .
            sprintf(
                /* translators: %1$s: Privacy Policy link, %2$s: Terms of Service link */
                __('This site is protected by reCAPTCHA and the Google %1$s and %2$s apply.', 'caretochina-medical'),
                '<a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: underline;">' . __('Privacy Policy', 'caretochina-medical') . '</a>',
                '<a href="https://policies.google.com/terms" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: underline;">' . __('Terms of Service', 'caretochina-medical') . '</a>'
            ) .
        '</div>';
    }

    /**
     * Helper to render attribution text conditionally for a location
     *
     * @param string $location 'login' | 'register' | 'booking'
     * @return string
     */
    public static function render_attribution($location = '') {
        if (!empty($location) && !self::is_enabled_for($location)) {
            return '';
        }
        if (!self::is_badge_hidden()) {
            return '';
        }
        return self::get_attribution_html();
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
        $output = '';

        if ($ver === 'v2') {
            $output .= '<div class="ctc-recaptcha-container" style="margin: 12px 0;"><div class="g-recaptcha" data-sitekey="' . esc_attr($site_key) . '"></div></div>';
        } elseif ($ver === 'v3') {
            $output .= '<input type="hidden" name="g-recaptcha-response" class="ctc-recaptcha-v3-token" value=""><script>if(typeof grecaptcha!=="undefined"){grecaptcha.ready(function(){grecaptcha.execute("' . esc_js($site_key) . '",{action:"' . esc_js($location) . '"}).then(function(token){document.querySelectorAll(".ctc-recaptcha-v3-token").forEach(function(el){el.value=token;});});});}</script>';
        }

        if (self::is_badge_hidden()) {
            $output .= self::get_attribution_html();
        }

        return $output;
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
