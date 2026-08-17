# Headless WooCommerce Payment Engine Architecture — `caretochina-medical`

This document details the architecture, security model, and developer extension guide for the headless WooCommerce payment module built into `caretochina-medical`.

---

## 1. Architectural Overview

`caretochina-medical` uses WooCommerce **headlessly** as a backend payment and order engine:
- **No Native Storefront/Checkout**: All standard WooCommerce pages (`/cart`, `/checkout`, `/shop`, `/product/*`) are unhooked and redirected to `/patient-dashboard/` for non-admin users via `CareToChina_WooCommerce_Headless`.
- **Custom UI Layer**: Patients interact exclusively via the custom Patient Dashboard (`CareToChina_Patient_Dashboard`), while staff manage payments, refunds, and cancellations inside the Staff Desk (`CareToChina_Staff_Portal`).
- **Source of Truth**: `WC_Order` is the single authoritative source of financial truth (totals, currency, fees, refunds, and gateway transaction IDs). `caretochina_bookings` stores a read-only denormalized cache (`amount`, `currency`, `payment_gateway`, `status`) written immediately from `WC_Order` after state transitions.

---

## 2. Treatment ↔ WooCommerce Product Mapping

Medical treatments map 1-to-1 to simple, virtual, non-taxable WooCommerce products:
1. `CareToChina_Treatment_Product_Sync` creates/updates virtual `WC_Product` items when treatments are added or updated in WordPress, storing `_caretochina_treatment_id` in product meta.
2. When creating a `WC_Order`, `CareToChina_Payment_Manager` adds the backing `WC_Product` line item with `$order->add_product($product, 1, ['subtotal' => $snapshotted_price, 'total' => $snapshotted_price])`. This overrides the live product price with the booking's **snapshotted price** locked at booking creation time.
3. Deleting or deactivating a treatment soft-deactivates (unlists/drafts) its backing `WC_Product` without deleting the post, ensuring historical orders, invoices, line items, and refund calculations remain 100% intact.

---

## 3. Environment Constants Reference

Secret API keys can be declared in `wp-config.php` (recommended for production environments) or configured via the WP Admin Settings UI:

```php
// Environment Mode Switch ('test' or 'live')
define( 'CARETOCHINA_PAYMENT_MODE', 'test' );

// Stripe Credentials (Test & Live)
define( 'CARETOCHINA_STRIPE_TEST_PUBLISHABLE_KEY', 'pk_test_...' );
define( 'CARETOCHINA_STRIPE_TEST_SECRET_KEY',      'sk_test_...' );
define( 'CARETOCHINA_STRIPE_TEST_WEBHOOK_SECRET',  'whsec_test_...' );

define( 'CARETOCHINA_STRIPE_PUBLISHABLE_KEY',      'pk_live_...' );
define( 'CARETOCHINA_STRIPE_SECRET_KEY',          'sk_live_...' );
define( 'CARETOCHINA_STRIPE_WEBHOOK_SECRET',      'whsec_...' );

// PayPal Credentials (Test & Live)
define( 'CARETOCHINA_PAYPAL_TEST_CLIENT_ID',       '...' );
define( 'CARETOCHINA_PAYPAL_TEST_CLIENT_SECRET',   '...' );

define( 'CARETOCHINA_PAYPAL_CLIENT_ID',            '...' );
define( 'CARETOCHINA_PAYPAL_CLIENT_SECRET',        '...' );
```

If keys are entered via the WP Admin Settings UI (`CareToChina Payment Settings`), secret keys are **encrypted at rest** in `wp_options` using `CareToChina_Payment_Security` (Sodium/AES-256-GCM authenticated encryption derived from WordPress `AUTH_KEY` and `SECURE_AUTH_KEY` salts).

---

## 4. Webhook Security & Idempotency

### Event-ID Idempotency
Concurrent or duplicate webhook deliveries are handled atomically via `wp_caretochina_processed_webhook_events` (`UNIQUE KEY (event_id)`). Webhook handlers attempt an atomic `INSERT IGNORE` before executing status transitions. Duplicate events return an instant `200 OK`.

### REST Route Authentication
Webhook routes (`/wp-json/caretochina/v1/webhooks/stripe` and `/webhooks/paypal`):
- Restrict HTTP methods to `POST` only.
- Validate `Content-Type: application/json`.
- Set `permission_callback => '__return_true'`, bypassing WP session/cookie/nonce checks.
- Authenticate strictly via Stripe HMAC signature verification (`Stripe\Webhook::constructEvent()`) and PayPal signature API (`/v1/notifications/verify-webhook-signature`).
- Retain Stripe default timestamp tolerance (300 seconds) to prevent replay attacks using old captured payloads.

