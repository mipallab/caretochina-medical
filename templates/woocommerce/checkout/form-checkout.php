<?php
/**
 * CareToChina Medical Checkout Template Override
 * Custom branded medical checkout experience with case summary and payment gateway selectors.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Fetch booking context if available from session or order
$booking_id = 0;
$booking = null;

if (WC()->cart && !WC()->cart->is_empty()) {
    foreach (WC()->cart->get_cart() as $cart_item) {
        if (!empty($cart_item['caretochina_booking_id'])) {
            $booking_id = intval($cart_item['caretochina_booking_id']);
            break;
        }
    }
}

if (!$booking_id && isset($_GET['booking_id'])) {
    $booking_id = intval($_GET['booking_id']);
}

if ($booking_id && class_exists('CareToChina_Payment_Manager')) {
    $booking = CareToChina_Payment_Manager::instance()->get_booking($booking_id);
}

do_action('woocommerce_before_checkout_form', $checkout);
?>

<div class="ctc-medical-checkout-wrap">
    
    <!-- Secure Header Banner -->
    <div class="ctc-checkout-secure-header">
        <div class="ctc-secure-badge">
            <i class="fas fa-shield-alt"></i>
            <span><?php esc_html_e('256-bit SSL Encrypted Medical Payment Portal', 'caretochina-medical'); ?></span>
        </div>
        <div class="ctc-compliance-badge">
            <i class="fas fa-lock"></i>
            <span><?php esc_html_e('HIPAA & GDPR Privacy Compliant', 'caretochina-medical'); ?></span>
        </div>
    </div>

    <form name="checkout" method="post" class="checkout woocommerce-checkout ctc-checkout-form-grid" action="<?php echo esc_url(wc_get_checkout_url()); ?>" enctype="multipart/form-data">

        <!-- Left Column: Patient Case & Treatment Details -->
        <div class="ctc-checkout-col-summary">
            
            <div class="ctc-checkout-card ctc-case-card">
                <h3 class="ctc-card-title">
                    <i class="fas fa-file-medical-alt" style="color:#0f766e;"></i>
                    <?php esc_html_e('Medical Booking Summary', 'caretochina-medical'); ?>
                </h3>

                <?php if ($booking) : ?>
                    <div class="ctc-case-badge-row">
                        <span class="ctc-code-badge">#<?php echo esc_html($booking->booking_code); ?></span>
                        <span class="ctc-status-badge pending"><?php esc_html_e('Awaiting Payment', 'caretochina-medical'); ?></span>
                    </div>

                    <ul class="ctc-case-detail-list">
                        <li>
                            <span class="lbl"><?php esc_html_e('Patient Name:', 'caretochina-medical'); ?></span>
                            <span class="val"><?php echo esc_html($booking->full_name); ?></span>
                        </li>
                        <li>
                            <span class="lbl"><?php esc_html_e('Specialty / Treatment:', 'caretochina-medical'); ?></span>
                            <span class="val"><?php echo esc_html(!empty($booking->specialty) ? $booking->specialty : __('Medical Concierge', 'caretochina-medical')); ?></span>
                        </li>
                        <?php if (!empty($booking->hospital_name)) : ?>
                            <li>
                                <span class="lbl"><?php esc_html_e('Target Hospital:', 'caretochina-medical'); ?></span>
                                <span class="val"><?php echo esc_html($booking->hospital_name); ?></span>
                            </li>
                        <?php endif; ?>
                    </ul>
                <?php else : ?>
                    <p class="ctc-case-notice"><?php esc_html_e('Secure checkout for your specialized medical treatment booking and coordinator services.', 'caretochina-medical'); ?></p>
                <?php endif; ?>

                <div class="ctc-checkout-divider"></div>

                <!-- Order Review Table -->
                <div id="order_review_summary">
                    <h4 class="ctc-sub-title"><?php esc_html_e('Payment Breakdown', 'caretochina-medical'); ?></h4>
                    <?php do_action('woocommerce_checkout_before_order_review'); ?>
                    <div id="order_review" class="woocommerce-checkout-review-order">
                        <?php do_action('woocommerce_checkout_order_review'); ?>
                    </div>
                    <?php do_action('woocommerce_checkout_after_order_review'); ?>
                </div>

                <!-- Coordinator Assistance Callout -->
                <div class="ctc-checkout-concierge-tip">
                    <i class="fas fa-headset"></i>
                    <div>
                        <strong><?php esc_html_e('Need Assistance with Payment?', 'caretochina-medical'); ?></strong>
                        <p><?php esc_html_e('Our 24/7 International Patient Desk is available to help verify currency conversion or alternative payment methods.', 'caretochina-medical'); ?></p>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Column: Billing Information & Payment Gateways -->
        <div class="ctc-checkout-col-billing">
            
            <?php if ($checkout->get_checkout_fields()) : ?>
                <div class="ctc-checkout-card">
                    <h3 class="ctc-card-title">
                        <i class="fas fa-user-check" style="color:#0f766e;"></i>
                        <?php esc_html_e('Patient & Billing Information', 'caretochina-medical'); ?>
                    </h3>

                    <?php do_action('woocommerce_checkout_before_customer_details'); ?>

                    <div class="col2-set" id="customer_details">
                        <div class="ctc-billing-fields-wrap">
                            <?php do_action('woocommerce_checkout_billing'); ?>
                        </div>
                        <div class="ctc-shipping-fields-wrap" style="display:none;">
                            <?php do_action('woocommerce_checkout_shipping'); ?>
                        </div>
                    </div>

                    <?php do_action('woocommerce_checkout_after_customer_details'); ?>
                </div>
            <?php endif; ?>

        </div>

    </form>

</div>

<style>
    .ctc-medical-checkout-wrap {
        max-width: 1140px;
        margin: 40px auto 80px auto;
        padding: 0 20px;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        box-sizing: border-box;
    }
    .ctc-checkout-secure-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: linear-gradient(135deg, #0f172a 0%, #134e4a 100%);
        color: #ffffff;
        padding: 16px 24px;
        border-radius: 12px;
        margin-bottom: 30px;
        font-size: 13px;
        font-weight: 600;
        gap: 12px;
        flex-wrap: wrap;
    }
    .ctc-secure-badge, .ctc-compliance-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #2dd4bf;
    }
    .ctc-compliance-badge {
        color: #e2e8f0;
    }
    .ctc-checkout-form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        align-items: start;
    }
    .ctc-checkout-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 28px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
        box-sizing: border-box;
    }
    .ctc-card-title {
        font-family: 'Manrope', sans-serif;
        font-size: 1.25rem;
        font-weight: 700;
        color: #0f172a;
        margin: 0 0 18px 0;
        display: flex;
        align-items: center;
        gap: 10px;
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 14px;
    }
    .ctc-case-badge-row {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 16px;
    }
    .ctc-code-badge {
        background: #f1f5f9;
        color: #0f172a;
        font-family: monospace;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 13px;
    }
    .ctc-status-badge.pending {
        background: #fef3c7;
        color: #92400e;
        font-weight: 600;
        font-size: 12px;
        padding: 4px 10px;
        border-radius: 20px;
    }
    .ctc-case-detail-list {
        list-style: none;
        padding: 0;
        margin: 0 0 20px 0;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .ctc-case-detail-list li {
        display: flex;
        justify-content: space-between;
        font-size: 13.5px;
    }
    .ctc-case-detail-list .lbl {
        color: #64748b;
    }
    .ctc-case-detail-list .val {
        color: #0f172a;
        font-weight: 600;
    }
    .ctc-checkout-divider {
        height: 1px;
        background: #e2e8f0;
        margin: 20px 0;
    }
    .ctc-sub-title {
        font-family: 'Manrope', sans-serif;
        font-size: 1.05rem;
        font-weight: 700;
        color: #0f172a;
        margin: 0 0 14px 0;
    }
    .ctc-checkout-concierge-tip {
        margin-top: 24px;
        background: #f0fdfa;
        border: 1px solid #ccfbf1;
        border-radius: 10px;
        padding: 14px 16px;
        display: flex;
        gap: 12px;
        align-items: flex-start;
        font-size: 12.5px;
        color: #0f766e;
    }
    .ctc-checkout-concierge-tip i {
        font-size: 18px;
        margin-top: 2px;
    }
    .ctc-checkout-concierge-tip p {
        margin: 3px 0 0 0;
        color: #115e59;
        line-height: 1.4;
    }

    /* Style WooCommerce Native Form Inputs */
    .ctc-medical-checkout-wrap input[type="text"],
    .ctc-medical-checkout-wrap input[type="email"],
    .ctc-medical-checkout-wrap input[type="tel"],
    .ctc-medical-checkout-wrap select {
        border: 1px solid #cbd5e1 !important;
        border-radius: 8px !important;
        padding: 10px 14px !important;
        font-size: 14px !important;
        width: 100% !important;
        box-sizing: border-box !important;
        background: #ffffff !important;
    }
    .ctc-medical-checkout-wrap input:focus,
    .ctc-medical-checkout-wrap select:focus {
        border-color: #0f766e !important;
        outline: none !important;
        box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.15) !important;
    }
    .ctc-medical-checkout-wrap #place_order {
        background: #0f766e !important;
        color: #ffffff !important;
        font-family: 'Manrope', sans-serif !important;
        font-weight: 700 !important;
        font-size: 1rem !important;
        padding: 14px 28px !important;
        border-radius: 50px !important;
        width: 100% !important;
        border: none !important;
        cursor: pointer !important;
        transition: all 0.25s ease !important;
        margin-top: 20px !important;
    }
    .ctc-medical-checkout-wrap #place_order:hover {
        background: #115e59 !important;
        box-shadow: 0 6px 18px rgba(15, 118, 110, 0.3) !important;
        transform: translateY(-2px);
    }

    /* Dark Mode */
    html.dark-theme .ctc-checkout-card, body.dark-theme .ctc-checkout-card {
        background: #172033 !important;
        border-color: #28354e !important;
    }
    html.dark-theme .ctc-card-title, body.dark-theme .ctc-card-title,
    html.dark-theme .ctc-sub-title, body.dark-theme .ctc-sub-title {
        color: #f8fafc !important;
        border-color: #28354e !important;
    }
    html.dark-theme .ctc-case-detail-list .val, body.dark-theme .ctc-case-detail-list .val {
        color: #f8fafc !important;
    }
    html.dark-theme .ctc-case-detail-list .lbl, body.dark-theme .ctc-case-detail-list .lbl {
        color: #94a3b8 !important;
    }
    html.dark-theme .ctc-code-badge, body.dark-theme .ctc-code-badge {
        background: #0f172a !important;
        color: #2dd4bf !important;
    }
    html.dark-theme .ctc-medical-checkout-wrap input, body.dark-theme .ctc-medical-checkout-wrap input,
    html.dark-theme .ctc-medical-checkout-wrap select, body.dark-theme .ctc-medical-checkout-wrap select {
        background: #101726 !important;
        border-color: #28354e !important;
        color: #f8fafc !important;
    }

    @media (max-width: 900px) {
        .ctc-checkout-form-grid {
            grid-template-columns: 1fr;
        }
        .ctc-medical-checkout-wrap {
            margin: 20px auto 50px auto;
            padding: 0 14px;
        }
    }
</style>
<?php do_action('woocommerce_after_checkout_form', $checkout); ?>
