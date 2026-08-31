<?php
/**
 * Plugin Name: CareToChina Medical Suite
 * Plugin URI: https://caretochina.com
 * Description: Unified Medical Management suite for CareToChina, combining Hospitals Management, Booking Engine, Coordinator Portal, and Headless WooCommerce Payments.
 * Version: 2.5.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Tested up to: 7.1
 * Author: CareToChina Team
 * Author URI: https://caretochina.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: caretochina-medical
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Runtime Environment Compatibility Check
if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>CareToChina Medical Suite:</strong> ' . esc_html(sprintf('This plugin requires PHP version %1$s or higher. Your server is running PHP %2$s.', '7.4', PHP_VERSION)) . '</p></div>';
    });
    return;
}

global $wp_version;
if (isset($wp_version) && version_compare($wp_version, '6.0', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>CareToChina Medical Suite:</strong> This plugin requires WordPress version 6.0 or higher.</p></div>';
    });
    return;
}

// Unified Constants
define('CARETOCHINA_MEDICAL_VERSION', '2.5.0');
define('CARETOCHINA_MEDICAL_PATH', plugin_dir_path(__FILE__));
define('CARETOCHINA_MEDICAL_URL', plugin_dir_url(__FILE__));

// Load translations strictly on 'init' action for WordPress 6.7+ standard
add_action('init', function() {
    load_plugin_textdomain('caretochina-medical', false, dirname(plugin_basename(__FILE__)) . '/languages');
}, 1);

// Backwards compatibility constants for Booking
if (!defined('CARETOCHINA_BOOKING_VERSION')) define('CARETOCHINA_BOOKING_VERSION', CARETOCHINA_MEDICAL_VERSION);
if (!defined('CARETOCHINA_BOOKING_PATH')) define('CARETOCHINA_BOOKING_PATH', CARETOCHINA_MEDICAL_PATH);
if (!defined('CARETOCHINA_BOOKING_URL')) define('CARETOCHINA_BOOKING_URL', CARETOCHINA_MEDICAL_URL);
if (!defined('CAREYOU_BOOKING_VERSION')) define('CAREYOU_BOOKING_VERSION', CARETOCHINA_MEDICAL_VERSION);
if (!defined('CAREYOU_BOOKING_PATH')) define('CAREYOU_BOOKING_PATH', CARETOCHINA_MEDICAL_PATH);
if (!defined('CAREYOU_BOOKING_URL')) define('CAREYOU_BOOKING_URL', CARETOCHINA_MEDICAL_URL);

// Backwards compatibility constants for Staff
if (!defined('CARETOCHINA_STAFF_VERSION')) define('CARETOCHINA_STAFF_VERSION', CARETOCHINA_MEDICAL_VERSION);
if (!defined('CARETOCHINA_STAFF_PATH')) define('CARETOCHINA_STAFF_PATH', CARETOCHINA_MEDICAL_PATH);
if (!defined('CARETOCHINA_STAFF_URL')) define('CARETOCHINA_STAFF_URL', CARETOCHINA_MEDICAL_URL);
if (!defined('CAREYOU_STAFF_VERSION')) define('CAREYOU_STAFF_VERSION', CARETOCHINA_MEDICAL_VERSION);
if (!defined('CAREYOU_STAFF_PATH')) define('CAREYOU_STAFF_PATH', CARETOCHINA_MEDICAL_PATH);
if (!defined('CAREYOU_STAFF_URL')) define('CAREYOU_STAFF_URL', CARETOCHINA_MEDICAL_URL);

// Load the modules
require_once CARETOCHINA_MEDICAL_PATH . 'hospitals-main.php';
require_once CARETOCHINA_MEDICAL_PATH . 'treatments-main.php';
require_once CARETOCHINA_MEDICAL_PATH . 'booking-main.php';
require_once CARETOCHINA_MEDICAL_PATH . 'staff-main.php';
require_once CARETOCHINA_MEDICAL_PATH . 'includes/class-async-mailer.php';
require_once CARETOCHINA_MEDICAL_PATH . 'includes/class-country-helper.php';
require_once CARETOCHINA_MEDICAL_PATH . 'includes/emails/class-email-templates.php';
require_once CARETOCHINA_MEDICAL_PATH . 'includes/class-page-manager.php';
require_once CARETOCHINA_MEDICAL_PATH . 'includes/class-recaptcha.php';
require_once CARETOCHINA_MEDICAL_PATH . 'includes/admin/class-data-exporter.php';
require_once CARETOCHINA_MEDICAL_PATH . 'includes/admin/class-setup-wizard.php';
require_once CARETOCHINA_MEDICAL_PATH . 'includes/admin/class-hospital-settings.php';
require_once CARETOCHINA_MEDICAL_PATH . 'includes/class-hero-hospital-slider.php';
require_once CARETOCHINA_MEDICAL_PATH . 'includes/class-litespeed-compat.php';
require_once CARETOCHINA_MEDICAL_PATH . 'includes/payments/loader.php';

// Instantiate Core Services
CareToChina_Async_Mailer::init();
CareToChina_Email_Templates::instance();
CareToChina_Page_Manager::instance();
CareToChina_Recaptcha::instance();
CareToChina_Setup_Wizard::instance();
CareToChina_Hospital_Settings::instance();
CareToChina_Hero_Hospital_Slider::instance();
CareToChina_Treatments_Plugin::instance();
CareToChina_LiteSpeed_Compat::instance();

// Automatic DB Schema & Index Synchronization
add_action('plugins_loaded', function() {
    if (get_option('caretochina_db_index_version') !== CARETOCHINA_MEDICAL_VERSION) {
        if (class_exists('CareToChina_Booking_DB')) {
            CareToChina_Booking_DB::create_tables();
            update_option('caretochina_db_index_version', CARETOCHINA_MEDICAL_VERSION);
        }
    }
}, 5);

// Activation hook for DB tables, Capabilities & Setup Wizard Redirect
register_activation_hook(__FILE__, function() {
    CareToChina_Booking_DB::create_tables();

    // Set 60-second transient for first-run setup wizard redirect
    set_transient('ctc_activation_redirect', true, 60);

    // Grant caretochina_manage_bookings capability strictly to admin and medical_staff
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $admin_role->add_cap('caretochina_manage_bookings');
    }
    $staff_role = get_role('medical_staff');
    if ($staff_role) {
        $staff_role->add_cap('caretochina_manage_bookings');
    }
});

// Single-load First-Run Admin Redirect to Setup Wizard
add_action('admin_init', function() {
    if (!get_transient('ctc_activation_redirect')) {
        return;
    }

    delete_transient('ctc_activation_redirect');

    // Do not redirect on bulk plugin activation or background contexts
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (isset($_GET['activate-multi'])) {
        return;
    }

    if (wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST) || defined('WP_CLI')) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    wp_safe_redirect(admin_url('admin.php?page=caretochina-setup-wizard'));
    exit;
});

// Prevent accidental deactivation with a warning popup
add_action('admin_footer-plugins.php', function() {
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Individual deactivate link
        var targetLink = $('tr[data-plugin="caretochina-medical/caretochina-medical.php"] .deactivate a, tr[data-slug="caretochina-medical"] .deactivate a');
        targetLink.on('click', function(e) {
            var confirmed = confirm("WARNING: Deactivating CareToChina Medical Suite will break your website's hospital directories, treatment booking engine, and staff desk portal. Are you sure you want to deactivate it?");
            if (!confirmed) {
                e.preventDefault();
                return false;
            }
        });

        // Bulk action deactivation
        $('#bulk-action-form').on('submit', function(e) {
            var action = $('#bulk-action-selector-top').val() || $('#bulk-action-selector-bottom').val();
            if (action === 'deactivate-selected') {
                var isSelected = $('input[value="caretochina-medical/caretochina-medical.php"]:checked').length > 0;
                if (isSelected) {
                    var confirmed = confirm("WARNING: Deactivating CareToChina Medical Suite will break your website's hospital directories, treatment booking engine, and staff desk portal. Are you sure you want to deactivate it?");
                    if (!confirmed) {
                        e.preventDefault();
                        return false;
                    }
                }
            }
        });
    });
    </script>
    <?php
});

// System-wide Dark/Light Mode Engine
add_action('wp_head', function() {
    ?>
    <script type="text/javascript">
    (function() {
        var theme = localStorage.getItem('caretochina_theme');
        if (!theme) {
            theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        if (theme === 'dark') {
            document.documentElement.classList.add('dark-theme');
            document.addEventListener('DOMContentLoaded', function() {
                document.body.classList.add('dark-theme');
            });
        }
        
        window.appToggleTheme = function() {
            var current = document.documentElement.classList.contains('dark-theme') ? 'dark' : 'light';
            var next = current === 'dark' ? 'light' : 'dark';
            
            if (next === 'dark') {
                document.documentElement.classList.add('dark-theme');
                document.body.classList.add('dark-theme');
            } else {
                document.documentElement.classList.remove('dark-theme');
                document.body.classList.remove('dark-theme');
            }
            localStorage.setItem('caretochina_theme', next);
        };
        
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
            if (!localStorage.getItem('caretochina_theme')) {
                var next = e.matches ? 'dark' : 'light';
                if (next === 'dark') {
                    document.documentElement.classList.add('dark-theme');
                    document.body.classList.add('dark-theme');
                } else {
                    document.documentElement.classList.remove('dark-theme');
                    document.body.classList.remove('dark-theme');
                }
            }
        });
    })();
    </script>
    <?php
}, 1);

// Mobile-First & Senior Accessibility Viewport Meta Tag Hook
add_action('wp_head', function() {
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">' . "\n";
    ?>
    <style id="ctc-header-btn-critical-css">
        .ctc-dash-el-btn,
        .ctc-dash-btn {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
        .ctc-dash-el-btn .elementor-button-content-wrapper,
        .ctc-dash-btn .elementor-button-content-wrapper {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
        .ctc-dash-el-btn .ctc-btn-icon,
        .ctc-dash-btn .ctc-btn-icon,
        .ctc-dash-el-btn .ctc-btn-icon-wrap,
        .ctc-dash-btn .ctc-btn-icon-wrap {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            visibility: visible !important;
            opacity: 1 !important;
            font-size: 16px !important;
            line-height: 1 !important;
            color: #0F766E !important;
        }
        .ctc-dash-el-btn svg,
        .ctc-dash-btn svg,
        .ctc-dash-el-btn .ctc-btn-svg,
        .ctc-dash-btn .ctc-btn-svg {
            display: inline-block !important;
            visibility: visible !important;
            opacity: 1 !important;
            width: 16px !important;
            height: 16px !important;
            min-width: 16px !important;
            min-height: 16px !important;
            fill: #0F766E !important;
            color: #0F766E !important;
            vertical-align: middle !important;
        }
        .ctc-dash-el-btn:hover svg,
        .ctc-dash-btn:hover svg {
            fill: #FFFFFF !important;
            color: #FFFFFF !important;
        }
        @media (max-width: 1200px) {
            .ctc-dash-el-btn,
            .ctc-dash-btn {
                padding: 0 !important;
                width: 42px !important;
                height: 42px !important;
                min-width: 42px !important;
                min-height: 42px !important;
                border-radius: 50% !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
            }
            .ctc-dash-el-btn .elementor-button-text,
            .ctc-dash-btn .elementor-button-text {
                display: none !important;
            }
            .ctc-dash-el-btn .ctc-btn-icon,
            .ctc-dash-btn .ctc-btn-icon,
            .ctc-dash-el-btn .ctc-btn-icon-wrap,
            .ctc-dash-btn .ctc-btn-icon-wrap {
                margin: 0 !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                width: 100% !important;
                height: 100% !important;
            }
            .ctc-dash-el-btn svg,
            .ctc-dash-btn svg {
                display: block !important;
                margin: 0 auto !important;
                width: 17px !important;
                height: 17px !important;
            }
        }
    </style>
    <script type="text/javascript">
    (function() {
        function injectHeaderIcons() {
            var isUserLoggedIn = document.body && (document.body.classList.contains('logged-in') || document.body.getAttribute('data-logged-in') === '1');
            var svgSignIn = '<svg width="15" height="15" viewBox="0 0 512 512" fill="currentColor" style="display:inline-block; vertical-align:middle; width:15px; height:15px;"><path d="M217.9 105.9L340.7 228.7c7.2 7.2 11.3 17.1 11.3 27.3s-4.1 20.1-11.3 27.3L217.9 406.1c-6.4 6.4-15 9.9-24 9.9c-18.7 0-33.9-15.2-33.9-33.9l0-62.1L32 320c-17.7 0-32-14.3-32-32l0-64c0-17.7 14.3-32 32-32l128 0 0-62.1c0-18.7 15.2-33.9 33.9-33.9c9 0 17.6 3.6 24 9.9zM352 416l64 0c17.7 0 32-14.3 32-32l0-256c0-17.7-14.3-32-32-32l-64 0c-17.7 0-32-14.3-32-32s14.3-32 32-32l64 0c53 0 96 43 96 96l0 256c0 53-43 96-96 96l-64 0c-17.7 0-32-14.3-32-32s14.3-32 32-32z"/></svg>';
            var svgUserCircle = '<svg width="16" height="16" viewBox="0 0 512 512" fill="currentColor" style="display:inline-block; vertical-align:middle; width:16px; height:16px;"><path d="M256 0C114.6 0 0 114.6 0 256s114.6 256 256 256s256-114.6 256-256S397.4 0 256 0zM256 128c39.7 0 72 32.3 72 72s-32.3 72-72 72s-72-32.3-72-72s32.3-72 72-72zm0 320c-55.7 0-105.7-24.8-139.7-64.2c1.7-41.4 35.1-74.8 76.8-74.8c12.2 0 23.9 3.5 33.9 9.7c8.9 5.5 19.3 8.3 29 8.3s20.1-2.8 29-8.3c10-6.2 21.7-9.7 33.9-9.7c41.7 0 75.1 33.4 76.8 74.8C361.7 423.2 311.7 448 256 448z"/></svg>';
            var chosenSvg = isUserLoggedIn ? svgUserCircle : svgSignIn;
            
            var iconHolders = document.querySelectorAll('.ctc-dash-el-btn .ctc-btn-icon, .ctc-dash-btn .ctc-btn-icon, .ctc-dash-el-btn i.fa-right-to-bracket, .ctc-dash-btn i.fa-right-to-bracket, .ctc-dash-el-btn i.fa-user-circle, .ctc-dash-btn i.fa-user-circle');
            for (var i = 0; i < iconHolders.length; i++) {
                if (!iconHolders[i].querySelector('svg')) {
                    iconHolders[i].innerHTML = chosenSvg;
                    iconHolders[i].style.display = 'inline-flex';
                    iconHolders[i].style.alignItems = 'center';
                    iconHolders[i].style.justifyContent = 'center';
                }
            }
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', injectHeaderIcons);
        } else {
            injectHeaderIcons();
        }
        setTimeout(injectHeaderIcons, 300);
        setTimeout(injectHeaderIcons, 1000);
    })();
    </script>
    <?php
}, 0);

