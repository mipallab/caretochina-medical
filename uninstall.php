<?php
/**
 * CareToChina Medical Suite Uninstall Handler
 *
 * @package CareToChina_Medical
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// 1. Safe default check: Only delete data if admin explicitly opted in
$caretochina_delete_data = (bool) intval(get_option('ctc_delete_data_on_uninstall', 0));

if (!$caretochina_delete_data) {
    // Standard WordPress convention: Preserve all tables and settings
    return;
}

// 2. Load Data Exporter to create safety-net backup before dropping anything
require_once dirname(__FILE__) . '/includes/admin/class-data-exporter.php';

$caretochina_backup_path = CareToChina_Data_Exporter::write_backup_file();

// 3. CONDITIONAL TABLE DELETION: Must verify backup file is valid and non-empty
if (!$caretochina_backup_path || !file_exists($caretochina_backup_path) || filesize($caretochina_backup_path) <= 0) {
    return;
}

global $wpdb;

// 4. Drop Plugin Tables using dynamic prefix and strict allowlist
$caretochina_tables = [
    $wpdb->prefix . 'caretochina_bookings',
    $wpdb->prefix . 'caretochina_payment_requests',
    $wpdb->prefix . 'caretochina_messages',
    $wpdb->prefix . 'caretochina_processed_webhook_events',
    $wpdb->prefix . 'caretochina_payment_audit_logs',
];

foreach ($caretochina_tables as $caretochina_tbl) {
    if (in_array($caretochina_tbl, $caretochina_tables, true)) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $wpdb->query("DROP TABLE IF EXISTS `" . esc_sql($caretochina_tbl) . "`");
    }
}

// 5. Explicit Enumerated Option Cleanup (including encrypted secrets)
$caretochina_options_to_delete = [
    'caretochina_medical_version',
    'caretochina_payment_db_version',
    'ctc_payment_environment_mode',
    'ctc_payment_currency',
    'ctc_delete_data_on_uninstall',
    'ctc_stripe_test_pub_key',
    'ctc_stripe_test_sec_key',
    'ctc_stripe_test_wh_secret',
    'ctc_stripe_live_pub_key',
    'ctc_stripe_live_sec_key',
    'ctc_stripe_live_wh_secret',
    'ctc_paypal_test_client_id',
    'ctc_paypal_test_client_secret',
    'ctc_paypal_live_client_id',
    'ctc_paypal_live_client_secret',
    'ctc_google_client_id',
    'ctc_google_client_secret',
    'ctc_recaptcha_version',
    'ctc_recaptcha_v2_site_key',
    'ctc_recaptcha_v2_secret_key',
    'ctc_recaptcha_v3_site_key',
    'ctc_recaptcha_v3_secret_key',
    'ctc_recaptcha_v3_threshold',
    'ctc_recaptcha_enable_login',
    'ctc_recaptcha_enable_register',
    'ctc_recaptcha_enable_booking',
    'ctc_recaptcha_hide_badge',
    'ctc_page_patient_dashboard',
    'ctc_page_staff_portal',
    'ctc_page_privacy_policy',
    'ctc_page_terms',
];

foreach ($caretochina_options_to_delete as $caretochina_opt_name) {
    delete_option($caretochina_opt_name);
}
