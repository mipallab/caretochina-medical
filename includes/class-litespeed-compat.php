<?php
/**
 * LiteSpeed Cache Performance & Compatibility Engine (Hostinger Optimized)
 *
 * Ensures seamless, conflict-free integration between CareToChina Medical Suite
 * and LiteSpeed Web Server / LiteSpeed Cache plugin (LSCache).
 *
 * Features:
 * - Dynamic endpoint exclusion via litespeed_control_set_nocache
 * - Automatic targeted cache purging on CPT updates (Hospital, Treatment, Package)
 * - Safe exclusions from LiteSpeed JS Deferral, Delay JS, and CSS Minification
 * - Lazyload exclusions for Swiper slider hero and critical UI cards
 * - Lightweight AJAX nonce refresh endpoint for cached guest pages
 *
 * @package CareToChina_Medical
 */

if (!defined('ABSPATH')) {
    exit;
}

class CareToChina_LiteSpeed_Compat {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Dynamic Page Cache Control (LSCache & Server level)
        add_action('template_redirect', [$this, 'handle_dynamic_page_cache_control'], 5);

        // LiteSpeed Optimizer Exclusions (JS Defer, Delay, Combine, Minify)
        add_filter('litespeed_optm_js_defer_exc', [$this, 'exclude_scripts_from_defer']);
        add_filter('litespeed_optm_js_exc', [$this, 'exclude_scripts_from_optm']);
        add_filter('litespeed_delay_js_exclusions', [$this, 'exclude_scripts_from_delay_js']);
        add_filter('litespeed_optm_inline_js_exc', [$this, 'exclude_inline_js']);

        // LiteSpeed CSS Exclusions & Safelisting
        add_filter('litespeed_optm_css_exc', [$this, 'exclude_css_from_optm']);
        add_filter('litespeed_ucss_exc', [$this, 'safelist_ucss_selectors']);

        // LiteSpeed Image & Media LazyLoad Exclusions
        add_filter('litespeed_media_lazy_exc', [$this, 'exclude_lazyload_images']);
        add_filter('litespeed_media_lazy_img_exc', [$this, 'exclude_lazyload_images']);

        // Automatic Targeted Cache Purging on Post & Settings Changes
        add_action('save_post_hospital', [$this, 'purge_cache_on_post_update'], 20, 2);
        add_action('save_post_medical_treatment', [$this, 'purge_cache_on_post_update'], 20, 2);
        add_action('save_post_service_package', [$this, 'purge_cache_on_post_update'], 20, 2);
        add_action('update_option_caretochina_hospital_settings', [$this, 'purge_all_litespeed_cache']);
        add_action('update_option_caretochina_pricing_settings', [$this, 'purge_all_litespeed_cache']);

