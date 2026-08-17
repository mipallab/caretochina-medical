<?php
if (!defined('ABSPATH')) {
    exit;
}

// 1. Require Payment Infrastructure Files
require_once __DIR__ . '/class-payment-security.php';
require_once __DIR__ . '/class-treatment-product-sync.php';
require_once __DIR__ . '/interface-payment-gateway.php';
require_once __DIR__ . '/class-stripe-gateway.php';
require_once __DIR__ . '/class-paypal-gateway.php';
require_once __DIR__ . '/class-payment-manager.php';
require_once __DIR__ . '/class-payment-reconciliation.php';
require_once __DIR__ . '/class-woocommerce-headless.php';
require_once __DIR__ . '/class-woocommerce-admin-cleanup.php';
require_once __DIR__ . '/class-payment-api.php';
require_once __DIR__ . '/class-payment-admin-settings.php';
require_once __DIR__ . '/class-payment-request-manager.php';
require_once __DIR__ . '/class-transaction-cpt.php';

// Require Google OAuth Auth handler
if (file_exists(dirname(__DIR__) . '/auth/class-google-login.php')) {
    require_once dirname(__DIR__) . '/auth/class-google-login.php';
}

// 2. Initialize Module Singletons on plugins_loaded
add_action('plugins_loaded', function() {
    CareToChina_Treatment_Product_Sync::instance();
    CareToChina_Payment_Manager::instance();
    CareToChina_Payment_Reconciliation::instance();
    CareToChina_WooCommerce_Headless::instance();
    CareToChina_WooCommerce_Admin_Cleanup::instance();
    CareToChina_Payment_API::instance();
    CareToChina_Payment_Admin_Settings::instance();
    CareToChina_Payment_Request_Manager::instance();
    CareToChina_Transaction_CPT::instance();

    if (class_exists('CareToChina_Google_Login')) {
        CareToChina_Google_Login::instance();
    }

    // Dependency check notice for site administrators
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            if (current_user_can('manage_options')) {
                echo '<div class="notice notice-warning is-dismissible"><p><strong>' . esc_html__('CareToChina Medical Suite Notice:', 'caretochina-medical') . '</strong> ' . esc_html__('WooCommerce is required for online treatment payment processing. Please install and activate WooCommerce.', 'caretochina-medical') . '</p></div>';
            }
        });
    }
}, 20);
