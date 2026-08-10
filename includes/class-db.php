<?php
if (!defined('ABSPATH')) {
    exit;
}

class CareToChina_Booking_DB {
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // 1. NEW BOOKINGS TABLE
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';
        
        // Force table update if old schema is active
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_bookings'") === $table_bookings) {
            if ($wpdb->get_var("SHOW COLUMNS FROM `$table_bookings` LIKE 'patient_name'")) {
                $wpdb->query("DROP TABLE IF EXISTS `$table_bookings`");
            }
        }

        $sql_bookings = "CREATE TABLE $table_bookings (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            booking_code varchar(30) NOT NULL,
            patient_id bigint(20) DEFAULT 0,
            hospital_id bigint(20) DEFAULT 0,
            hospital_name varchar(255) DEFAULT '',
            specialty text NOT NULL,
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
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY booking_code (booking_code)
        ) $charset_collate;";

        // 2. NEW MESSAGES TABLE
        $table_messages = $wpdb->prefix . 'caretochina_messages';
        $sql_messages = "CREATE TABLE $table_messages (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) NOT NULL,
            sender_type varchar(30) NOT NULL,
            sender_name varchar(100) NOT NULL,
            message text NOT NULL,
            is_read tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_bookings);
        dbDelta($sql_messages);

        // DATA MIGRATION FROM LEGACY TABLES IF THEY EXIST
        $legacy_bookings = $wpdb->prefix . 'careyou_bookings';
        $legacy_messages = $wpdb->prefix . 'careyou_messages';

        if ($wpdb->get_var("SHOW TABLES LIKE '$legacy_bookings'") === $legacy_bookings) {
            $wpdb->query("DROP TABLE IF EXISTS $legacy_bookings");
        }

        if ($wpdb->get_var("SHOW TABLES LIKE '$legacy_messages'") === $legacy_messages) {
            $wpdb->query("INSERT IGNORE INTO $table_messages (id, booking_id, sender_type, sender_name, message, is_read, created_at) SELECT id, booking_id, sender_type, sender_name, message, is_read, created_at FROM $legacy_messages");
            $wpdb->query("DROP TABLE IF EXISTS $legacy_messages");
        }
    }
}