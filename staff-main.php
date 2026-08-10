<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once CARETOCHINA_STAFF_PATH . 'includes/class-staff-portal.php';

class CareToChina_Medical_Staff_Plugin {
    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('init', [$this, 'register_polylang_strings']);

        add_action('wp_enqueue_scripts', [$this, 'enqueue_staff_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        CareToChina_Staff_Portal::instance();
    }

    public function load_textdomain() {
        load_plugin_textdomain('caretochina-staff', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function register_polylang_strings() {
        if (function_exists('pll_register_string')) {
            pll_register_string('Medical Coordinator Control Desk', 'Medical Coordinator Control Desk', 'CareToChina Staff Desk');
            pll_register_string('Bookings & Approvals', 'Bookings & Approvals', 'CareToChina Staff Desk');
            pll_register_string('Patient Live Chat', 'Patient Live Chat', 'CareToChina Staff Desk');
            pll_register_string('Treatment Timeline', 'Treatment Timeline', 'CareToChina Staff Desk');
            pll_register_string('Invoices & Payments', 'Invoices & Payments', 'CareToChina Staff Desk');
        }
    }

    public function enqueue_staff_assets() {
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', [], '6.4.0');
        wp_enqueue_style('google-fonts-caretochina-staff', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap', [], null);
        wp_enqueue_style('caretochina-staff-style', CARETOCHINA_STAFF_URL . 'assets/css/staff-style.css', [], CARETOCHINA_STAFF_VERSION);
        wp_enqueue_script('caretochina-staff-script', CARETOCHINA_STAFF_URL . 'assets/js/staff-script.js', ['jquery'], CARETOCHINA_STAFF_VERSION, true);

        $localized_data = [
            'ajax_url' => wp_parse_url(admin_url('admin-ajax.php'), PHP_URL_PATH),
            'nonce'    => wp_create_nonce('caretochina_staff_nonce'),
        ];

        wp_localize_script('caretochina-staff-script', 'caretochina_staff_obj', $localized_data);
        wp_localize_script('caretochina-staff-script', 'careyou_staff_obj', $localized_data);
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'caretochina-staff-desk') !== false || strpos($hook, 'careyou-staff-desk') !== false) {
            wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', [], '6.4.0');
            wp_enqueue_style('caretochina-staff-admin-style', CARETOCHINA_STAFF_URL . 'assets/css/staff-style.css', [], CARETOCHINA_STAFF_VERSION);
            wp_enqueue_script('caretochina-staff-script', CARETOCHINA_STAFF_URL . 'assets/js/staff-script.js', ['jquery'], CARETOCHINA_STAFF_VERSION, true);

            $localized_data = [
                'ajax_url' => wp_parse_url(admin_url('admin-ajax.php'), PHP_URL_PATH),
                'nonce'    => wp_create_nonce('caretochina_staff_nonce'),
            ];

            wp_localize_script('caretochina-staff-script', 'caretochina_staff_obj', $localized_data);
            wp_localize_script('caretochina-staff-script', 'careyou_staff_obj', $localized_data);
        }
    }
}

CareToChina_Medical_Staff_Plugin::instance();
