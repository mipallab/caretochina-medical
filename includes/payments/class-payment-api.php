<?php
if (!defined('ABSPATH')) {
    exit;
}

class CareToChina_Payment_API {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        $namespace = 'caretochina/v1';

        // 1. Create Payment Intent Endpoint
        register_rest_route($namespace, '/create-payment-intent', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_create_payment_intent'],
            'permission_callback' => [$this, 'check_patient_auth'],
            'args'                => [
                'booking_id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && intval($param) > 0;
                    },
                ],
                'gateway' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function($param) {
                        return in_array($param, ['stripe', 'paypal'], true);
                    },
                ],
            ],
        ]);

        // 2. Stripe Webhook Endpoint (POST Only, Public Signature Auth)
        register_rest_route($namespace, '/webhooks/stripe', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_stripe_webhook'],
            'permission_callback' => '__return_true', // HMAC Signature verified in handler
        ]);

        // 3. PayPal Webhook Endpoint (POST Only, Public Signature Auth)
        register_rest_route($namespace, '/webhooks/paypal', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_paypal_webhook'],
            'permission_callback' => '__return_true', // PayPal API Signature verified in handler
        ]);
    }

    /**
     * Auth & Nonce Permission Callback for Patient Actions
     */
    public function check_patient_auth(WP_REST_Request $request) {
        if (!is_user_logged_in()) {
            return new WP_Error('rest_forbidden', __('You must be logged in to initialize payment.', 'caretochina-medical'), ['status' => 401]);
        }

        // REST Nonce Verification (X-WP-Nonce header)
        $nonce = $request->get_header('x_wp_nonce');
        if (empty($nonce) || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error('rest_invalid_nonce', __('Invalid security token. Please refresh the page and try again.', 'caretochina-medical'), ['status' => 403]);
        }

        return true;
    }

    /**
     * Handler: POST /caretochina/v1/create-payment-intent
     */
    public function handle_create_payment_intent(WP_REST_Request $request) {
        $booking_id = intval($request->get_param('booking_id'));
        $gateway_id = sanitize_text_field($request->get_param('gateway'));
        $current_user_id = get_current_user_id();

        // Ownership Verification
        $booking = CareToChina_Payment_Manager::instance()->get_booking($booking_id);
        if (!$booking) {
            return new WP_REST_Response(['success' => false, 'message' => __('Booking not found.', 'caretochina-medical')], 404);
        }

        // Check if already paid
        $is_already_paid = in_array(strtolower($booking->status), ['confirmed', 'completed', 'paid']) 
            && (strpos(strtolower($booking->invoice_status), 'paid') !== false || !empty($booking->paid_at));
        if ($is_already_paid) {
            return new WP_REST_Response(['success' => false, 'message' => __('This booking has already been paid and confirmed. Duplicate payment is blocked.', 'caretochina-medical')], 400);
        }

        if (intval($booking->patient_id) !== $current_user_id && !current_user_can('caretochina_manage_bookings')) {
            return new WP_REST_Response(['success' => false, 'message' => __('Unauthorized access to booking payment.', 'caretochina-medical')], 403);
        }

        // Rate Limiting Check (Max 10 requests / 15 minutes per IP/User)
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '0.0.0.0';
        if (!CareToChina_Payment_Security::check_rate_limit($current_user_id, $ip, 10, 900)) {
            return new WP_REST_Response(['success' => false, 'message' => __('Too many payment attempts. Please wait 15 minutes before trying again.', 'caretochina-medical')], 429);
        }

        // Create/retrieve WC_Order for booking
        $order = CareToChina_Payment_Manager::instance()->get_or_create_order_for_booking($booking_id, $gateway_id);
        if (is_wp_error($order)) {
            return new WP_REST_Response(['success' => false, 'message' => $order->get_error_message()], 400);
        }

        $amount = floatval($order->get_total());
        $currency = $order->get_currency();

        // Dispatch to appropriate payment gateway
        if ($gateway_id === 'stripe') {
            $gateway = new CareToChina_Stripe_Gateway();
            $result = $gateway->create_payment_intent($booking_id, $amount, $currency);
            return new WP_REST_Response($result, $result['success'] ? 200 : 400);
        } elseif ($gateway_id === 'paypal') {
            $gateway = new CareToChina_PayPal_Gateway();
            $result = $gateway->create_payment_intent($booking_id, $amount, $currency);
            return new WP_REST_Response($result, $result['success'] ? 200 : 400);
        }

        return new WP_REST_Response(['success' => false, 'message' => __('Unsupported payment gateway selected.', 'caretochina-medical')], 400);
    }

    /**
     * Handler: POST /caretochina/v1/webhooks/stripe
     */
    public function handle_stripe_webhook(WP_REST_Request $request) {
        $content_type = $request->get_header('content-type');
        if ($content_type && strpos($content_type, 'application/json') === false) {
            return new WP_REST_Response(['error' => 'Invalid Content-Type header'], 400);
        }

        $gateway = new CareToChina_Stripe_Gateway();
        return $gateway->handle_webhook($request);
    }

    /**
     * Handler: POST /caretochina/v1/webhooks/paypal
     */
    public function handle_paypal_webhook(WP_REST_Request $request) {
        $content_type = $request->get_header('content-type');
        if ($content_type && strpos($content_type, 'application/json') === false) {
            return new WP_REST_Response(['error' => 'Invalid Content-Type header'], 400);
        }

        $gateway = new CareToChina_PayPal_Gateway();
        return $gateway->handle_webhook($request);
    }
}
