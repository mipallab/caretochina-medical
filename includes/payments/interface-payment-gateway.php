<?php
if (!defined('ABSPATH')) {
    exit;
}

interface CareToChina_Payment_Gateway_Interface {
    /**
     * Unique identifier for the payment gateway ('stripe', 'paypal', etc.)
     */
    public function get_id(): string;

    /**
     * Human readable title
     */
    public function get_title(): string;

    /**
     * Check if gateway is configured and active
     */
    public function is_available(): bool;

    /**
     * Create client intent/order payload for client-side checkout
     */
    public function create_payment_intent(int $booking_id, float $amount, string $currency): array;

    /**
     * Execute server-side refund via gateway API
     */
    public function process_refund(int $booking_id, int $wc_order_id, float $amount, string $reason = '');

    /**
     * Handle incoming verified server-to-server webhook
     */
    public function handle_webhook(WP_REST_Request $request): WP_REST_Response;
}
