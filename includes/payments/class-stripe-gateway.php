<?php
if (!defined('ABSPATH')) {
    exit;
}

class CareToChina_Stripe_Gateway implements CareToChina_Payment_Gateway_Interface {

    private $mode;
    private $publishable_key;
    private $secret_key;
    private $webhook_secret;

    public function __construct() {
        $this->mode = CareToChina_Payment_Admin_Settings::get_mode();
        $this->load_keys();
    }

    private function load_keys() {
        if ($this->mode === 'test') {
            $this->publishable_key = defined('CARETOCHINA_STRIPE_TEST_PUBLISHABLE_KEY') 
                ? CARETOCHINA_STRIPE_TEST_PUBLISHABLE_KEY 
                : get_option('ctc_stripe_test_pub_key', '');
            
            $secret = defined('CARETOCHINA_STRIPE_TEST_SECRET_KEY') 
                ? CARETOCHINA_STRIPE_TEST_SECRET_KEY 
                : get_option('ctc_stripe_test_sec_key', '');
            $this->secret_key = CareToChina_Payment_Security::decrypt_secret($secret);

            $wh_secret = defined('CARETOCHINA_STRIPE_TEST_WEBHOOK_SECRET') 
                ? CARETOCHINA_STRIPE_TEST_WEBHOOK_SECRET 
                : get_option('ctc_stripe_test_wh_secret', '');
            $this->webhook_secret = CareToChina_Payment_Security::decrypt_secret($wh_secret);
        } else {
            $this->publishable_key = defined('CARETOCHINA_STRIPE_PUBLISHABLE_KEY') 
                ? CARETOCHINA_STRIPE_PUBLISHABLE_KEY 
                : get_option('ctc_stripe_live_pub_key', '');

            $secret = defined('CARETOCHINA_STRIPE_SECRET_KEY') 
                ? CARETOCHINA_STRIPE_SECRET_KEY 
                : get_option('ctc_stripe_live_sec_key', '');
            $this->secret_key = CareToChina_Payment_Security::decrypt_secret($secret);

            $wh_secret = defined('CARETOCHINA_STRIPE_WEBHOOK_SECRET') 
                ? CARETOCHINA_STRIPE_WEBHOOK_SECRET 
                : get_option('ctc_stripe_live_wh_secret', '');
            $this->webhook_secret = CareToChina_Payment_Security::decrypt_secret($wh_secret);
        }
    }

    public function get_id(): string {
        return 'stripe';
    }

    public function get_title(): string {
        return __('Stripe (Credit/Debit Cards & Apple Pay)', 'caretochina-medical');
    }

    public function is_available(): bool {
        return !empty($this->publishable_key) && !empty($this->secret_key);
    }

    public function get_publishable_key(): string {
        return $this->publishable_key;
    }