### Optional Web-Server / WAF IP Allowlisting
As an additional defense-in-depth layer, site administrators may configure web-server (Nginx/Apache) or Cloudflare WAF rules to restrict `/wp-json/caretochina/v1/webhooks/*` to official gateway IP ranges:
- **Stripe Webhook IP Ranges**: `3.18.12.63`, `3.130.192.231`, `13.235.14.237`, `13.235.122.149`, `18.211.135.69`, `35.154.171.200`, `52.15.183.38`, `54.187.174.169`, `54.187.205.235`, `54.187.215.71`.
- **PayPal Webhook IP Ranges**: Refer to PayPal's published developer documentation.

---

## 5. Staff Refund & Cancellation Security

1. **Atomic Refund Lock**: `acquire_refund_lock($booking_id)` prevents race conditions or duplicate refunds from double-clicking "Refund" or retried form POSTs.
2. **Remaining Refundable Balance Validation**: Server-side checks verify `$refund_amount <= $order->get_remaining_refund_amount()`.
3. **Separate Paid vs. Unpaid Cancellation Workflows**:
   - Unpaid bookings cancel directly without calling gateway APIs.
   - Paid bookings route through full gateway refund logic or require explicit staff confirmation logged to audit records.
4. **Role Scoping**: Capability `caretochina_manage_bookings` is restricted strictly to `administrator` and `medical_staff` roles (excluding `editor`).

---

## 6. How to Add a New Payment Gateway

To add a new payment gateway (e.g., Alipay, WeChat Pay, UnionPay, local bank transfer):

1. Create a new class in `includes/payments/` implementing `CareToChina_Payment_Gateway_Interface`:

```php
class CareToChina_Alipay_Gateway implements CareToChina_Payment_Gateway_Interface {
    public function get_id(): string { return 'alipay'; }
    public function get_title(): string { return 'Alipay'; }
    public function is_available(): bool { ... }
    public function create_payment_intent(int $booking_id, float $amount, string $currency): array { ... }
    public function process_refund(int $booking_id, int $wc_order_id, float $amount, string $reason = '') { ... }
    public function handle_webhook(WP_REST_Request $request): WP_REST_Response { ... }
}
```

2. Register the gateway in `CareToChina_Payment_API` and `CareToChina_Payment_Manager`.
3. No changes are required in booking or dashboard code — the generic abstraction layer handles UI state transitions seamlessly.

---

## 7. Google OAuth 2.0 Login for Patients

### Architecture & Security Model
- **Patient-Only Scope**: Google OAuth (`CareToChina_Google_Login`) is strictly restricted to patient users. Attempts by accounts with `administrator`, `editor`, or `medical_staff` roles are rejected with an explicit security notice.
- **Server-Side Code Exchange**: The Client Secret is never exposed to the client; all code token exchanges occur server-side against `https://oauth2.googleapis.com/token`.
- **`email_verified` Claim Check**: The Google ID token / userinfo response must return `email_verified === true` before any account matching or auto-registration occurs.
- **CSRF State Validation**: Uses transient-backed random state nonces (`ctc_g_state_*`) with 15-minute expiration to prevent CSRF callback attacks.
- **Identity Storage**: Stores the Google permanent `sub` claim in `_ctc_google_sub` user meta.

---

## 8. Staff-Initiated Chat Payment Requests

### Overview & Data Model
Staff can send direct payment requests to patients inside existing chat threads (`wp_caretochina_payment_requests`):
- **Pricing Sources (Enforce Exactly 1)**:
  1. `treatment_plan`: Reference to existing treatment + specific pricing plan (snapshotted price).
  2. `custom_amount`: Custom ad-hoc fee amount + required short description label.
  3. `custom_treatment`: One-off, catalog-independent treatment with custom title, rich content (sanitized with `wp_kses_post()`), and locked price.
- **Dual-Layer Patient Ownership Check**: Verifies `current_user_id() === payment_request.patient_id` prior to initiating the payment pipeline.
- **Atomic Compare-And-Swap Duplicate-Accept Idempotency**:
  ```sql
  UPDATE wp_caretochina_payment_requests 
  SET status = 'processing' 
  WHERE id = %d AND status = 'pending'
  ```
  If 0 rows are affected, concurrent requests safely re-use the existing `converted_booking_id` without creating duplicate orders.
- **Staff Cancel State Validation**: Only requests in `pending` or `processing` status can be cancelled; already `accepted_paid` requests must go through the standard refund workflow.

---

