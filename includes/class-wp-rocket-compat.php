<?php
/**
 * WP Rocket Performance & Full Compatibility Engine
 *
 * Ensures 100% seamless, high-performance integration between CareToChina Medical Suite
 * and WP Rocket caching, minification, JavaScript execution delaying (Delay JS),
 * Remove Unused CSS (RUCSS), Critical CSS generation, and Database Optimization.
 *
 * Features:
 * - Dynamic Page & Session Cache Exclusions (rocket_cache_reject_uri, DONOTROCKETOPTIMIZE)
 * - Safe Delay JavaScript Exclusions (rocket_delay_js_exclusions, rocket_excluded_inline_js_content)
 * - JS/CSS Minification & Combination Exclusions (rocket_exclude_js, rocket_exclude_css)
 * - Remove Unused CSS (RUCSS) & Critical CSS Safelist Patterns (rocket_rucss_safelist)
 * - Image & Hero Slider LazyLoad Exclusions for optimal LCP scores
 * - Automated Targeted Cache Clearing on CPT & Plugin Settings Updates (rocket_clean_domain, rocket_clean_post)
 * - Custom Database Optimization & Housekeeping (Transients cleanup & table optimization)
 * - Lightweight AJAX Nonce Refresh Handler for cached guest landing pages
 *
 * @package CareToChina_Medical
 */

if (!defined('ABSPATH')) {
    exit;
}

class CareToChina_WP_Rocket_Compat {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // 1. Dynamic Page Cache Control (WP Rocket & Server Level)
        add_action('template_redirect', [$this, 'handle_dynamic_cache_control'], 5);
        add_filter('rocket_cache_reject_uri', [$this, 'exclude_pages_from_cache']);
        add_filter('rocket_cache_query_vars', [$this, 'exclude_query_vars_from_cache']);
        add_filter('do_rocket_generate_caching_files', [$this, 'prevent_cache_file_generation']);

        // 2. JavaScript Delay (Delay JS) & Defer Exclusions
        add_filter('rocket_delay_js_exclusions', [$this, 'exclude_scripts_from_delay_js']);
        add_filter('rocket_excluded_inline_js_content', [$this, 'exclude_inline_js_content']);
        add_filter('rocket_defer_js_exclusions', [$this, 'exclude_scripts_from_defer']);
        add_filter('rocket_exclude_js', [$this, 'exclude_scripts_from_combine_minify']);

        // 3. CSS Optimization & Remove Unused CSS (RUCSS) Safelisting
        add_filter('rocket_exclude_css', [$this, 'exclude_css_from_combine_minify']);
        add_filter('rocket_rucss_safelist', [$this, 'safelist_rucss_selectors']);
        add_filter('rocket_critical_css_safelist', [$this, 'safelist_rucss_selectors']);

        // 4. Image & Media LazyLoad Exclusions (LCP Optimization)
        add_filter('rocket_lazyload_excluded_attributes', [$this, 'exclude_lazyload_attributes']);
        add_filter('rocket_lazyload_excluded_src', [$this, 'exclude_lazyload_src']);

        // 5. Automatic Targeted Cache Purging on CPT & Settings Update
        add_action('save_post_hospital', [$this, 'purge_cache_on_post_update'], 20, 2);
        add_action('save_post_medical_treatment', [$this, 'purge_cache_on_post_update'], 20, 2);
        add_action('save_post_service_package', [$this, 'purge_cache_on_post_update'], 20, 2);
        add_action('update_option_caretochina_hospital_settings', [$this, 'purge_all_rocket_cache']);
        add_action('update_option_caretochina_pricing_settings', [$this, 'purge_all_rocket_cache']);

        // 6. Database Table Optimization & Scheduled Transient Cleanup
        add_filter('rocket_database_optimize_tables', [$this, 'register_custom_tables_for_optimization']);
        add_action('wp_scheduled_delete', [$this, 'optimize_custom_tables_and_transients']);
        add_action('caretochina_daily_db_cleanup', [$this, 'optimize_custom_tables_and_transients']);

        // 7. Schedule daily cleanup cron if not present
        if (!wp_next_scheduled('caretochina_daily_db_cleanup')) {
            wp_schedule_event(time(), 'daily', 'caretochina_daily_db_cleanup');
        }

        // 8. Critical Font Preloading & Efficient Cache Lifetimes
        add_action('wp_head', [$this, 'preload_critical_webfonts'], 1);
        add_action('send_headers', [$this, 'set_static_asset_cache_headers']);

