<?php
/**
 * CareToChina Async Mailer
 * High-performance, non-blocking email dispatcher for fast checkout, booking, and approvals.
 */

if (!defined('ABSPATH')) {
    exit;
}

class CareToChina_Async_Mailer {

    private static $queue = [];
    private static $registered = false;

    public static function init() {
        if (!self::$registered) {
            add_action('shutdown', [__CLASS__, 'process_queue'], 999);
            self::$registered = true;
        }
    }

    /**
     * Queue an email to be sent asynchronously / on shutdown
     *
     * @param string|array $to
     * @param string $subject
     * @param string $message
     * @param array|string $headers
     * @param array $attachments
     * @return bool
     */
    public static function send($to, $subject, $message, $headers = '', $attachments = []) {
        self::init();

        self::$queue[] = [
            'to'          => $to,
            'subject'     => $subject,
            'message'     => $message,
            'headers'     => $headers,
            'attachments' => $attachments,
        ];

        return true;
    }

    /**
     * Process queued emails on PHP shutdown (after HTTP response is flushed to client)
     */
    public static function process_queue() {
        if (empty(self::$queue)) {
            return;
        }

        // If running in FastCGI, finish the request to immediately release the browser!
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        // Extend timeout for SMTP socket connections
        if (function_exists('set_time_limit')) {
            // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
            @set_time_limit(60);
        }

        foreach (self::$queue as $email) {
            try {
                wp_mail(
                    $email['to'],
                    $email['subject'],
                    $email['message'],
                    $email['headers'],
                    $email['attachments']
                );
            } catch (\Throwable $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log('CareToChina Async Mailer Error: ' . $e->getMessage());
                }
            }
        }

        self::$queue = [];
    }
}