## 9. Transaction Custom Post Type (`ctc_transaction`)

- **Read-Optimized Administrative View**: Synced automatically from `CareToChina_Payment_Manager::add_audit_log()` across all payment lifecycle transitions (`payment_succeeded`, `payment_failed`, `refund_full`, `refund_partial`, `booking_cancelled`).
- **Explicit Capability Protection**:
  - `map_meta_cap => true`
  - Explicit capabilities mapped directly to `caretochina_manage_bookings`.
  - Non-authorized roles cannot access or view transaction screens.
---

## 10. Treatment Pricing Plans, Booking Tier Step & Login-Gated Flow

### 10.1 Data Model & Treatment ↔ Pricing Plans Relationship
- Treatments in `caretochina-medical` are stored as **`hospital_specialty` taxonomy terms** (not separate CPTs) and map 1:1 to a virtual, non-taxable WooCommerce Product.
- Each Treatment has a 1-to-many relationship with Pricing Plans stored in `wp_caretochina_pricing_plans`:
  - `id`: Primary key.
  - `treatment_id`: Foreign key referencing the `hospital_specialty` `term_id`.
  - `name`: Tier / package label (e.g., "Standard VIP Package").
  - `price`: Decimal amount (e.g., `1200.00`).
  - `currency`: Store currency, derived server-side from `get_woocommerce_currency()`.
  - `description`: Scope and inclusions (hospital stay, surgery, concierge, etc.).
  - `display_order`: Integer sorting.
  - `is_active`: 1 (active) or 0 (inactive).

### 10.2 Server-Side Hard Delete Protection
- Pricing Plans check reference counts across `wp_caretochina_bookings` and `wp_caretochina_payment_requests`:
  ```sql
  SELECT COUNT(*) FROM wp_caretochina_bookings WHERE pricing_plan_id = %d;
  SELECT COUNT(*) FROM wp_caretochina_payment_requests WHERE pricing_plan_id = %d;
  ```
- If `ref_count > 0`, hard deletion is blocked by the server and the admin is prompted to deactivate the plan instead, preserving historical booking and payment integrity.

### 10.3 Booking Wizard 5-Step Flow & Dynamic Plan Loader
1. **Hospital Selection**: Browse and filter partner medical centers.
2. **Timing & Specialty**: Select required medical specialty / treatment (`hospital_specialty`).
3. **Pricing Plan Selection**: AJAX endpoint `ctc_get_treatment_plans` (registered for both `wp_ajax_` and `wp_ajax_nopriv_`) loads active plans dynamically as interactive cards.
4. **Patient Details**: Medical condition overview, name, country, age, gender.
5. **Review & Login-Gated Confirmation**: Shows full itinerary summary including locked package cost.

### 10.4 Anonymous Browsing & Login Gate
- Visitors can freely browse hospitals, treatments, and pricing tiers anonymously without premature friction.
- Gating occurs at the final submission step:
  - Draft state is preserved in client-side state and `sessionStorage.setItem('ctc_wizard_draft', ...)`.
  - Triggers the Auth Gate Modal (`#wiz-auth-gate-modal`) offering Sign In, New Registration, and Continue with Google.
  - Upon authentication, the draft state is automatically retrieved and submitted, seamlessly opening the Stripe/PayPal payment modal.

---

## 11. First-Run Onboarding Setup Wizard

### 11.1 Activation Redirect & Navigation
- On plugin activation (`register_activation_hook`), a 60-second transient `ctc_activation_redirect` is established.
- On the next single admin load, `admin_init` checks the transient, verifies `manage_options`, excludes bulk activation (`$_GET['activate-multi']`) and background requests (AJAX/REST/cron/WP-CLI), and redirects the administrator to `admin.php?page=caretochina-setup-wizard`.
- A persistent admin submenu **CareToChina &rarr; Setup Wizard** is available anytime under the coordinator menu for re-entry.

### 11.2 6-Step Multi-Step Controller (`CareToChina_Setup_Wizard`)
1. **Welcome**: System overview and introductory launch.
2. **WooCommerce Engine Check**: Detects active, inactive, or uninstalled states with 1-click `Plugin_Upgrader` install/activate and direct filesystem fallback.
3. **Dedicated Pages**: Create or assign Patient Dashboard, Staff Portal, Privacy Policy, and Terms & Conditions.
4. **Google reCAPTCHA Security**: Select v2 Checkbox or v3 Invisible, configure encrypted keys, threshold, and form toggles.
5. **Google OAuth Sign-In**: Surfaces Client ID & Secret configuration with authorized redirect URI (`/?ctc_google_callback=1`).
6. **Finish & Safety Preferences**: Summary overview, direct settings links, "Delete data on uninstall" toggle, and "Export Data Now" button.
- All steps include individual "Skip this step" and global "Skip setup entirely" options.