    /**
     * Create a Stripe PaymentIntent with server-side price and Idempotency key
     */
    public function create_payment_intent(int $booking_id, float $amount, string $currency): array {
        if (!$this->is_available()) {
            return [
                'success' => false,
                'message' => __('Stripe payment gateway is not fully configured.', 'caretochina-medical'),
            ];
        }

        // Zero-decimal currencies check
        $zero_decimal = in_array(strtolower($currency), ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf']);
        $amount_cents = $zero_decimal ? intval(round($amount)) : intval(round($amount * 100));

        $idempotency_key = 'ctc_booking_' . $booking_id . '_' . time();

        $response = wp_remote_post('https://api.stripe.com/v1/payment_intents', [
            'headers' => [
                'Authorization'   => 'Bearer ' . $this->secret_key,
                'Idempotency-Key' => $idempotency_key,
                'Content-Type'    => 'application/x-www-form-urlencoded',
            ],
            'body' => http_build_query([
                'amount'               => $amount_cents,
                'currency'             => strtolower($currency),
                'payment_method_types' => ['card'],
                'metadata'             => [
                    'caretochina_booking_id' => $booking_id,
                ],
                /* translators: %d: Booking ID */
                'description'          => sprintf(__('CareToChina Medical Booking #%d', 'caretochina-medical'), $booking_id),
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('Stripe PaymentIntent Creation Failed: ' . $response->get_error_message());
            return [
                'success' => false,
                'message' => __('Unable to initialize card payment session. Please try again.', 'caretochina-medical'),
            ];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200 || empty($body['client_secret'])) {
            $err_msg = isset($body['error']['message']) ? $body['error']['message'] : 'Stripe API error';
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r
            error_log('Stripe Error Body: ' . print_r($body, true));
            return [
                'success' => false,
                'message' => __('Payment service unavailable. Please try again or use another payment method.', 'caretochina-medical'),
            ];
        }

        return [
            'success'         => true,
            'client_secret'   => $body['client_secret'],
            'payment_intent'  => $body['id'],
            'publishable_key' => $this->publishable_key,
        ];
    }

    /**
     * Process Stripe refund via API
     */
    public function process_refund(int $booking_id, int $wc_order_id, float $amount, string $reason = '') {
        if (!$this->is_available()) {
            return new WP_Error('stripe_unavailable', __('Stripe secret key not configured.', 'caretochina-medical'));
        }

        $order = wc_get_order($wc_order_id);
        if (!$order) {
            return new WP_Error('order_not_found', __('WooCommerce order not found.', 'caretochina-medical'));
        }

        $transaction_id = $order->get_transaction_id();
        if (empty($transaction_id)) {
            // Attempt to get payment intent ID from order meta
            $transaction_id = $order->get_meta('_stripe_intent_id');
        }

        if (empty($transaction_id)) {
            return new WP_Error('missing_transaction_id', __('No Stripe transaction ID found for this order.', 'caretochina-medical'));
        }

        $currency = $order->get_currency();
        $zero_decimal = in_array(strtolower($currency), ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf']);
        $amount_cents = $zero_decimal ? intval(round($amount)) : intval(round($amount * 100));

        $params = [
            'payment_intent' => $transaction_id,
            'amount'         => $amount_cents,
            'reason'         => 'requested_by_customer',
        ];
        if (!empty($reason)) {
            $params['metadata'] = ['reason' => sanitize_text_field($reason)];
        }

        $response = wp_remote_post('https://api.stripe.com/v1/refunds', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->secret_key,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body'    => http_build_query($params),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200 || empty($body['id'])) {
            $err_msg = isset($body['error']['message']) ? $body['error']['message'] : 'Stripe refund failed';
            return new WP_Error('stripe_refund_error', $err_msg);
        }

        return [
            'success'   => true,
            'refund_id' => $body['id'],
            'amount'    => $amount,
        ];
    }

    /**
     * Handle Stripe Webhooks with HMAC Signature & Timestamp Tolerance verification
     */
    public function handle_webhook(WP_REST_Request $request): WP_REST_Response {
        $signature_header = $request->get_header('stripe-signature');
        if (empty($signature_header)) {
            return new WP_REST_Response(['error' => 'Missing Stripe-Signature header'], 400);
        }

        $payload = $request->get_body();
        if (empty($payload)) {
            return new WP_REST_Response(['error' => 'Empty request body'], 400);
        }

        // Verify HMAC signature and timestamp tolerance (default 300 seconds)
        if (!empty($this->webhook_secret)) {
            $sig_verified = $this->verify_stripe_signature($payload, $signature_header, $this->webhook_secret);
            if (!$sig_verified) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('Stripe Webhook Signature Verification Failed.');
                return new WP_REST_Response(['error' => 'Invalid signature or timestamp expired'], 400);
            }
        }

        $event = json_decode($payload, true);
        if (!$event || empty($event['id']) || empty($event['type'])) {
            return new WP_REST_Response(['error' => 'Invalid payload format'], 400);
        }

        $event_id = $event['id'];
        $event_type = $event['type'];

        // Delegate atomic processing to Payment Manager
        $processed = CareToChina_Payment_Manager::instance()->process_webhook_event(
            'stripe',
            $event_id,
            $event_type,
            $event
        );

        return new WP_REST_Response(['status' => 'success', 'processed' => $processed], 200);
    }

    /**
     * Verify Stripe HMAC-SHA256 signature header (t=timestamp, v1=signature)
     */
    private function verify_stripe_signature($payload, $header, $secret) {
        $items = explode(',', $header);
        $timestamp = null;
        $signatures = [];

        foreach ($items as $item) {
            $pair = explode('=', trim($item), 2);
            if (count($pair) === 2) {
                if ($pair[0] === 't') {
                    $timestamp = intval($pair[1]);
                } elseif ($pair[0] === 'v1') {
                    $signatures[] = $pair[1];
                }
            }
        }

        if (!$timestamp || empty($signatures)) {
            return false;
        }

        // Check timestamp tolerance (default 300 seconds)
        if (abs(time() - $timestamp) > 300) {
            return false;
        }

        $signed_payload = $timestamp . '.' . $payload;
        $expected_signature = hash_hmac('sha256', $signed_payload, $secret);

        foreach ($signatures as $sig) {
            if (hash_equals($expected_signature, $sig)) {
                return true;
            }
        }

        return false;
    }
}
