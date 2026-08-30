<?php
/**
 * WP Rocket Performance & Caching Compatibility Engine
 *
 * Ensures seamless, conflict-free integration between CareToChina Medical Suite
 * and WP Rocket (Page Caching, Delay JavaScript Execution, Remove Unused CSS,
 * JS Minification/Deferral, LazyLoad, and Dynamic Cache Purging).
 *
 * @package CareToChina_Medical
 */

if (!defined('ABSPATH')) {
    exit;
}

class CareToChina_WPRocket_Compat {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Dynamic Page Cache Exclusions & Headers
        add_filter('rocket_cache_reject_uri', [$this, 'exclude_dynamic_pages_from_cache']);
        add_action('template_redirect', [$this, 'handle_dynamic_page_headers_and_constants']);

        // Delay JS & Minify / Defer Exclusions
        add_filter('rocket_delay_js_exclusions', [$this, 'exclude_scripts_from_delay_js']);
        add_filter('rocket_exclude_defer_js', [$this, 'exclude_scripts_from_defer_js']);
        add_filter('rocket_minify_excluded_external_js', [$this, 'exclude_external_scripts_from_minify']);
        add_filter('rocket_excluded_inline_js', [$this, 'exclude_inline_js']);

        // Remove Unused CSS (RUCSS) / Used CSS Safelist
        add_filter('rocket_rucss_safelist', [$this, 'safelist_dynamic_css_selectors']);
        add_filter('rocket_safelist_css', [$this, 'safelist_dynamic_css_selectors']);

        // Image LazyLoad Exclusions for Sliders and Dynamic Cards
        add_filter('rocket_lazyload_excluded_attributes', [$this, 'exclude_lazyload_attributes']);
        add_filter('rocket_lazyload_excluded_src', [$this, 'exclude_lazyload_src']);

        // Automatic Cache Purging on Post & Settings Changes
        add_action('save_post_hospital', [$this, 'purge_cache_on_post_update'], 20, 2);
        add_action('save_post_medical_treatment', [$this, 'purge_cache_on_post_update'], 20, 2);
        add_action('save_post_service_package', [$this, 'purge_cache_on_post_update'], 20, 2);
        add_action('update_option_caretochina_hospital_settings', [$this, 'purge_full_domain_cache']);
        add_action('update_option_caretochina_pricing_settings', [$this, 'purge_full_domain_cache']);

