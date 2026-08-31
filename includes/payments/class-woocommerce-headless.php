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
        add_filter('woocommerce_is_purchasable', [$this, 'filter_is_purchasable'], 99, 2);
        add_filter('woocommerce_locate_template', [$this, 'override_wc_templates'], 20, 3);
        add_filter('wc_get_template', [$this, 'intercept_wc_template'], 20, 5);
    }

    /**
     * Redirect native WooCommerce storefront, shop, cart & account pages to patient dashboard
     */
    public function restrict_wc_frontend_pages() {
        if (!get_option('ctc_wc_redirect_frontend_pages', 1)) {
            return;
        }

        if (!function_exists('is_woocommerce') && !function_exists('is_cart')) {
            return;
        }

        // Always allow checkout and order confirmation pages for payment flow
        if (is_checkout() || (function_exists('is_order_received_page') && is_order_received_page())) {
            return;
        }

        // Allow Administrators and Staff to view WC pages for debugging if necessary
        if (current_user_can('manage_options') || current_user_can('caretochina_manage_bookings')) {
            return;
        }

        if (is_cart() || is_shop() || is_product() || is_account_page() || is_product_taxonomy()) {
            $dash_url = class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_url('patient_dashboard') : home_url('/patient-dashboard/');
            wp_safe_redirect($dash_url);
            exit;
        }
    }

    /**
     * Allow medical booking and package products to be purchasable at checkout
     */
    public function filter_is_purchasable($purchasable, $product) {
        if (!$product) {
            return $purchasable;
        }

        // If product has _caretochina_package_id or is virtual package line item, allow purchase
        $is_c2c = $product->get_meta('_caretochina_package_id') || $product->get_meta('_caretochina_product') || $product->is_virtual();
        if ($is_c2c) {
            return true;
        }

        // Otherwise prevent direct standard catalog checkout if headless mode is active
        return get_option('ctc_wc_headless_admin_menus', 1) ? false : $purchasable;
    }

    /**
     * Override WooCommerce template files with CareToChina custom medical templates
     */
    public function override_wc_templates($template, $template_name, $template_path) {
        if (!get_option('ctc_wc_custom_checkout_templates', 1)) {
            return $template;
        }

        $plugin_dir = plugin_dir_path(dirname(__DIR__));

        if ($template_name === 'checkout/form-checkout.php') {
            $custom_template = $plugin_dir . 'templates/woocommerce/checkout/form-checkout.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        if ($template_name === 'checkout/thankyou.php') {
            $custom_template = $plugin_dir . 'templates/woocommerce/checkout/thankyou.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        return $template;
    }

    /**
     * Intercept direct wc_get_template calls
     */
    public function intercept_wc_template($located, $template_name, $args, $template_path, $default_path) {
        if (!get_option('ctc_wc_custom_checkout_templates', 1)) {
            return $located;
        }

        $plugin_dir = plugin_dir_path(dirname(__DIR__));

        if ($template_name === 'checkout/form-checkout.php') {
            $custom = $plugin_dir . 'templates/woocommerce/checkout/form-checkout.php';
            if (file_exists($custom)) {
                return $custom;
            }
        }

        if ($template_name === 'checkout/thankyou.php') {
            $custom = $plugin_dir . 'templates/woocommerce/checkout/thankyou.php';
            if (file_exists($custom)) {
                return $custom;
            }
        }

        return $located;
    }
}
