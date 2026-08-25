<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!defined('CARETOCHINA_PAYMENT_DB_VERSION')) {
    define('CARETOCHINA_PAYMENT_DB_VERSION', '2.0.0');
}

class CareToChina_Booking_DB {
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // 1. DROP LEGACY PRICING PLANS TABLE
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}caretochina_pricing_plans");

        // 2. BOOKINGS TABLE (WITH PACKAGE_ID & PAYMENT CACHE)
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';
        
        $sql_bookings = "CREATE TABLE $table_bookings (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            booking_code varchar(30) NOT NULL,
            patient_id bigint(20) DEFAULT 0,
            is_guest tinyint(1) DEFAULT 0,
            guest_token_hash varchar(255) DEFAULT '',
            hospital_id bigint(20) DEFAULT 0,
            hospital_name varchar(255) DEFAULT '',
            specialty varchar(255) DEFAULT '',
            package_id bigint(20) DEFAULT 0,
            treatment_timing varchar(100) DEFAULT '',
            quote_details text,
            country varchar(100) DEFAULT '',
            full_name varchar(150) NOT NULL,
            age int(11) DEFAULT NULL,
            gender varchar(20) DEFAULT '',
            email varchar(150) NOT NULL,
            phone varchar(50) NOT NULL,
            whatsapp varchar(100) DEFAULT '',
            wechat varchar(100) DEFAULT '',
            messenger varchar(100) DEFAULT '',
            linkedin varchar(100) DEFAULT '',
            status varchar(30) DEFAULT 'pending',
            timeline_stage int(11) DEFAULT 1,
            invoice_status varchar(100) DEFAULT 'Pending Deposit',
            wc_order_id bigint(20) DEFAULT 0,
            amount decimal(10,2) DEFAULT 0.00,
            currency varchar(10) DEFAULT 'USD',
            payment_gateway varchar(50) DEFAULT '',
            paid_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY booking_code (booking_code),
            KEY guest_token_hash (guest_token_hash),
            KEY patient_id (patient_id),
            KEY is_guest (is_guest),
            KEY package_id (package_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        // 3. MESSAGES TABLE (WITH MESSAGE TYPE, PAYMENT REQUEST & ATTACHMENT FIELDS)
        $table_messages = $wpdb->prefix . 'caretochina_messages';
        $sql_messages = "CREATE TABLE $table_messages (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) NOT NULL,
            sender_type varchar(30) NOT NULL,
            sender_name varchar(100) NOT NULL,
            message text NOT NULL,
            message_type varchar(30) DEFAULT 'text',
            payment_request_id bigint(20) DEFAULT 0,
            attachment_url varchar(255) DEFAULT '',
            attachment_name varchar(255) DEFAULT '',
            attachment_type varchar(50) DEFAULT '',
            is_read tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY booking_id (booking_id),
            KEY sender_type (sender_type),
            KEY is_read (is_read),
            KEY created_at (created_at)
        ) $charset_collate;";

        // 4. PROCESSED WEBHOOK EVENTS TABLE (EVENT-ID IDEMPOTENCY)
        $table_webhook_events = $wpdb->prefix . 'caretochina_processed_webhook_events';
        $sql_webhook_events = "CREATE TABLE $table_webhook_events (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_id varchar(255) NOT NULL,
            gateway varchar(50) NOT NULL,
            event_type varchar(100) NOT NULL,
            processed_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY event_id (event_id)
        ) $charset_collate;";

        // 5. PAYMENT AUDIT LOGS TABLE
        $table_audit_logs = $wpdb->prefix . 'caretochina_payment_audit_logs';
        $sql_audit_logs = "CREATE TABLE $table_audit_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) NOT NULL,
            wc_order_id bigint(20) NOT NULL,
            actor_id bigint(20) NOT NULL,
            action varchar(50) NOT NULL,
            amount decimal(10,2) DEFAULT 0.00,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY booking_id (booking_id)
        ) $charset_collate;";

        // 6. PAYMENT REQUESTS TABLE (STAFF CHAT REQUESTS)
        $table_payment_requests = $wpdb->prefix . 'caretochina_payment_requests';
        $sql_payment_requests = "CREATE TABLE $table_payment_requests (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            request_code varchar(30) NOT NULL,
            chat_thread_booking_id bigint(20) NOT NULL,
            converted_booking_id bigint(20) DEFAULT 0,
            patient_id bigint(20) NOT NULL,
            created_by bigint(20) NOT NULL,
            pricing_type varchar(30) NOT NULL,
            package_id bigint(20) DEFAULT 0,
            plan_name varchar(150) DEFAULT '',
            custom_title varchar(255) DEFAULT '',
            custom_content text,
            amount decimal(10,2) NOT NULL DEFAULT 0.00,
            currency varchar(10) DEFAULT 'USD',
            status varchar(30) DEFAULT 'pending',
            chat_message_id bigint(20) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY request_code (request_code),
            KEY chat_thread_booking_id (chat_thread_booking_id),
            KEY patient_id (patient_id),
            KEY package_id (package_id),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_bookings);
        dbDelta($sql_messages);
        dbDelta($sql_webhook_events);
        dbDelta($sql_audit_logs);
        dbDelta($sql_payment_requests);

        // Column migration for existing tables if package_id is missing or old columns remain
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($table_bookings))) === $table_bookings) {
            // Check if package_id column exists
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $has_pkg = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$wpdb->prefix}caretochina_bookings LIKE %s", $wpdb->esc_like('package_id')));
            if (!$has_pkg) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
                $wpdb->query("ALTER TABLE {$wpdb->prefix}caretochina_bookings ADD COLUMN package_id bigint(20) DEFAULT 0 AFTER specialty, ADD KEY package_id (package_id)");
            }
            // Drop old pricing_plan_id column if present
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $has_old_plan = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$wpdb->prefix}caretochina_bookings LIKE %s", $wpdb->esc_like('pricing_plan_id')));
            if ($has_old_plan) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
                $wpdb->query("ALTER TABLE {$wpdb->prefix}caretochina_bookings DROP COLUMN pricing_plan_id");
            }
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($table_payment_requests))) === $table_payment_requests) {
            // Check if package_id column exists
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $has_pkg_req = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$wpdb->prefix}caretochina_payment_requests LIKE %s", $wpdb->esc_like('package_id')));
            if (!$has_pkg_req) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
                $wpdb->query("ALTER TABLE {$wpdb->prefix}caretochina_payment_requests ADD COLUMN package_id bigint(20) DEFAULT 0 AFTER pricing_type, ADD KEY package_id (package_id)");
            }
            // Drop old treatment_id / pricing_plan_id columns if present
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$wpdb->prefix}caretochina_payment_requests LIKE %s", $wpdb->esc_like('pricing_plan_id')))) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
                $wpdb->query("ALTER TABLE {$wpdb->prefix}caretochina_payment_requests DROP COLUMN pricing_plan_id");
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$wpdb->prefix}caretochina_payment_requests LIKE %s", $wpdb->esc_like('treatment_id')))) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
                $wpdb->query("ALTER TABLE {$wpdb->prefix}caretochina_payment_requests DROP COLUMN treatment_id");
            }
        }

        // Update DB version option
        update_option('caretochina_payment_db_version', CARETOCHINA_PAYMENT_DB_VERSION);

        // DATA MIGRATION FROM LEGACY TABLES IF THEY EXIST
        $legacy_bookings = $wpdb->prefix . 'careyou_bookings';
        $legacy_messages = $wpdb->prefix . 'careyou_messages';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($legacy_bookings))) === $legacy_bookings) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}careyou_bookings");
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($legacy_messages))) === $legacy_messages) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query("INSERT IGNORE INTO {$wpdb->prefix}caretochina_messages (id, booking_id, sender_type, sender_name, message, is_read, created_at) SELECT id, booking_id, sender_type, sender_name, message, is_read, created_at FROM {$wpdb->prefix}careyou_messages");
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}careyou_messages");
        }
    }
}