        // Nonce Refresh Endpoint for Aggressively Cached Pages
        add_action('wp_ajax_ctc_refresh_nonces', [$this, 'ajax_refresh_nonces']);
        add_action('wp_ajax_nopriv_ctc_refresh_nonces', [$this, 'ajax_refresh_nonces']);
    }

    /**
     * Exclude dynamic, user-specific, coordinator desk, and checkout pages from WP Rocket Page Caching
     *
     * @param array $urls
     * @return array
     */
    public function exclude_dynamic_pages_from_cache($urls) {
        if (!is_array($urls)) {
            $urls = [];
        }

        // Get configured page slugs
        $dynamic_slugs = [
            'patient-dashboard',
            'staff-portal',
            'patient-login',
            'caretochina-desk',
            'careyou-desk',
            'ctc-payment',
        ];

        if (class_exists('CareToChina_Page_Manager')) {
            $types = ['patient_dashboard', 'staff_portal', 'patient_login'];
            foreach ($types as $type) {
                $page_id = CareToChina_Page_Manager::get_page_id($type);
                if ($page_id > 0) {
                    $slug = get_post_field('post_name', $page_id);
                    if (!empty($slug) && !in_array($slug, $dynamic_slugs, true)) {
                        $dynamic_slugs[] = $slug;
                    }
                }
            }
        }

        foreach ($dynamic_slugs as $slug) {
            $pattern = '(.*)/' . preg_quote($slug, '/') . '/(.*)';
            if (!in_array($pattern, $urls, true)) {
                $urls[] = $pattern;
            }
            $pattern_exact = '/' . preg_quote($slug, '/') . '/(.*)';
            if (!in_array($pattern_exact, $urls, true)) {
                $urls[] = $pattern_exact;
            }
        }

        return $urls;
    }

    /**
     * Define DONOTCACHEPAGE and set headers on dynamic endpoints
     */
    public function handle_dynamic_page_headers_and_constants() {
        $dash_page_id  = class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_id('patient_dashboard') : 0;
        $staff_page_id = class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_id('staff_portal') : 0;
        $auth_page_id  = class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_id('patient_login') : 0;

        $is_dynamic_page = ($dash_page_id > 0 && is_page($dash_page_id))
            || ($staff_page_id > 0 && is_page($staff_page_id))
            || ($auth_page_id > 0 && is_page($auth_page_id))
            || (isset($_GET['ctc_action']) || isset($_GET['ctc_token']) || isset($_GET['pay_order']));

        if ($is_dynamic_page || is_user_logged_in()) {
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
     * Exclude critical interactive scripts from WP Rocket's "Delay JavaScript Execution"
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
            'caretochina_booking_obj',
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
     * Exclude scripts from Deferral
     *
     * @param array $excluded_scripts
     * @return array
     */
    public function exclude_scripts_from_defer_js($excluded_scripts) {
        if (!is_array($excluded_scripts)) {
            $excluded_scripts = [];
        }

        $items = [
            'assets/vendor/swiper/js/swiper-bundle.min.js',
            'https://js.stripe.com/v3/',
            'https://www.paypal.com/sdk/js',
        ];

        foreach ($items as $item) {
            if (!in_array($item, $excluded_scripts, true)) {
                $excluded_scripts[] = $item;
            }
        }

        return $excluded_scripts;
    }

    /**
     * Exclude third party CDNs/SDKs from JS minification
     *
     * @param array $external_js
     * @return array
     */
    public function exclude_external_scripts_from_minify($external_js) {
        if (!is_array($external_js)) {
            $external_js = [];
        }

        $items = [
            'https://js.stripe.com/v3/',
            'https://www.paypal.com/sdk/js',
        ];

        foreach ($items as $item) {
            if (!in_array($item, $external_js, true)) {
                $external_js[] = $item;
            }
        }

        return $external_js;
    }

    /**
     * Exclude critical inline JS (Dark mode engine & theme state) from minification
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
            'caretochina_booking_obj',
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
     * Safelist Dynamic CSS Selectors for WP Rocket's Remove Unused CSS (RUCSS)
     *
     * @param array $safelist
     * @return array
     */
    public function safelist_dynamic_css_selectors($safelist) {
        if (!is_array($safelist)) {
            $safelist = [];
        }

        $selectors = [
            // Dark Mode
            '(html|body)\.dark-theme(.*)',
            '\.dark-theme(.*)',
            // CareToChina UI & Components
            '\.ctc-(.*)',
            '\.cy-(.*)',
            // Tags & Badges
            '\.popular-tag(.*)',
            '\.ultimate-tag(.*)',
            '\.plan-tag(.*)',
            '\.badge(.*)',
            // Swiper Carousels & Sliders
            '\.swiper(.*)',
            '\.swiper-slide(.*)',
            '\.swiper-pagination(.*)',
            '\.swiper-button-(next|prev)(.*)',
            // International Tel Input
            '\.iti(.*)',
            '\.iti__(.*)',
            '\.iti--(.*)',
        ];

        foreach ($selectors as $pattern) {
            if (!in_array($pattern, $safelist, true)) {
                $safelist[] = $pattern;
            }
        }

        return $safelist;
    }

    /**
     * Exclude Swiper slides and dynamic cards from LazyLoad attributes to avoid broken initial slide layouts
     *
     * @param array $attributes
     * @return array
     */
    public function exclude_lazyload_attributes($attributes) {
        if (!is_array($attributes)) {
            $attributes = [];
        }

        $items = [
            'ctc-hosp-img',
            'ctc-treat-img',
            'ctc-hero-slide-img',
            'swiper-slide',
            'data-no-lazy',
        ];

        foreach ($items as $item) {
            if (!in_array($item, $attributes, true)) {
                $attributes[] = $item;
            }
        }

        return $attributes;
    }

    /**
     * Exclude specific image sources from LazyLoad
     *
     * @param array $excluded_src
     * @return array
     */
    public function exclude_lazyload_src($excluded_src) {
        if (!is_array($excluded_src)) {
            $excluded_src = [];
        }

        $items = [
            'hero-hospital-slider',
        ];

        foreach ($items as $item) {
            if (!in_array($item, $excluded_src, true)) {
                $excluded_src[] = $item;
            }
        }

        return $excluded_src;
    }

    /**
     * Automatic Cache Purging on Post update (Hospital, Treatment, Service Package)
     *
     * @param int $post_id
     * @param WP_Post $post
     */
    public function purge_cache_on_post_update($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Clean specific post cache in WP Rocket
        if (function_exists('rocket_clean_post')) {
            rocket_clean_post($post_id);
        }

        // Also clean home page and archives
        if (function_exists('rocket_clean_home')) {
            rocket_clean_home();
        }
    }

    /**
     * Purge Entire Domain Cache in WP Rocket on Global Settings Update
     */
    public function purge_full_domain_cache() {
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
    }

    /**
     * Lightweight Nonce Refresh Handler for Pages Served from Long-term Page Caches
     */
    public function ajax_refresh_nonces() {
        wp_send_json_success([
            'booking_nonce' => wp_create_nonce('caretochina_booking_nonce'),
            'patient_nonce' => wp_create_nonce('caretochina_patient_nonce'),
            'rest_nonce'    => wp_create_nonce('wp_rest'),
        ]);
    }
}