        // Lightweight Nonce Refresh Endpoint for Cached Public Pages
        add_action('wp_ajax_ctc_refresh_nonces', [$this, 'ajax_refresh_nonces']);
        add_action('wp_ajax_nopriv_ctc_refresh_nonces', [$this, 'ajax_refresh_nonces']);
    }

    /**
     * Disable LiteSpeed & Browser caching on private/interactive portal pages
     */
    public function handle_dynamic_page_cache_control() {
        $dash_page_id  = class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_id('patient_dashboard') : 0;
        $staff_page_id = class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_id('staff_portal') : 0;
        $auth_page_id  = class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_id('patient_login') : 0;

        $is_dynamic_page = ($dash_page_id > 0 && is_page($dash_page_id))
            || ($staff_page_id > 0 && is_page($staff_page_id))
            || ($auth_page_id > 0 && is_page($auth_page_id))
            || (isset($_GET['ctc_action']) || isset($_GET['ctc_token']) || isset($_GET['pay_order']));

        if ($is_dynamic_page || is_user_logged_in()) {
            // LiteSpeed Cache native no-cache instruction
            if (defined('LSCWP_V')) {
                do_action('litespeed_control_set_nocache', 'CareToChina Interactive Session');
            }

            // Standard WordPress and server no-cache constants
            if (!defined('DONOTCACHEPAGE')) {
                define('DONOTCACHEPAGE', true);
            }
            if (!defined('DONOTCACHEOBJECT')) {
                define('DONOTCACHEOBJECT', true);
            }
            if (!defined('DONOTMINIFY')) {
                define('DONOTMINIFY', true);
            }

            if (!headers_sent() && $is_dynamic_page) {
                nocache_headers();
            }
        }
    }

    /**
     * Exclude critical interactive scripts from LiteSpeed JS Deferral
     *
     * @param array $exclusions
     * @return array
     */
    public function exclude_scripts_from_defer($exclusions) {
        if (!is_array($exclusions)) {
            $exclusions = [];
        }

        $items = [
            'assets/vendor/swiper/js/swiper-bundle.min.js',
            'https://js.stripe.com/v3/',
            'https://www.paypal.com/sdk/js',
            'caretochina-booking-script',
            'caretochina-staff-script',
        ];

        foreach ($items as $item) {
            if (!in_array($item, $exclusions, true)) {
                $exclusions[] = $item;
            }
        }

        return $exclusions;
    }

    /**
     * Exclude critical scripts from JS optimization/combining
     *
     * @param array $exclusions
     * @return array
     */
    public function exclude_scripts_from_optm($exclusions) {
        if (!is_array($exclusions)) {
            $exclusions = [];
        }

        $items = [
            'caretochina',
            'careyou',
            'ctc-',
            'stripe.com',
            'paypal.com',
            'intlTelInput',
        ];

        foreach ($items as $item) {
            if (!in_array($item, $exclusions, true)) {
                $exclusions[] = $item;
            }
        }

        return $exclusions;
    }

    /**
     * Exclude interactive triggers from LiteSpeed "Delay JS Execution"
     *
     * @param array $exclusions
     * @return array
     */
    public function exclude_scripts_from_delay_js($exclusions) {
        if (!is_array($exclusions)) {
            $exclusions = [];
        }

        $ctc_exclusions = [
            'caretochina',
            'careyou',
            'ctc-',
            'swiper',
            'swiper-bundle',
            'intlTelInput',
            'initHospitalsGrid',
            'initTreatmentsGrid',
            'runHospitalsSwiper',
            'appToggleTheme',
            'caretochina_obj',
            'caretochina_staff_obj',
            'caretochina_patient_obj',
            'js.stripe.com',
            'paypal.com/sdk',
        ];

        foreach ($ctc_exclusions as $item) {
            if (!in_array($item, $exclusions, true)) {
                $exclusions[] = $item;
            }
        }

        return $exclusions;
    }

    /**
     * Exclude critical inline JavaScript (Theme state engine) from minification
     *
     * @param array $inline_js
     * @return array
     */
    public function exclude_inline_js($inline_js) {
        if (!is_array($inline_js)) {
            $inline_js = [];
        }

        $items = [
            'appToggleTheme',
            'caretochina_theme',
            'caretochina_obj',
            'careyou_obj',
            'caretochina_staff_obj',
        ];

        foreach ($items as $item) {
            if (!in_array($item, $inline_js, true)) {
                $inline_js[] = $item;
            }
        }

        return $inline_js;
    }

    /**
     * Exclude CSS stylesheets from optimization if needed
     *
     * @param array $exclusions
     * @return array
     */
    public function exclude_css_from_optm($exclusions) {
        if (!is_array($exclusions)) {
            $exclusions = [];
        }

        $items = [
            'font-awesome',
            'caretochina-booking-style',
            'intl-tel-input',
        ];

        foreach ($items as $item) {
            if (!in_array($item, $exclusions, true)) {
                $exclusions[] = $item;
            }
        }

        return $exclusions;
    }

    /**
     * Safelist Dynamic CSS Selectors for LiteSpeed Unique CSS (UCSS)
     *
     * @param array $safelist
     * @return array
     */
    public function safelist_ucss_selectors($safelist) {
        if (!is_array($safelist)) {
            $safelist = [];
        }

        $selectors = [
            'dark-theme',
            'fa',
            'fas',
            'far',
            'fab',
            'fa-',
            'ctc-',
            'cy-',
            'swiper',
            'swiper-slide',
            'swiper-pagination',
            'swiper-button-next',
            'swiper-button-prev',
            'iti',
            'iti__',
            'iti--',
        ];

        foreach ($selectors as $item) {
            if (!in_array($item, $safelist, true)) {
                $safelist[] = $item;
            }
        }

        return $safelist;
    }

    /**
     * Exclude hero slider and dynamic card images from LiteSpeed LazyLoad
     *
     * @param array $exclusions
     * @return array
     */
    public function exclude_lazyload_images($exclusions) {
        if (!is_array($exclusions)) {
            $exclusions = [];
        }

        $items = [
            'ctc-hosp-img',
            'ctc-treat-img',
            'ctc-hero-slide-img',
            'swiper-slide',
            'data-no-lazy',
        ];

        foreach ($items as $item) {
            if (!in_array($item, $exclusions, true)) {
                $exclusions[] = $item;
            }
        }

        return $exclusions;
    }

    /**
     * Automatic Targeted Cache Purging on Post update (Hospital, Treatment, Service Package)
     *
     * @param int $post_id
     * @param WP_Post $post
     */
    public function purge_cache_on_post_update($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Purge specific post in LiteSpeed Cache
        if (defined('LSCWP_V')) {
            do_action('litespeed_purge_post', $post_id);
            do_action('litespeed_purge_url', home_url('/'));
        }
    }

    /**
     * Purge all LiteSpeed Cache on global plugin settings update
     */
    public function purge_all_litespeed_cache() {
        if (defined('LSCWP_V')) {
            do_action('litespeed_purge_all', 'CareToChina Global Settings Updated');
        }
    }

    /**
     * Lightweight Nonce Refresh Handler for Pages Served from LiteSpeed Cache
     */
    public function ajax_refresh_nonces() {
        wp_send_json_success([
            'booking_nonce' => wp_create_nonce('caretochina_booking_nonce'),
            'patient_nonce' => wp_create_nonce('caretochina_patient_nonce'),
            'rest_nonce'    => wp_create_nonce('wp_rest'),
        ]);
    }
}
