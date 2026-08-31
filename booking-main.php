<?php
if (!defined('ABSPATH')) {
    exit;
}

// Load Plugin Sub-modules
require_once CARETOCHINA_BOOKING_PATH . 'includes/class-db.php';
require_once CARETOCHINA_BOOKING_PATH . 'includes/class-packages.php';
require_once CARETOCHINA_BOOKING_PATH . 'includes/class-pricing-page.php';
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
        CareToChina_Pricing_Page::instance();
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
        if (!wp_style_is('caretochina-font-awesome', 'registered')) {
            wp_register_style('caretochina-font-awesome', CARETOCHINA_MEDICAL_URL . 'assets/vendor/font-awesome/css/all.min.css', [], '6.5.1');
        }
        if (!wp_style_is('font-awesome', 'registered')) {
            wp_register_style('font-awesome', CARETOCHINA_MEDICAL_URL . 'assets/vendor/font-awesome/css/all.min.css', [], '6.5.1');
        }
        wp_enqueue_style('caretochina-font-awesome');
        wp_enqueue_style('font-awesome');
        wp_enqueue_style('caretochina-booking-style', CARETOCHINA_BOOKING_URL . 'assets/css/style.css', ['caretochina-font-awesome', 'font-awesome'], CARETOCHINA_BOOKING_VERSION);

        $intl_phone_enabled = (bool) get_option('ctc_enable_intl_phone_flags', 1);
        if ($intl_phone_enabled) {
            wp_register_style('intl-tel-input', CARETOCHINA_MEDICAL_URL . 'assets/vendor/intl-tel-input/css/intlTelInput.min.css', [], '19.5.6');
            wp_register_script('intl-tel-input', CARETOCHINA_MEDICAL_URL . 'assets/vendor/intl-tel-input/js/intlTelInput.min.js', [], '19.5.6', true);
            wp_enqueue_style('intl-tel-input');
            wp_enqueue_script('intl-tel-input');
        }

        // Main booking and interactivity script
        wp_enqueue_script('caretochina-booking-script', CARETOCHINA_BOOKING_URL . 'assets/js/script.js', ['jquery'], CARETOCHINA_BOOKING_VERSION, true);

        // Payment handler script (registered with on-demand Stripe/PayPal SDK dynamic loading)
        wp_register_script('stripe-js', 'https://js.stripe.com/v3/', [], '3.0', true);
        wp_register_script('caretochina-payment-handler', CARETOCHINA_BOOKING_URL . 'assets/js/payment-handler.js', ['jquery'], CARETOCHINA_BOOKING_VERSION, true);

        // Conditionally enqueue payment handler on dashboard, hospital single, or pricing pages where payments occur
        $dash_page_id    = class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_id('patient_dashboard') : 0;
        $pricing_page_id = class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_id('pricing') : 0;
        $auth_page_id    = class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_id('patient_login') : 0;
        
        $should_load_payments = is_singular('hospital') 
            || ($dash_page_id > 0 && is_page($dash_page_id)) 
            || ($pricing_page_id > 0 && is_page($pricing_page_id))
            || ($auth_page_id > 0 && is_page($auth_page_id))
            || is_user_logged_in();

        if ($should_load_payments) {
            wp_enqueue_script('caretochina-payment-handler');
        }

        $active_packages = class_exists('CareToChina_Packages') ? CareToChina_Packages::instance()->get_active_packages() : [];
        $store_currency  = class_exists('CareToChina_Packages') ? CareToChina_Packages::get_store_currency() : 'USD';
        $curr_symbol     = class_exists('CareToChina_Packages') ? CareToChina_Packages::get_currency_symbol($store_currency) : '$';
        $service_notes   = class_exists('CareToChina_Packages') ? CareToChina_Packages::get_global_service_notes() : '';

        $paypal_gateway = class_exists('CareToChina_PayPal_Gateway') ? new CareToChina_PayPal_Gateway() : null;
        $stripe_gateway = class_exists('CareToChina_Stripe_Gateway') ? new CareToChina_Stripe_Gateway() : null;

        $localized_data = [
            'ajax_url'              => wp_parse_url(admin_url('admin-ajax.php'), PHP_URL_PATH),
            'nonce'                 => wp_create_nonce('caretochina_booking_nonce'),
            'booking_nonce'         => wp_create_nonce('caretochina_booking_nonce'),
            'patient_nonce'         => wp_create_nonce('caretochina_patient_nonce'),
            'rest_url'              => esc_url_raw(rest_url()),
            'rest_nonce'            => wp_create_nonce('wp_rest'),
            'dashboard_url'         => class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_url('patient_dashboard') : home_url('/patient-dashboard/'),
            'intl_phone_enabled'    => $intl_phone_enabled,
            'hospitals'             => CareToChina_Booking_Wizard::instance()->get_hospitals_data(),
            'packages'              => $active_packages,
            'currency'              => $store_currency,
            'currency_symbol'       => $curr_symbol,
            'service_notes'         => $service_notes,
            'all_specialties'       => CareToChina_Booking_Wizard::instance()->get_all_specialties(),
            'all_cities'            => CareToChina_Booking_Wizard::instance()->get_all_cities(),
            'pricing_url'           => home_url('/pricing/'),
            'recaptcha_enabled'     => class_exists('CareToChina_Recaptcha') && CareToChina_Recaptcha::is_master_enabled() && CareToChina_Recaptcha::is_configured(),
            'recaptcha_version'     => class_exists('CareToChina_Recaptcha') ? CareToChina_Recaptcha::get_version() : 'v2',
            'recaptcha_site_key'    => class_exists('CareToChina_Recaptcha') ? CareToChina_Recaptcha::get_site_key() : '',
            'stripe_publishable_key'=> ($stripe_gateway && $stripe_gateway->is_available()) ? $stripe_gateway->get_publishable_key() : '',
            'paypal_client_id'      => ($paypal_gateway && $paypal_gateway->is_available()) ? $paypal_gateway->get_client_id() : '',
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
    <a href="<?php echo esc_url($url); ?>" class="ctc-dash-btn elementor-button elementor-button-secondary elementor-size-sm <?php echo esc_attr($atts['class'] . ' ' . $extra_class); ?>" aria-label="<?php echo esc_attr($label); ?>">
        <span class="elementor-button-content-wrapper" style="display:inline-flex; align-items:center; justify-content:center; gap:8px;">
            <?php if ($display_mode !== 'text_only') : ?>
                <span class="ctc-btn-icon-wrap" style="display:inline-flex; align-items:center; justify-content:center; line-height:1; flex-shrink:0;">
                    <?php if ($is_logged_in) : ?>
                        <svg class="ctc-btn-svg" width="16" height="16" viewBox="0 0 512 512" fill="currentColor" style="display:inline-block; vertical-align:middle;"><path d="M256 0C114.6 0 0 114.6 0 256s114.6 256 256 256s256-114.6 256-256S397.4 0 256 0zM256 128c39.7 0 72 32.3 72 72s-32.3 72-72 72s-72-32.3-72-72s32.3-72 72-72zm0 320c-55.7 0-105.7-24.8-139.7-64.2c1.7-41.4 35.1-74.8 76.8-74.8c12.2 0 23.9 3.5 33.9 9.7c8.9 5.5 19.3 8.3 29 8.3s20.1-2.8 29-8.3c10-6.2 21.7-9.7 33.9-9.7c41.7 0 75.1 33.4 76.8 74.8C361.7 423.2 311.7 448 256 448z"/></svg>
                    <?php else : ?>
                        <svg class="ctc-btn-svg" width="15" height="15" viewBox="0 0 512 512" fill="currentColor" style="display:inline-block; vertical-align:middle;"><path d="M217.9 105.9L340.7 228.7c7.2 7.2 11.3 17.1 11.3 27.3s-4.1 20.1-11.3 27.3L217.9 406.1c-6.4 6.4-15 9.9-24 9.9c-18.7 0-33.9-15.2-33.9-33.9l0-62.1L32 320c-17.7 0-32-14.3-32-32l0-64c0-17.7 14.3-32 32-32l128 0 0-62.1c0-18.7 15.2-33.9 33.9-33.9c9 0 17.6 3.6 24 9.9zM352 416l64 0c17.7 0 32-14.3 32-32l0-256c0-17.7-14.3-32-32-32l-64 0c-17.7 0-32-14.3-32-32s14.3-32 32-32l64 0c53 0 96 43 96 96l0 256c0 53-43 96-96 96l-64 0c-17.7 0-32-14.3-32-32s14.3-32 32-32z"/></svg>
                    <?php endif; ?>
                </span>
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
        .ctc-dash-btn .ctc-btn-icon-wrap,
        .ctc-dash-btn .ctc-btn-svg,
        .ctc-dash-btn svg {
            display: inline-flex !important;
            visibility: visible !important;
            opacity: 1 !important;
            width: 15px !important;
            height: 15px !important;
            fill: #0F766E !important;
            color: #0F766E !important;
            flex-shrink: 0 !important;
            vertical-align: middle !important;
        }
        .ctc-dash-btn:hover {
            background-color: #0F766E !important;
            border-color: #0F766E !important;
            color: #FFFFFF !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 18px rgba(15, 118, 110, 0.25) !important;
        }
        .ctc-dash-btn:hover .ctc-btn-svg,
        .ctc-dash-btn:hover svg {
            fill: #FFFFFF !important;
            color: #FFFFFF !important;
        }

        html.dark-theme .ctc-dash-btn, body.dark-theme .ctc-dash-btn {
            background-color: #CCFBF1 !important;
            color: #0F766E !important;
            border-color: #CCFBF1 !important;
        }
        html.dark-theme .ctc-dash-btn .ctc-btn-svg,
        body.dark-theme .ctc-dash-btn .ctc-btn-svg {
            fill: #0F766E !important;
            color: #0F766E !important;
        }
        html.dark-theme .ctc-dash-btn:hover, body.dark-theme .ctc-dash-btn:hover {
            background-color: #0F766E !important;
            color: #FFFFFF !important;
            border-color: #0F766E !important;
        }
        html.dark-theme .ctc-dash-btn:hover .ctc-btn-svg,
        body.dark-theme .ctc-dash-btn:hover .ctc-btn-svg {
            fill: #FFFFFF !important;
            color: #FFFFFF !important;
        }

        /* DISPLAY MODE OVERRIDES */
        .ctc-dash-btn.mode-icon_only .ctc-btn-icon-wrap {
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
            .ctc-dash-btn .ctc-btn-icon-wrap {
                margin-right: 0 !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                line-height: 1 !important;
            }
            .ctc-dash-btn {
                padding: 0 !important;
                width: 42px !important;
                height: 42px !important;
                min-width: 42px !important;
                border-radius: 50% !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
            }
            .ctc-dash-btn svg,
            .ctc-dash-btn .ctc-btn-svg {
                width: 16px !important;
                height: 16px !important;
                display: block !important;
                margin: 0 auto !important;
                fill: #0F766E !important;
                color: #0F766E !important;
            }
            .ctc-dash-btn:hover svg,
            .ctc-dash-btn:hover .ctc-btn-svg {
                fill: #FFFFFF !important;
                color: #FFFFFF !important;
            }
        }
    </style>
    <?php
    return ob_get_clean();
}

add_shortcode('caretochina_dashboard_button', 'caretochina_render_dashboard_button');
add_shortcode('careyou_dashboard_button', 'caretochina_render_dashboard_button');
