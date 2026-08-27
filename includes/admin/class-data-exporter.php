<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (!defined('ARRAY_N')) {
    define('ARRAY_N', 'ARRAY_N');
}

class CareToChina_Data_Exporter {

    /**
     * Get list of plugin custom tables with dynamic prefix
     */
    public static function get_plugin_tables() {
        global $wpdb;
        return [
            $wpdb->prefix . 'caretochina_bookings',
            $wpdb->prefix . 'caretochina_payment_requests',
            $wpdb->prefix . 'caretochina_messages',
            $wpdb->prefix . 'caretochina_processed_webhook_events',
            $wpdb->prefix . 'caretochina_payment_audit_logs',
        ];
    }

    /**
     * Get explicit whitelist of plugin options
     */
    public static function get_plugin_option_names() {
        return [
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
    }

    /**
     * Pure PHP / $wpdb SQL Dump Generator (Zero shell/mysqldump commands)
     *
     * @return string
     */
    public static function generate_sql_dump() {
        global $wpdb;

        $sql  = "-- =========================================================================\n";
        $sql .= "-- CareToChina Medical Suite Database Export\n";
        $sql .= "-- Generated: " . gmdate('Y-m-d H:i:s') . " UTC\n";
        $sql .= "-- Host: " . esc_sql(DB_HOST) . "\n";
        $sql .= "-- Database: " . esc_sql(DB_NAME) . "\n";
        $sql .= "-- Table Prefix: " . esc_sql($wpdb->prefix) . "\n";
        $sql .= "-- =========================================================================\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        $tables = self::get_plugin_tables();

        foreach ($tables as $table) {
            if (!in_array($table, self::get_plugin_tables(), true)) {
                continue;
            }

            // Check if table exists in database
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($table)));
            if ($exists !== $table) {
                continue;
            }

            $sql .= "-- -------------------------------------------------------------------------\n";
            $sql .= "-- Table structure & data for `$table`\n";
            $sql .= "-- -------------------------------------------------------------------------\n";

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $create_row = $wpdb->get_row("SHOW CREATE TABLE `" . esc_sql($table) . "`", ARRAY_N);
            if ($create_row && isset($create_row[1])) {
                $sql .= "DROP TABLE IF EXISTS `$table`;\n";
                $sql .= $create_row[1] . ";\n\n";
            }

            // Dump Table Data in Chunks
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $rows = $wpdb->get_results("SELECT * FROM `" . esc_sql($table) . "`", ARRAY_A);
            if (!empty($rows)) {
                $columns = array_keys($rows[0]);
                $col_names = '`' . implode('`, `', $columns) . '`';

                foreach ($rows as $row) {
                    $values = [];
                    foreach ($row as $val) {
                        if (is_null($val)) {
                            $values[] = 'NULL';
                        } elseif (is_numeric($val) && !is_string($val)) {
                            $values[] = $val;
                        } else {
                            $values[] = "'" . esc_sql($val) . "'";
                        }
                    }
                    $sql .= "INSERT INTO `$table` ($col_names) VALUES (" . implode(', ', $values) . ");\n";
                }
                $sql .= "\n";
            }
        }

        // Dump Plugin Options (Values remain encrypted as ciphertext, never decrypted)
        $option_names = self::get_plugin_option_names();
        $options_rows = [];
        foreach ($option_names as $opt_key) {
            $val = get_option($opt_key, null);
            if ($val !== null) {
                $val_str = is_scalar($val) ? (string) $val : maybe_serialize($val);
                $options_rows[] = [
                    'option_name'  => $opt_key,
                    'option_value' => $val_str,
                    'autoload'     => 'yes',
                ];
            }
        }

        if (!empty($options_rows)) {
            $sql .= "-- -------------------------------------------------------------------------\n";
            $sql .= "-- CareToChina Plugin Configuration Options\n";
            $sql .= "-- -------------------------------------------------------------------------\n";
            foreach ($options_rows as $opt) {
                $opt_name = esc_sql($opt['option_name']);
                $opt_val  = esc_sql($opt['option_value']);
                $autoload = esc_sql($opt['autoload']);
                $sql .= "INSERT INTO `{$wpdb->options}` (`option_name`, `option_value`, `autoload`) VALUES ('$opt_name', '$opt_val', '$autoload') ON DUPLICATE KEY UPDATE `option_value` = VALUES(`option_value`);\n";
            }
            $sql .= "\n";
        }

        $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        $sql .= "-- End of CareToChina Export\n";

        return $sql;
    }

    /**
     * Stream SQL dump directly to browser (Zero file left on disk)
     */
    public static function stream_download() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'caretochina-medical'));
        }

        $dump = self::generate_sql_dump();
        $filename = 'caretochina-data-export-' . gmdate('Y-m-d-His') . '.sql';

        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($dump));
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $dump;
        exit;
    }

    /**
     * Ensure protected backup directory exists with .htaccess and blank index.php
     *
     * @return string Directory path
     */
    public static function get_and_secure_backup_dir() {
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/caretochina-backups';

        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }

        // Dual Apache .htaccess protection
        $htaccess_path = $backup_dir . '/.htaccess';
        if (!file_exists($htaccess_path)) {
            $htaccess_content  = "# Apache 2.2\n";
            $htaccess_content .= "<IfModule !authz_core_module>\nDeny from all\n</IfModule>\n";
            $htaccess_content .= "# Apache 2.4+\n";
            $htaccess_content .= "<IfModule authz_core_module>\nRequire all denied\n</IfModule>\n";
            @file_put_contents($htaccess_path, $htaccess_content);
        }

        // Blank index.php protection
        $index_path = $backup_dir . '/index.php';
        if (!file_exists($index_path)) {
            @file_put_contents($index_path, "<?php\n// Silence is golden.\n");
        }

        return $backup_dir;
    }

    /**
     * Write verified safety-net backup file to protected directory
     *
     * @return string|false Filepath on success, false on failure
     */
    public static function write_backup_file() {
        $backup_dir = self::get_and_secure_backup_dir();
        if (!is_dir($backup_dir) || !wp_is_writable($backup_dir)) {
            return false;
        }

        $random_token = function_exists('wp_generate_password') ? wp_generate_password(16, false, false) : bin2hex(random_bytes(8));
        $filename = 'caretochina-backup-' . gmdate('Y-m-d-His') . '-' . $random_token . '.sql';
        $filepath = $backup_dir . '/' . $filename;

        $dump = self::generate_sql_dump();
        $bytes_written = @file_put_contents($filepath, $dump);

        // Verification: file must exist, be readable, and have positive size
        if ($bytes_written === false || !file_exists($filepath) || filesize($filepath) <= 0) {
            return false;
        }

        return $filepath;
    }
}
