<?php
if (!defined('ABSPATH')) {
    exit;
}

class CareToChina_PayPal_Gateway implements CareToChina_Payment_Gateway_Interface {

    private $mode;
    private $client_id;
    private $client_secret;
    private $webhook_id;
    private $api_url;

    public function __construct() {
        $this->mode = CareToChina_Payment_Admin_Settings::get_mode();
        $this->api_url = ($this->mode === 'test') ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        $this->load_keys();
    }

    private function load_keys() {
        if ($this->mode === 'test') {
            $this->client_id = defined('CARETOCHINA_PAYPAL_TEST_CLIENT_ID') 
                ? CARETOCHINA_PAYPAL_TEST_CLIENT_ID 
                : get_option('ctc_paypal_test_client_id', '');

            $secret = defined('CARETOCHINA_PAYPAL_TEST_CLIENT_SECRET') 
                ? CARETOCHINA_PAYPAL_TEST_CLIENT_SECRET 
                : get_option('ctc_paypal_test_client_secret', '');
            $this->client_secret = CareToChina_Payment_Security::decrypt_secret($secret);

            $this->webhook_id = defined('CARETOCHINA_PAYPAL_TEST_WEBHOOK_ID') 
                ? CARETOCHINA_PAYPAL_TEST_WEBHOOK_ID 
                : get_option('ctc_paypal_test_wh_id', '');
        } else {
            $this->client_id = defined('CARETOCHINA_PAYPAL_CLIENT_ID') 
                ? CARETOCHINA_PAYPAL_CLIENT_ID 
                : get_option('ctc_paypal_live_client_id', '');

            $secret = defined('CARETOCHINA_PAYPAL_CLIENT_SECRET') 
                ? CARETOCHINA_PAYPAL_CLIENT_SECRET 
                : get_option('ctc_paypal_live_client_secret', '');
            $this->client_secret = CareToChina_Payment_Security::decrypt_secret($secret);

            $this->webhook_id = defined('CARETOCHINA_PAYPAL_WEBHOOK_ID') 
                ? CARETOCHINA_PAYPAL_WEBHOOK_ID 
                : get_option('ctc_paypal_live_wh_id', '');
        }
    }

    public function get_id(): string {
        return 'paypal';
    }

    public function get_title(): string {
        return __('PayPal (PayPal Wallet & Pay Later)', 'caretochina-medical');
    }

    public function is_available(): bool {
        return !empty($this->client_id) && !empty($this->client_secret);
    }

    public function get_client_id(): string {
        return $this->client_id;
    }

    /**
     * Get OAuth2 Access Token from PayPal API
     */
    private function get_access_token() {
        if (!$this->is_available()) {
            return false;
        }

        $transient_key = 'ctc_paypal_token_' . md5($this->client_id);
        $cached_token = get_transient($transient_key);
        if ($cached_token) {
            return $cached_token;
        }

        $response = wp_remote_post($this->api_url . '/v1/oauth2/token', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->client_id . ':' . $this->client_secret),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body'    => 'grant_type=client_credentials',
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            error_log('PayPal OAuth token error: ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['access_token'])) {
            return false;
        }

        $expires_in = isset($body['expires_in']) ? intval($body['expires_in']) - 60 : 3500;
        set_transient($transient_key, $body['access_token'], max(60, $expires_in));

        return $body['access_token'];
    }

