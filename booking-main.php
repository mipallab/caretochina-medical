<?php
if (!defined('ABSPATH')) {
    exit;
}

// Load Plugin Sub-modules
require_once CARETOCHINA_BOOKING_PATH . 'includes/class-db.php';
require_once CARETOCHINA_BOOKING_PATH . 'includes/class-packages.php';
require_once CARETOCHINA_BOOKING_PATH . 'includes/class-booking-wizard.php';
require_once CARETOCHINA_BOOKING_PATH . 'includes/class-patient-dashboard.php';
require_once CARETOCHINA_BOOKING_PATH . 'includes/class-auth.php';
require_once CARETOCHINA_BOOKING_PATH . 'includes/class-admin.php';

class CareToChina_Medical_Booking {
    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('init', [$this, 'register_polylang_strings']);

        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Initialize Modules
        CareToChina_Packages::instance();
        CareToChina_Booking_Wizard::instance();
        CareToChina_Patient_Dashboard::instance();
        CareToChina_Booking_Auth::instance();
        CareToChina_Booking_Admin::instance();
    }

    public function register_polylang_strings() {
        if (function_exists('pll_register_string')) {
            pll_register_string('Instant Medical Travel Booking', 'Instant Medical Travel Booking', 'CareToChina Booking Engine');
            pll_register_string('Care Case Code', 'Care Case Code', 'CareToChina Booking Engine');
            pll_register_string('Booking Confirmed', 'Booking Confirmed', 'CareToChina Booking Engine');
            pll_register_string('Service Packages', 'Service Packages', 'CareToChina Booking Engine');
        }
    }

    public function enqueue_frontend_assets() {
        wp_enqueue_style('font-awesome', CARETOCHINA_MEDICAL_URL . 'assets/vendor/font-awesome/css/all.min.css', [], '6.4.0');
        wp_enqueue_style('caretochina-booking-style', CARETOCHINA_BOOKING_URL . 'assets/css/style.css', [], CARETOCHINA_BOOKING_VERSION);

        $intl_phone_enabled = (bool) get_option('ctc_enable_intl_phone_flags', 1);
        if ($intl_phone_enabled) {
            wp_enqueue_style('intl-tel-input', CARETOCHINA_MEDICAL_URL . 'assets/vendor/intl-tel-input/css/intlTelInput.min.css', [], '19.5.6');
            wp_enqueue_script('intl-tel-input', CARETOCHINA_MEDICAL_URL . 'assets/vendor/intl-tel-input/js/intlTelInput.min.js', [], '19.5.6', true);
        }

        wp_enqueue_script('caretochina-booking-script', CARETOCHINA_BOOKING_URL . 'assets/js/script.js', ['jquery'], CARETOCHINA_BOOKING_VERSION, true);

        // Payment Scripts Enqueue
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], '3.0', true);
        
        $paypal_gateway = new CareToChina_PayPal_Gateway();
        if ($paypal_gateway->is_available()) {
            $currency = get_option('ctc_payment_currency', 'USD');
            wp_enqueue_script('paypal-js-sdk', 'https://www.paypal.com/sdk/js?client-id=' . esc_attr($paypal_gateway->get_client_id()) . '&currency=' . esc_attr($currency), [], CARETOCHINA_BOOKING_VERSION, true);
        }

        wp_enqueue_script('caretochina-payment-handler', CARETOCHINA_BOOKING_URL . 'assets/js/payment-handler.js', ['jquery', 'stripe-js'], CARETOCHINA_BOOKING_VERSION, true);

        $active_packages = class_exists('CareToChina_Packages') ? CareToChina_Packages::instance()->get_active_packages() : [];
        $store_currency  = class_exists('CareToChina_Packages') ? CareToChina_Packages::get_store_currency() : 'USD';
        $curr_symbol     = class_exists('CareToChina_Packages') ? CareToChina_Packages::get_currency_symbol($store_currency) : '$';
        $service_notes   = class_exists('CareToChina_Packages') ? CareToChina_Packages::get_global_service_notes() : '';

        $localized_data = [
            'ajax_url'           => wp_parse_url(admin_url('admin-ajax.php'), PHP_URL_PATH),
            'nonce'              => wp_create_nonce('caretochina_booking_nonce'),
            'booking_nonce'      => wp_create_nonce('caretochina_booking_nonce'),
            'patient_nonce'      => wp_create_nonce('caretochina_patient_nonce'),
            'rest_url'           => esc_url_raw(rest_url()),
            'rest_nonce'         => wp_create_nonce('wp_rest'),
            'dashboard_url'      => class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_url('patient_dashboard') : home_url('/patient-dashboard/'),
            'intl_phone_enabled' => $intl_phone_enabled,
            'hospitals'          => CareToChina_Booking_Wizard::instance()->get_hospitals_data(),
            'packages'           => $active_packages,
            'currency'           => $store_currency,
            'currency_symbol'    => $curr_symbol,
            'service_notes'      => $service_notes,
            'all_specialties'    => CareToChina_Booking_Wizard::instance()->get_all_specialties(),
            'all_cities'         => CareToChina_Booking_Wizard::instance()->get_all_cities(),
        ];

        if (is_singular('hospital')) {
            $hosp_id = get_the_ID();
            $localized_data['current_hospital'] = [
                'id'   => $hosp_id,
                'name' => get_the_title(),
            ];
        }

        wp_localize_script('caretochina-booking-script', 'caretochina_obj', $localized_data);
        wp_localize_script('caretochina-booking-script', 'careyou_obj', $localized_data);
        wp_localize_script('caretochina-payment-handler', 'caretochina_obj', $localized_data);
        wp_localize_script('caretochina-payment-handler', 'careyou_obj', $localized_data);
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'caretochina-bookings') !== false || strpos($hook, 'careyou-bookings') !== false) {
            wp_enqueue_style('font-awesome', CARETOCHINA_MEDICAL_URL . 'assets/vendor/font-awesome/css/all.min.css', [], '6.4.0');
            wp_enqueue_style('caretochina-booking-admin-style', CARETOCHINA_BOOKING_URL . 'assets/css/style.css', [], CARETOCHINA_BOOKING_VERSION);
        }
    }
}

