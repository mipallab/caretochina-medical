<?php
/**
 * Plugin Name: CareToChina Medical Suite
 * Plugin URI: https://caretochina.com
 * Description: Unified Medical Management suite for CareToChina, combining Hospitals Management, Booking Engine, and Coordinator Portal.
 * Version: 1.4.6
 * Author: SM Mart
 * Text Domain: caretochina-medical
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Unified Constants
define('CARETOCHINA_MEDICAL_VERSION', '1.4.6');
define('CARETOCHINA_MEDICAL_PATH', plugin_dir_path(__FILE__));
define('CARETOCHINA_MEDICAL_URL', plugin_dir_url(__FILE__));

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
require_once CARETOCHINA_MEDICAL_PATH . 'booking-main.php';
require_once CARETOCHINA_MEDICAL_PATH . 'staff-main.php';

// Activation hook for DB tables
register_activation_hook(__FILE__, ['CareToChina_Booking_DB', 'create_tables']);

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