    /**
     * Create PayPal Checkout Order
     */
    public function create_payment_intent(int $booking_id, float $amount, string $currency): array {
        $token = $this->get_access_token();
        if (!$token) {
            return [
                'success' => false,
                'message' => __('PayPal payment gateway is not properly configured.', 'caretochina-medical'),
            ];
        }

        $formatted_amount = number_format($amount, 2, '.', '');

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => 'ctc_booking_' . $booking_id,
                    'description'  => sprintf(__('CareToChina Medical Booking #%d', 'caretochina-medical'), $booking_id),
                    'custom_id'    => (string)$booking_id,
                    'amount'       => [
                        'currency_code' => strtoupper($currency),
                        'value'         => $formatted_amount,
                    ],
                ]
            ],
            'application_context' => [
                'brand_name'          => 'CareToChina Medical',
                'landing_page'        => 'NO_PREFERENCE',
                'user_action'         => 'PAY_NOW',
                'shipping_preference' => 'NO_SHIPPING',
            ]
        ];

        $response = wp_remote_post($this->api_url . '/v2/checkout/orders', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => json_encode($payload),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            error_log('PayPal Order Creation Error: ' . $response->get_error_message());
            return [
                'success' => false,
                'message' => __('Unable to initialize PayPal checkout. Please try again.', 'caretochina-medical'),
            ];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 201 || empty($body['id'])) {
            error_log('PayPal Order Creation Failed: ' . print_r($body, true));
            return [
                'success' => false,
                'message' => __('Payment service unavailable. Please try again or choose another gateway.', 'caretochina-medical'),
            ];
        }

        return [
            'success'   => true,
            'order_id'  => $body['id'],
            'client_id' => $this->client_id,
        ];
    }

    /**
     * Process PayPal Refund via API
     */
    public function process_refund(int $booking_id, int $wc_order_id, float $amount, string $reason = '') {
        $token = $this->get_access_token();
        if (!$token) {
            return new WP_Error('paypal_token_error', __('Unable to authenticate with PayPal.', 'caretochina-medical'));
        }

        $order = wc_get_order($wc_order_id);
        if (!$order) {
            return new WP_Error('order_not_found', __('WooCommerce order not found.', 'caretochina-medical'));
        }

        $capture_id = $order->get_meta('_paypal_capture_id');
        if (empty($capture_id)) {
            $capture_id = $order->get_transaction_id();
        }

        if (empty($capture_id)) {
            return new WP_Error('missing_capture_id', __('No PayPal transaction/capture ID recorded for this order.', 'caretochina-medical'));
        }

        $currency = $order->get_currency();
        $formatted_amount = number_format($amount, 2, '.', '');

        $payload = [
            'amount' => [
                'value'         => $formatted_amount,
                'currency_code' => strtoupper($currency),
            ],
            'note_to_payer' => sanitize_text_field($reason ?: __('Refund for CareToChina Medical Service', 'caretochina-medical')),
        ];

        $response = wp_remote_post($this->api_url . '/v2/payments/captures/' . urlencode($capture_id) . '/refund', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => json_encode($payload),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 201 || empty($body['id'])) {
            $msg = isset($body['message']) ? $body['message'] : 'PayPal refund failed';
            return new WP_Error('paypal_refund_error', $msg);
        }

        return [
            'success'   => true,
            'refund_id' => $body['id'],
            'amount'    => $amount,
        ];
    }

    /**
     * Handle PayPal Webhooks with API Signature Verification
     */
    public function handle_webhook(WP_REST_Request $request): WP_REST_Response {
        $headers = $request->get_headers();
        $body_raw = $request->get_body();
        $event = json_decode($body_raw, true);

        if (!$event || empty($event['id']) || empty($event['event_type'])) {
            return new WP_REST_Response(['error' => 'Invalid PayPal event payload'], 400);
        }

        // Verify webhook signature with PayPal API if webhook ID is configured
        if (!empty($this->webhook_id)) {
            $token = $this->get_access_token();
            if ($token) {
                $verified = $this->verify_paypal_webhook_signature($request, $token);
                if (!$verified) {
                    error_log('PayPal Webhook Signature Verification Failed.');
                    return new WP_REST_Response(['error' => 'Webhook signature verification failed'], 400);
                }
            }
        }

        $event_id = $event['id'];
        $event_type = $event['event_type'];

        $processed = CareToChina_Payment_Manager::instance()->process_webhook_event(
            'paypal',
            $event_id,
            $event_type,
            $event
        );

        return new WP_REST_Response(['status' => 'success', 'processed' => $processed], 200);
    }

    /**
     * Verify PayPal webhook signature via /v1/notifications/verify-webhook-signature
     */
    private function verify_paypal_webhook_signature(WP_REST_Request $request, $token) {
        $transmission_id   = $request->get_header('paypal-transmission-id');
        $transmission_time = $request->get_header('paypal-transmission-time');
        $cert_url          = $request->get_header('paypal-cert-url');
        $auth_algo         = $request->get_header('paypal-auth-algo');
        $transmission_sig  = $request->get_header('paypal-transmission-sig');

        if (!$transmission_id || !$transmission_time || !$cert_url || !$auth_algo || !$transmission_sig) {
            return false;
        }

        $verify_payload = [
            'transmission_id'   => $transmission_id,
            'transmission_time' => $transmission_time,
            'cert_url'          => $cert_url,
            'auth_algo'         => $auth_algo,
            'transmission_sig'  => $transmission_sig,
            'webhook_id'        => $this->webhook_id,
            'webhook_event'     => json_decode($request->get_body(), true),
        ];

        $response = wp_remote_post($this->api_url . '/v1/notifications/verify-webhook-signature', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => json_encode($verify_payload),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return isset($body['verification_status']) && strtoupper($body['verification_status']) === 'SUCCESS';
    }
}