---

## 12. Google reCAPTCHA v2 / v3 Security Protection

### 12.1 Configuration & Encrypted Storage
- Managed by `CareToChina_Recaptcha`:
  - `ctc_recaptcha_version`: `'v2'` (checkbox) or `'v3'` (invisible score-based).
  - Keys encrypted at rest via `CareToChina_Payment_Security`: `ctc_recaptcha_v2_secret_key`, `ctc_recaptcha_v3_secret_key`.
  - `ctc_recaptcha_v3_threshold`: Float (default `0.5`).
- Independent Form Location Toggles:
  - `ctc_recaptcha_enable_login`
  - `ctc_recaptcha_enable_register`
  - `ctc_recaptcha_enable_booking`
- **Badge Visibility & Terms Compliance (`ctc_recaptcha_hide_badge`)**:
  - Checkbox option (default OFF / 0).
  - When enabled, enqueues `.grecaptcha-badge { visibility: hidden !important; }` and automatically displays Google's required legal attribution links (*"This site is protected by reCAPTCHA and the Google Privacy Policy and Terms of Service apply."*) directly below active protected forms in strict compliance with Google Terms of Service.
- **Google OAuth Exemption**: "Continue with Google" is handled directly by Google authentication and is exempt from reCAPTCHA.

### 12.2 Server-Side Verification
- Form submissions verify the client token server-side against `https://www.google.com/recaptcha/api/siteverify` using `wp_remote_post()`. Submissions failing verification or scoring below the threshold are rejected with an explicit `WP_Error`. Verification logic is independent of badge visibility.

---

## 13. Dedicated Pages & Dynamic URL Resolution (`CareToChina_Page_Manager`)

- Centralized page resolver replacing hardcoded URL strings:
  - `patient_dashboard`: `[caretochina_patient_dashboard]` (slug: `patient-dashboard`)
  - `staff_portal`: `[caretochina_staff_portal]` (slug: `staff-portal`)
  - `privacy_policy`: Privacy Policy (checks `wp_page_for_privacy_policy`)
  - `terms`: Terms & Conditions (slug: `terms-and-conditions`)
- Method `CareToChina_Page_Manager::get_page_url($type)` returns `get_permalink($id)` when configured or falls back gracefully to `home_url('/{slug}/')`.

---

## 14. Uninstall Data Management & Safety-Net Backups

### 14.1 Safe Default & Opt-In Deletion
- Option: `ctc_delete_data_on_uninstall` (defaults to `0` / OFF).
- If OFF, `uninstall.php` leaves all database tables and options intact.

### 14.2 Pure PHP `$wpdb` SQL Data Exporter (`CareToChina_Data_Exporter`)
- Zero shell commands (`exec()`, `shell_exec()`, `mysqldump`).
- Dynamically iterates plugin tables using `{$wpdb->prefix}caretochina_*` (`bookings`, `pricing_plans`, `payment_requests`, `messages`, `processed_webhook_events`, `payment_audit_logs`).
- Exports plugin `wp_options` with credentials preserved strictly as ciphertext (never decrypted into plaintext).
- **On-Demand Export**: Method `stream_download()` streams the SQL file directly to browser headers (`php://output`) without leaving files on disk.

### 14.3 Protected Safety-Net Backup Directory
- When opt-in deletion is triggered, `uninstall.php` writes a safety-net backup file before dropping tables:
  `wp-content/uploads/caretochina-backups/caretochina-backup-{timestamp}-{random16}.sql`
- **Directory Protection**:
  - Apache `.htaccess` with both Apache 2.2 (`Deny from all`) and Apache 2.4+ (`Require all denied`).
  - Blank `index.php` to prevent directory listing.
  - Cryptographically random 16-character filename component.
- **Nginx Web Server Configuration**:
  On Nginx hosting, site administrators should add this block to their server configuration:
  ```nginx
  location ^~ /wp-content/uploads/caretochina-backups/ {
      deny all;
      return 403;
  }
  ```

### 14.4 Conditional Table Dropping & Explicit Option Cleanup
- `uninstall.php` strictly verifies `file_exists($backup_file)` and `filesize($backup_file) > 0`. If backup generation fails, deletion is aborted immediately to protect data integrity.
- On verified backup success, plugin tables are dropped dynamically via `$wpdb->prefix` and options are removed by iterating an explicit whitelist array passed to `delete_option()`.

