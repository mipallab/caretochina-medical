=== CareToChina Medical Suite ===
Contributors: caretochinagroup
Donate link: https://caretochina.com
Tags: medical, hospital, booking, healthcare, appointments
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.4.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Unified Medical Management suite for CareToChina, combining Hospitals Management, Booking Engine, Coordinator Portal, and Headless Payments.

== Description ==

CareToChina Medical Suite is an enterprise-grade medical tourism and hospital appointment booking system tailored for international medical travel.

= Key Features =
* Hospital Directory & Advanced Filtering (by City, Department, Ranking, and Treatment)
* Medical Treatments Management with Dedicated Categories & Price / Stay Metadata
* Multi-step Appointment & Treatment Booking Engine
* Patient Dashboard with Medical History and Payment Records
* Dedicated Medical Staff / Coordinator Desk Portal
* Automated Multi-channel Email Notifications
* Headless Payment Support for Stripe & PayPal
* Multi-language Ready with Full i18n Compatibility
* Complete WP Rocket Caching & Performance Compatibility

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

= 2.4.4 =
* UX Enhancement: Made the entire Medical Treatment grid card box clickable with direct navigation to the single treatment post.
* Accessibility: Preserved layered z-index hierarchy for keyboard accessibility and direct link tab indexing.

= 2.4.3 =
* Responsiveness: Optimized Single Medical Treatment page for 100% mobile friendliness across Mobile Portrait, Mobile Landscape, Tablet, and Desktop viewports.
* Responsiveness: Added responsive typography, 1-column highlight stacking on mobile portrait, 2-column landscape grid, and zero horizontal scroll protection.

= 2.4.2 =
* Enhancement: Made grid metadata (Price, Day Stay Duration, and Discount Badges) 100% dynamically customizable with support for custom strings and formatted numbers.
* Enhancement: Applied explicit object-fit cover styling to ensure treatment featured images maintain perfect proportion without distortion.
* Design: Decreased card border radius from 24px to a sleek 12px.

= 2.4.1 =
* Design: Redesigned Medical Treatments Grid Card to 100% pixel-perfect match reference specifications in Light and Dark modes.
* Design: Added customizable top-right orange discount pill badge ("Save 65%"), horizontal separator divider, and bottom meta row with orange price tag ("From $7,500") and teal stay clock ("5-7 Days Stay").
* Enhancement: Updated Elementor Treatments Grid widget to default to 4 responsive columns with 20px gap.

= 2.4.0 =
* New Feature: Added 'Medical Treatments' Custom Post Type (medical_treatment) with Treatment Category and Specialty taxonomies.
* New Feature: Custom metadata for Treatment Price (optional) and Day Stay duration (optional) with store currency formatting.
* New Feature: Strict Publishing Validation (Title, Description/Content editor, and Featured Image required to publish).
* New Feature: Elementor Drag & Drop Treatments Grid & Search Widget (caretochina_treatments_grid) with live AJAX filtering.
* New Feature: Dedicated Medical Treatments single and archive directory templates matching hospital visual aesthetics.
* Performance & Compatibility: Integrated comprehensive WP Rocket Compatibility Engine with automatic dynamic portal cache exclusion, Delay JS safelisting, RUCSS safelisting, LazyLoad protection, and automatic cache invalidation.

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