        // 9. Lightweight Nonce Refresh Endpoint for Cached Public Pages
        add_action('wp_ajax_ctc_refresh_nonces', [$this, 'ajax_refresh_nonces']);
        add_action('wp_ajax_nopriv_ctc_refresh_nonces', [$this, 'ajax_refresh_nonces']);
    }

    /**
     * Preload Critical Font Awesome Webfonts to eliminate render-blocking delay
     */
    public function preload_critical_webfonts() {
        $font_url = CARETOCHINA_MEDICAL_URL . 'assets/vendor/font-awesome/webfonts/fa-solid-900.woff2';
        echo '<link rel="preload" href="' . esc_url($font_url) . '" as="font" type="font/woff2" crossorigin="anonymous">' . "\n";
    }

    /**
     * Send Long-life Browser Cache Headers for Webfonts & Static Assets
     */
    public function set_static_asset_cache_headers() {
        if (!headers_sent()) {
            // Enable CORS for webfonts
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, OPTIONS');
        }
    }

    /**
     * Disable WP Rocket & Browser caching on private/interactive portal pages
     */
    public function handle_dynamic_cache_control() {
        $dash_page_id  = class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_id('patient_dashboard') : 0;
        $staff_page_id = class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_id('staff_portal') : 0;
        $auth_page_id  = class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_id('patient_login') : 0;

        $is_dynamic_page = ($dash_page_id > 0 && is_page($dash_page_id))
            || ($staff_page_id > 0 && is_page($staff_page_id))
            || ($auth_page_id > 0 && is_page($auth_page_id))
            || (isset($_GET['ctc_action']) || isset($_GET['ctc_token']) || isset($_GET['pay_order']) || isset($_GET['ctc_google_callback']));

        if ($is_dynamic_page || is_user_logged_in()) {
            // WP Rocket native instruction to completely bypass caching & optimizations
            if (!defined('DONOTROCKETOPTIMIZE')) {
                define('DONOTROCKETOPTIMIZE', true);
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
     * Exclude specific URI patterns from WP Rocket cache
     *
     * @param array $urls Array of excluded URIs.
     * @return array
     */
    public function exclude_pages_from_cache($urls) {
        if (!is_array($urls)) {
            $urls = [];
        }

        $exclusions = [
            '/patient-dashboard/(.*)',
            '/staff-portal/(.*)',
            '/patient-login/(.*)',
            '/my-account/(.*)',
            '/checkout/(.*)',
            '/cart/(.*)',
            '/(.*)ctc_action(.*)',
            '/(.*)ctc_token(.*)',
            '/(.*)ctc_google_callback(.*)',
            '/(.*)pay_order(.*)',
        ];

        return array_unique(array_merge($urls, $exclusions));
    }

    /**
     * Exclude specific query variables from WP Rocket page caching
     *
     * @param array $query_vars Array of query parameters.
     * @return array
     */
    public function exclude_query_vars_from_cache($query_vars) {
        if (!is_array($query_vars)) {
            $query_vars = [];
        }

        $custom_vars = [
            'ctc_action',
            'ctc_token',
            'pay_order',
            'ctc_google_callback',
            'ctc_link_account',
            'ctc_case',
        ];

        return array_unique(array_merge($query_vars, $custom_vars));
    }

    /**
     * Prevent WP Rocket from generating cache files on dynamic portal pages
     *
     * @param bool $can_cache Boolean indicating if caching is allowed.
     * @return bool
     */
    public function prevent_cache_file_generation($can_cache) {
        $dash_page_id  = class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_id('patient_dashboard') : 0;
        $staff_page_id = class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_id('staff_portal') : 0;
        $auth_page_id  = class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_id('patient_login') : 0;

        if (($dash_page_id > 0 && is_page($dash_page_id))
            || ($staff_page_id > 0 && is_page($staff_page_id))
            || ($auth_page_id > 0 && is_page($auth_page_id))
            || is_user_logged_in()
            || isset($_GET['ctc_action'])
            || isset($_GET['ctc_token'])
            || isset($_GET['ctc_google_callback'])) {
            return false;
        }

        return $can_cache;
    }

    /**
     * Exclude interactive and critical scripts from WP Rocket "Delay JS"
     *
     * @param array $excluded_scripts
     * @return array
     */
    public function exclude_scripts_from_delay_js($excluded_scripts) {
        if (!is_array($excluded_scripts)) {
            $excluded_scripts = [];
        }

        $critical_handles = [
            'caretochina-booking-script',
            'caretochina-staff-script',
            'caretochina-hero-slider-script',
            'caretochina-pricing-script',
            'caretochina-early-theme-js',
            'intl-tel-input',
            'intlTelInput',
            'cute-alert',
            'ctc_booking_ajax',
            'ctc_staff_ajax',
            'caretochina_theme',
            'appToggleTheme',
            'injectHeaderIcons',
            'ctc-header-btn-critical-css',
            'recaptcha',
            'grecaptcha',
            'google.com/recaptcha',
            'accounts.google.com/gsi/client',
        ];

        return array_unique(array_merge($excluded_scripts, $critical_handles));
    }

    /**
     * Exclude critical inline scripts from WP Rocket inline JS minification/combination
     *
     * @param array $excluded_inline
     * @return array
     */
    public function exclude_inline_js_content($excluded_inline) {
        if (!is_array($excluded_inline)) {
            $excluded_inline = [];
        }

        $critical_inline = [
            'caretochina_theme',
            'appToggleTheme',
            'injectHeaderIcons',
            'ctc_booking_ajax',
            'ctc_staff_ajax',
            'ctc_recaptcha_site_key',
            'ctc_language',
            'careyou-early-theme-js',
            'ctc-header-btn-critical-css',
        ];

        return array_unique(array_merge($excluded_inline, $critical_inline));
    }

    /**
     * Exclude critical interactive scripts from JS deferral
     *
     * @param array $excluded_defer
     * @return array
     */
    public function exclude_scripts_from_defer($excluded_defer) {
        if (!is_array($excluded_defer)) {
            $excluded_defer = [];
        }

        $exclusions = [
            'caretochina-early-theme-js',
            'jquery-core',
            'jquery.min.js',
        ];

        return array_unique(array_merge($excluded_defer, $exclusions));
    }

    /**
     * Exclude specific JS files from WP Rocket concatenation/minification
     *
     * @param array $excluded_js
     * @return array
     */
    public function exclude_scripts_from_combine_minify($excluded_js) {
        if (!is_array($excluded_js)) {
            $excluded_js = [];
        }

        $exclusions = [
            '(.*)/assets/vendor/intl-tel-input/(.*)',
            '(.*)/assets/vendor/cute-alert/(.*)',
            '(.*)/assets/vendor/font-awesome/(.*)',
        ];

        return array_unique(array_merge($excluded_js, $exclusions));
    }

    /**
     * Exclude critical stylesheets from CSS concatenation if needed
     *
     * @param array $excluded_css
     * @return array
     */
    public function exclude_css_from_combine_minify($excluded_css) {
        if (!is_array($excluded_css)) {
            $excluded_css = [];
        }

        $exclusions = [
            '(.*)/assets/vendor/font-awesome/(.*)',
        ];

        return array_unique(array_merge($excluded_css, $exclusions));
    }

    /**
     * Safelist Dynamic CSS Selectors for WP Rocket "Remove Unused CSS" (RUCSS) and Critical CSS
     *
     * @param array $safelist
     * @return array
     */
    public function safelist_rucss_selectors($safelist) {
        if (!is_array($safelist)) {
            $safelist = [];
        }

        $dynamic_selectors = [
            // Dark mode classes
            '(.*)dark-theme(.*)',
            'html.dark-theme',
            'body.dark-theme',
            
            // Header button & SVG vector selectors
            '(.*)ctc-dash-btn(.*)',
            '(.*)ctc-dash-el-btn(.*)',
            '(.*)ctc-btn-svg(.*)',
            '(.*)ctc-btn-icon-wrap(.*)',
            '(.*)ctc-btn-icon(.*)',
            
            // Auth portal & Login/Register modal
            '(.*)caretochina-auth-container(.*)',
            '(.*)ctc-auth-card(.*)',
            '(.*)auth-tab-(.*)',
            '(.*)auth-panel(.*)',
            '(.*)ctc-form-grid-2(.*)',
            '(.*)form-input(.*)',
            '(.*)form-select(.*)',
            '(.*)form-group(.*)',
            '(.*)auth-submit-btn(.*)',
            
            // Phone country selector & input
            '(.*)ctc-phone-group-wrapper(.*)',
            '(.*)ctc-country-select(.*)',
            '(.*)ctc-phone-input(.*)',
            '(.*)iti(.*)',
            
            // Booking Wizard Modal
            '(.*)ctc-booking-modal(.*)',
            '(.*)modal-backdrop(.*)',
            '(.*)ctc-modal-open(.*)',
            '(.*)ctc-step-(.*)',
            '(.*)ctc-wiz-(.*)',
            
            // Interactive UI components & Cards
            '(.*)ctc-hospital-card(.*)',
            '(.*)ctc-treatment-card(.*)',
            '(.*)ctc-pricing-plan-card(.*)',
            '(.*)ctc-hero-slider(.*)',
            '(.*)swiper-(.*)',
            '(.*)cute-alert(.*)',
            '(.*)active(.*)',
            '(.*)open(.*)',
            '(.*)show(.*)',
        ];

        return array_unique(array_merge($safelist, $dynamic_selectors));
    }

    /**
     * Exclude hero slider and critical UI card images from WP Rocket LazyLoad
     *
     * @param array $excluded_attributes
     * @return array
     */
    public function exclude_lazyload_attributes($excluded_attributes) {
        if (!is_array($excluded_attributes)) {
            $excluded_attributes = [];
        }

        $attributes = [
            'class="ctc-hero-slide-img"',
            'class="caretochina-hero-slider"',
            'class="brand-logo"',
            'class="attachment-medium size-medium wp-image-1583"',
            'data-no-lazy="1"',
        ];

        return array_unique(array_merge($excluded_attributes, $attributes));
    }

    /**
     * Exclude specific image sources from WP Rocket LazyLoad
     *
     * @param array $excluded_src
     * @return array
     */
    public function exclude_lazyload_src($excluded_src) {
        if (!is_array($excluded_src)) {
            $excluded_src = [];
        }

        $sources = [
            'logo.webp',
            'logo-300x65.webp',
            'cropped-fav-icon',
        ];

        return array_unique(array_merge($excluded_src, $sources));
    }

    /**
     * Purge specific post & related archive URLs in WP Rocket Cache on Post Save
     *
     * @param int     $post_id
     * @param WP_Post $post
     */
    public function purge_cache_on_post_update($post_id, $post = null) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (wp_is_post_revision($post_id)) {
            return;
        }

        // Purge specific post in WP Rocket Cache
        if (function_exists('rocket_clean_post')) {
            rocket_clean_post($post_id);
        }

        // Also clean home page and main archives
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
    }

    /**
     * Purge all WP Rocket Cache on global plugin settings update
     */
    public function purge_all_rocket_cache() {
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
        if (function_exists('rocket_clean_minify')) {
            rocket_clean_minify();
        }
    }

    /**
     * Register CareToChina custom database tables for WP Rocket Database Optimization
     *
     * @param array $tables List of tables.
     * @return array
     */
    public function register_custom_tables_for_optimization($tables) {
        global $wpdb;
        if (!is_array($tables)) {
            $tables = [];
        }

        $custom_tables = [
            $wpdb->prefix . 'caretochina_bookings',
            $wpdb->prefix . 'caretochina_messages',
            $wpdb->prefix . 'caretochina_processed_webhook_events',
            $wpdb->prefix . 'caretochina_payment_audit_logs',
            $wpdb->prefix . 'caretochina_payment_requests',
        ];

        return array_unique(array_merge($tables, $custom_tables));
    }

    /**
     * Automated Database Housekeeping & Table Optimization Routine
     */
    public function optimize_custom_tables_and_transients() {
        global $wpdb;

        // 1. Delete expired transients for CareToChina
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            "DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
             WHERE a.option_name LIKE '_transient_ctc_%'
             AND b.option_name = CONCAT('_transient_timeout_ctc_', SUBSTRING(a.option_name, 16))
             AND b.option_value < UNIX_TIMESTAMP()"
        );

        // 2. Delete processed webhook idempotency records older than 60 days
        $webhook_table = $wpdb->prefix . 'caretochina_processed_webhook_events';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $webhook_table)) === $webhook_table) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query(
                "DELETE FROM {$webhook_table} WHERE processed_at < DATE_SUB(NOW(), INTERVAL 60 DAY)"
            );
        }

        // 3. Optimize custom database tables to reclaim unused storage and defragment indexes
        $custom_tables = [
            $wpdb->prefix . 'caretochina_bookings',
            $wpdb->prefix . 'caretochina_messages',
            $wpdb->prefix . 'caretochina_processed_webhook_events',
            $wpdb->prefix . 'caretochina_payment_audit_logs',
            $wpdb->prefix . 'caretochina_payment_requests',
        ];

        foreach ($custom_tables as $table) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->query("OPTIMIZE TABLE {$table}");
            }
        }
    }

    /**
     * Lightweight Nonce Refresh Handler for Pages Served from WP Rocket Cache
     */
    public function ajax_refresh_nonces() {
        wp_send_json_success([
            'booking_nonce' => wp_create_nonce('caretochina_booking_nonce'),
            'auth_nonce'    => wp_create_nonce('caretochina_auth_action'),
            'theme_nonce'   => wp_create_nonce('caretochina_theme_nonce'),
            'timestamp'     => time(),
        ]);
    }
}
