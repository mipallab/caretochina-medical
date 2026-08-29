=== CareToChina Medical Suite ===
Contributors: caretochinagroup
Donate link: https://caretochina.com
Tags: medical, hospital, booking, healthcare, appointments
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.3.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Unified Medical Management suite for CareToChina, combining Hospitals Management, Booking Engine, Coordinator Portal, and Headless Payments.

== Description ==

CareToChina Medical Suite is an enterprise-grade medical tourism and hospital appointment booking system tailored for international medical travel.

= Key Features =
* Hospital Directory & Advanced Filtering (by City, Department, Ranking, and Treatment)
* Multi-step Appointment & Treatment Booking Engine
* Patient Dashboard with Medical History and Payment Records
* Dedicated Medical Staff / Coordinator Desk Portal
* Automated Multi-channel Email Notifications
* Headless Payment Support for Stripe & PayPal
* Multi-language Ready with Full i18n Compatibility

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory, or install directly through WordPress Plugins screen.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Complete the Setup Wizard at 'CareToChina -> Setup Wizard' to configure settings, API credentials, and email templates.

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =
No, basic hospital management and booking wizard work standalone. WooCommerce can optionally be connected for advanced headless payment gateway processing.

= How do staff members access the coordinator desk? =
Staff members assigned the 'medical_staff' role can access the portal directly from the frontend portal page or WordPress admin.

== Changelog ==

= 2.3.4 =
* Performance: Optimized asset enqueueing; eliminated unconditional staff scripts and Swiper JS on unrelated frontend pages.
* Performance: Converted Stripe and PayPal SDKs to on-demand dynamic loading.
* Compatibility: Added full compatibility with WP Rocket (Delay JavaScript Execution, Defer JS, Minify/Combine JS).
* Fix: Enhanced Google reCAPTCHA v3/v2 token generation on the fly before form submissions.
* Fix: Added retry polling and user interaction triggers for Swiper carousels and sliders.
* Fix: Replaced inline jQuery event handlers with deferral-safe JavaScript logic.

= 2.3.3 =
* Fix: Custom treatment plan amount parsing in staff payment request handler.
* Enhancement: Patient timeline and chat responsiveness improvements.

= 1.9.0 =
* Unified code base under CareToChina Medical Suite.
* Full WordPress Coding Standards (WPCS) and Plugin Check compliance.
* Added localized assets and security hardening.

= 1.0.0 =
* Initial release.
