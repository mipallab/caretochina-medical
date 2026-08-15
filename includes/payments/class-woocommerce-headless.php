<?php
if (!defined('ABSPATH')) {
    exit;
}

class CareToChina_WooCommerce_Headless {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('template_redirect', [$this, 'restrict_wc_frontend_pages']);
        add_filter('woocommerce_is_purchasable', '__return_false', 99); // Prevent direct WC shop purchases
    }

    /**
     * Redirect native WooCommerce storefront, shop, cart, checkout & account pages to patient dashboard
     */
    public function restrict_wc_frontend_pages() {
        if (!function_exists('is_woocommerce')) {
            return;
        }

        // Allow Administrators and Staff to view WC pages for debugging if necessary
        if (current_user_can('manage_options') || current_user_can('caretochina_manage_bookings')) {
            return;
        }

        if (is_cart() || is_checkout() || is_shop() || is_product() || is_account_page() || is_woocommerce()) {
            $dash_url = class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_url('patient_dashboard') : home_url('/patient-dashboard/');
            wp_redirect($dash_url);
            exit;
        }
    }
}