CareToChina_Medical_Booking::instance();

// -------------------------------------------------------------------------
// DASHBOARD & AUTH SMART ELEMENTOR BUTTON SHORTCODE
// -------------------------------------------------------------------------
function caretochina_render_dashboard_button($atts = []) {
    $atts = shortcode_atts([
        'class'        => '',
        'display_mode' => 'both', // 'both', 'icon_only', 'text_only'
    ], $atts, 'caretochina_dashboard_button');

    $is_logged_in = is_user_logged_in();
    $dash_url     = class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_url('patient_dashboard') : home_url('/patient-dashboard/');
    $login_url    = home_url('/patient-login/');
    $display_mode = $atts['display_mode'];

    if ($is_logged_in) {
        $url   = $dash_url;
        $label = __('Dashboard', 'caretochina-medical');
    } else {
        $url   = $login_url;
        $label = __('Login / Register', 'caretochina-medical');
    }

    $extra_class = 'mode-' . $display_mode;

    ob_start();
    ?>
    <a href="<?php echo esc_url($url); ?>" class="ctc-dash-btn elementor-button elementor-button-secondary elementor-size-sm <?php echo esc_attr($atts['class'] . ' ' . $extra_class); ?>">
        <span class="elementor-button-content-wrapper" style="display:inline-flex; align-items:center; justify-content:center;">
            <?php if ($display_mode !== 'text_only') : ?>
                <i class="<?php echo $is_logged_in ? 'fa-solid fa-user-circle' : 'fa-solid fa-right-to-bracket'; ?> ctc-btn-icon" style="margin-right:8px; font-size:<?php echo $is_logged_in ? '16px' : '15px'; ?>;"></i>
            <?php endif; ?>
            <?php if ($display_mode !== 'icon_only') : ?>
                <span class="elementor-button-text"><?php echo esc_html($label); ?></span>
            <?php endif; ?>
        </span>
    </a>
    <style>
        .ctc-dash-btn {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 10px 22px !important;
            border-radius: 999px !important;
            font-family: 'Manrope', sans-serif !important;
            font-weight: 700 !important;
            font-size: 14px !important;
            text-decoration: none !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            box-sizing: border-box !important;
            background-color: #CCFBF1 !important;
            color: #0F766E !important;
            border: 1.5px solid #CCFBF1 !important;
            box-shadow: 0 4px 12px rgba(15, 118, 110, 0.12) !important;
        }
        .ctc-dash-btn i, .ctc-dash-btn .ctc-btn-icon {
            color: #0F766E !important;
        }
        .ctc-dash-btn:hover {
            background-color: #0F766E !important;
            border-color: #0F766E !important;
            color: #FFFFFF !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 18px rgba(15, 118, 110, 0.25) !important;
        }
        .ctc-dash-btn:hover i, .ctc-dash-btn:hover .ctc-btn-icon {
            color: #FFFFFF !important;
        }

        html.dark-theme .ctc-dash-btn, body.dark-theme .ctc-dash-btn {
            background-color: #CCFBF1 !important;
            color: #0F766E !important;
            border-color: #CCFBF1 !important;
        }
        html.dark-theme .ctc-dash-btn i, body.dark-theme .ctc-dash-btn i,
        html.dark-theme .ctc-dash-btn .ctc-btn-icon, body.dark-theme .ctc-dash-btn .ctc-btn-icon {
            color: #0F766E !important;
        }
        html.dark-theme .ctc-dash-btn:hover, body.dark-theme .ctc-dash-btn:hover {
            background-color: #0F766E !important;
            color: #FFFFFF !important;
            border-color: #0F766E !important;
        }
        html.dark-theme .ctc-dash-btn:hover i, body.dark-theme .ctc-dash-btn:hover i,
        html.dark-theme .ctc-dash-btn:hover .ctc-btn-icon, body.dark-theme .ctc-dash-btn:hover .ctc-btn-icon {
            color: #FFFFFF !important;
        }

        /* DISPLAY MODE OVERRIDES */
        .ctc-dash-btn.mode-icon_only .ctc-btn-icon {
            margin-right: 0 !important;
        }
        .ctc-dash-btn.mode-icon_only {
            padding: 10px 14px !important;
            width: 44px !important;
            height: 44px !important;
            border-radius: 50% !important;
        }

        /* RESPONSIVE AUTO ICON-ONLY BELOW 1200px */
        @media (max-width: 1200px) {
            .ctc-dash-btn .elementor-button-text {
                display: none !important;
            }
            .ctc-dash-btn .ctc-btn-icon {
                margin-right: 0 !important;
                font-size: 18px !important;
            }
            .ctc-dash-btn {
                padding: 10px 14px !important;
                width: 44px !important;
                height: 44px !important;
                border-radius: 50% !important;
            }
        }
    </style>
    <?php
    return ob_get_clean();
}

add_shortcode('caretochina_dashboard_button', 'caretochina_render_dashboard_button');
add_shortcode('careyou_dashboard_button', 'caretochina_render_dashboard_button');
