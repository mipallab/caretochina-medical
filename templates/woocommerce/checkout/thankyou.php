<?php
/**
 * CareToChina Medical Thank You / Order Received Template Override
 * Branded Medical Payment Confirmation with direct Patient Dashboard Action & Case Details.
 */

if (!defined('ABSPATH')) {
    exit;
}

$booking_id = 0;
$booking = null;

if ($order) {
    $booking_id = intval($order->get_meta('_caretochina_booking_id'));
    if (!$booking_id && class_exists('CareToChina_Payment_Manager')) {
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $booking_id = intval($wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}caretochina_bookings WHERE wc_order_id = %d", $order->get_id())));
    }
}

if ($booking_id && class_exists('CareToChina_Payment_Manager')) {
    $booking = CareToChina_Payment_Manager::instance()->get_booking($booking_id);
}

$dash_url = class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_url('patient_dashboard') : home_url('/patient-dashboard/');
$chat_url = ($booking && !empty($booking->guest_token)) ? home_url('/guest-chat/?token=' . $booking->guest_token) : home_url('/patient-dashboard/?tab=messages');

// Concierge channels from hospital settings
$settings     = class_exists('CareToChina_Hospital_Settings') ? CareToChina_Hospital_Settings::get_settings() : [];
$whatsapp_num = $settings['whatsapp_number'] ?? '';
$phone_num    = $settings['phone_number'] ?? '';
$email_addr   = $settings['email'] ?? '';
?>

<div class="ctc-thankyou-wrapper">

    <?php if ($order) : ?>

        <?php if ($order->has_status('failed')) : ?>

            <!-- Payment Failed Card -->
            <div class="ctc-thankyou-card failed">
                <div class="ctc-ty-icon-box failed">
                    <i class="fas fa-times-circle"></i>
                </div>
                <h1 class="ctc-ty-title"><?php esc_html_e('Payment Unsuccessful', 'caretochina-medical'); ?></h1>
                <p class="ctc-ty-subtitle"><?php esc_html_e('Unfortunately, your payment could not be processed at this time. Your medical consultation record is safely preserved.', 'caretochina-medical'); ?></p>

                <div class="ctc-ty-actions">
                    <a href="<?php echo esc_url($order->get_checkout_payment_url()); ?>" class="ctc-ty-btn-primary">
                        <i class="fas fa-redo-alt"></i> <?php esc_html_e('Retry Payment', 'caretochina-medical'); ?>
                    </a>
                    <a href="<?php echo esc_url($chat_url); ?>" class="ctc-ty-btn-secondary">
                        <i class="fas fa-comments"></i> <?php esc_html_e('Ask Medical Coordinator', 'caretochina-medical'); ?>
                    </a>
                </div>
            </div>

        <?php else : ?>

            <!-- Payment Confirmed Card -->
            <div class="ctc-thankyou-card success">
                
                <div class="ctc-ty-icon-box success">
                    <i class="fas fa-check-circle"></i>
                </div>

                <div class="ctc-ty-badge-confirmed">
                    <i class="fas fa-certificate"></i> <?php esc_html_e('Official Medical Booking & Payment Confirmed', 'caretochina-medical'); ?>
                </div>

                <h1 class="ctc-ty-title"><?php esc_html_e('Thank You! Your Payment Was Received', 'caretochina-medical'); ?></h1>
                <p class="ctc-ty-subtitle"><?php esc_html_e('Your medical case has been locked and our China Hospital Advisory Team has commenced clinic admission arrangements.', 'caretochina-medical'); ?></p>

                <!-- Case & Payment Breakdown Card -->
                <div class="ctc-ty-details-grid">
                    
                    <div class="ctc-ty-detail-item">
                        <span class="lbl"><?php esc_html_e('Booking Reference:', 'caretochina-medical'); ?></span>
                        <strong class="val code">#<?php echo esc_html($booking ? $booking->booking_code : ('ORD-' . $order->get_id())); ?></strong>
                    </div>

                    <div class="ctc-ty-detail-item">
                        <span class="lbl"><?php esc_html_e('Amount Received:', 'caretochina-medical'); ?></span>
                        <strong class="val amount"><?php echo wp_kses_post($order->get_formatted_order_total()); ?></strong>
                    </div>

                    <div class="ctc-ty-detail-item">
                        <span class="lbl"><?php esc_html_e('Payment Gateway:', 'caretochina-medical'); ?></span>
                        <strong class="val"><?php echo esc_html($order->get_payment_method_title() ?: __('Online Gateway', 'caretochina-medical')); ?></strong>
                    </div>

                    <div class="ctc-ty-detail-item">
                        <span class="lbl"><?php esc_html_e('Confirmed Date:', 'caretochina-medical'); ?></span>
                        <strong class="val"><?php echo esc_html(wc_format_datetime($order->get_date_created())); ?></strong>
                    </div>

                    <?php if ($booking && !empty($booking->specialty)) : ?>
                        <div class="ctc-ty-detail-item" style="grid-column: 1 / -1;">
                            <span class="lbl"><?php esc_html_e('Specialized Treatment:', 'caretochina-medical'); ?></span>
                            <strong class="val"><?php echo esc_html($booking->specialty); ?><?php echo !empty($booking->hospital_name) ? ' — ' . esc_html($booking->hospital_name) : ''; ?></strong>
                        </div>
                    <?php endif; ?>

                </div>

                <!-- Primary Call to Action: Go to Patient Dashboard -->
                <div class="ctc-ty-primary-cta-box">
                    <a href="<?php echo esc_url($dash_url); ?>" class="ctc-ty-btn-primary ctc-ty-btn-lg">
                        <i class="fas fa-columns"></i>
                        <span><?php esc_html_e('Go to Patient Dashboard', 'caretochina-medical'); ?></span>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                    <p class="ctc-ty-cta-hint"><?php esc_html_e('Access your confirmed booking, download official invoices, track treatment roadmap, and upload medical records.', 'caretochina-medical'); ?></p>
                </div>

                <!-- Secondary Actions -->
                <div class="ctc-ty-actions secondary">
                    <a href="<?php echo esc_url($chat_url); ?>" class="ctc-ty-btn-secondary">
                        <i class="fas fa-comment-medical"></i> <?php esc_html_e('Open Live Consultation Chat', 'caretochina-medical'); ?>
                    </a>
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="ctc-ty-btn-secondary">
                        <i class="fas fa-home"></i> <?php esc_html_e('Return to Home', 'caretochina-medical'); ?>
                    </a>
                </div>

                <!-- 24/7 Concierge Support Footer -->
                <div class="ctc-ty-concierge-box">
                    <div class="ctc-ty-concierge-head">
                        <i class="fas fa-headset" style="color:#0f766e; font-size:20px;"></i>
                        <div>
                            <strong><?php esc_html_e('24/7 International Patient Concierge Desk', 'caretochina-medical'); ?></strong>
                            <p><?php esc_html_e('Our coordinators are standing by to assist with visa invitation letters, translation, and hospital check-in.', 'caretochina-medical'); ?></p>
                        </div>
                    </div>

                    <div class="ctc-ty-channels">
                        <?php if (!empty($whatsapp_num)) : 
                            $clean_wa = preg_replace('/[^0-9]/', '', $whatsapp_num);
                            $wa_msg = urlencode(sprintf('Hello CareToChina, I have confirmed payment for booking #%s', $booking ? $booking->booking_code : $order->get_id()));
                            ?>
                            <a href="https://api.whatsapp.com/send?phone=<?php echo esc_attr($clean_wa); ?>&text=<?php echo esc_attr($wa_msg); ?>" target="_blank" rel="noopener noreferrer" class="ctc-channel-link wa">
                                <i class="fab fa-whatsapp"></i> <?php esc_html_e('WhatsApp', 'caretochina-medical'); ?>
                            </a>
                        <?php endif; ?>

                        <?php if (!empty($phone_num)) : ?>
                            <a href="tel:<?php echo esc_attr(preg_replace('/[^+0-9]/', '', $phone_num)); ?>" class="ctc-channel-link tel">
                                <i class="fas fa-phone-alt"></i> <?php echo esc_html($phone_num); ?>
                            </a>
                        <?php endif; ?>

                        <?php if (!empty($email_addr)) : ?>
                            <a href="mailto:<?php echo esc_attr($email_addr); ?>" class="ctc-channel-link mail">
                                <i class="fas fa-envelope"></i> <?php esc_html_e('Direct Email', 'caretochina-medical'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

        <?php endif; ?>

    <?php else : ?>

        <div class="ctc-thankyou-card success">
            <h1 class="ctc-ty-title"><?php esc_html_e('Thank you for choosing CareToChina Medical.', 'caretochina-medical'); ?></h1>
            <p class="ctc-ty-subtitle"><?php esc_html_e('Your appointment details have been securely registered with our clinical concierge team.', 'caretochina-medical'); ?></p>
            <div class="ctc-ty-primary-cta-box">
                <a href="<?php echo esc_url($dash_url); ?>" class="ctc-ty-btn-primary">
                    <i class="fas fa-columns"></i> <?php esc_html_e('Go to Patient Dashboard', 'caretochina-medical'); ?>
                </a>
            </div>
        </div>

    <?php endif; ?>

</div>

<style>
    .ctc-thankyou-wrapper {
        max-width: 800px;
        margin: 50px auto 90px auto;
        padding: 0 20px;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        box-sizing: border-box;
    }
    .ctc-thankyou-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        padding: 44px 36px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        text-align: center;
        box-sizing: border-box;
    }
    .ctc-ty-icon-box {
        width: 76px;
        height: 76px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px auto;
        font-size: 40px;
    }
    .ctc-ty-icon-box.success {
        background: #ccfbf1;
        color: #0f766e;
    }
    .ctc-ty-icon-box.failed {
        background: #fee2e2;
        color: #dc2626;
    }
    .ctc-ty-badge-confirmed {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        background: #ecfdf5;
        color: #065f46;
        border: 1px solid #a7f3d0;
        padding: 6px 16px;
        border-radius: 50px;
        font-size: 13px;
        font-weight: 700;
        letter-spacing: 0.2px;
        margin-bottom: 14px;
    }
    .ctc-ty-title {
        font-family: 'Manrope', sans-serif;
        font-size: 2rem;
        font-weight: 800;
        color: #0f172a;
        margin: 0 0 10px 0;
        line-height: 1.25;
    }
    .ctc-ty-subtitle {
        color: #64748b;
        font-size: 1.05rem;
        line-height: 1.6;
        max-width: 620px;
        margin: 0 auto 30px auto;
    }
    .ctc-ty-details-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 22px;
        margin-bottom: 32px;
        text-align: left;
    }
    .ctc-ty-detail-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .ctc-ty-detail-item .lbl {
        font-size: 11.5px;
        color: #64748b;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.3px;
    }
    .ctc-ty-detail-item .val {
        font-size: 14.5px;
        color: #0f172a;
        font-weight: 700;
    }
    .ctc-ty-detail-item .val.code {
        color: #0f766e;
        font-family: monospace;
        font-size: 15px;
    }
    .ctc-ty-detail-item .val.amount {
        color: #0f766e;
        font-size: 1.2rem;
    }
    .ctc-ty-primary-cta-box {
        margin-bottom: 24px;
        padding: 20px;
        background: #f0fdfa;
        border: 1.5px solid #0f766e33;
        border-radius: 16px;
    }
    .ctc-ty-btn-primary {
        background: #0f766e;
        color: #ffffff !important;
        font-family: 'Manrope', sans-serif;
        font-weight: 700;
        font-size: 1.05rem;
        padding: 16px 36px;
        border-radius: 50px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        transition: all 0.25s ease;
        box-shadow: 0 6px 20px rgba(15, 118, 110, 0.25);
    }
    .ctc-ty-btn-primary:hover {
        background: #115e59;
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(15, 118, 110, 0.35);
    }
    .ctc-ty-cta-hint {
        margin: 10px 0 0 0;
        font-size: 12.5px;
        color: #0f766e;
    }
    .ctc-ty-actions.secondary {
        display: flex;
        justify-content: center;
        gap: 14px;
        margin-bottom: 36px;
        flex-wrap: wrap;
    }
    .ctc-ty-btn-secondary {
        background: #ffffff;
        color: #334155 !important;
        border: 1px solid #cbd5e1;
        font-family: 'Manrope', sans-serif;
        font-weight: 600;
        font-size: 0.92rem;
        padding: 11px 22px;
        border-radius: 50px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
    }
    .ctc-ty-btn-secondary:hover {
        background: #f1f5f9;
        border-color: #94a3b8;
        color: #0f172a !important;
    }
    .ctc-ty-concierge-box {
        background: #f8fafc;
        border-radius: 14px;
        padding: 22px 24px;
        text-align: left;
        border: 1px solid #e2e8f0;
    }
    .ctc-ty-concierge-head {
        display: flex;
        gap: 14px;
        align-items: flex-start;
        margin-bottom: 16px;
    }
    .ctc-ty-concierge-head strong {
        color: #0f172a;
        font-size: 14px;
        display: block;
        margin-bottom: 3px;
    }
    .ctc-ty-concierge-head p {
        margin: 0;
        font-size: 12.5px;
        color: #64748b;
        line-height: 1.4;
    }
    .ctc-ty-channels {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .ctc-channel-link {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 12.5px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .ctc-channel-link.wa {
        background: #ecfdf5;
        color: #047857;
        border: 1px solid #a7f3d0;
    }
    .ctc-channel-link.wa:hover {
        background: #25D366;
        color: #ffffff;
        border-color: #25D366;
    }
    .ctc-channel-link.tel {
        background: #f0f9ff;
        color: #0369a1;
        border: 1px solid #bae6fd;
    }
    .ctc-channel-link.mail {
        background: #fdf4ff;
        color: #7e22ce;
        border: 1px solid #f5d0fe;
    }

    /* Dark Mode */
    html.dark-theme .ctc-thankyou-card, body.dark-theme .ctc-thankyou-card {
        background: #172033 !important;
        border-color: #28354e !important;
    }
    html.dark-theme .ctc-ty-title, body.dark-theme .ctc-ty-title,
    html.dark-theme .ctc-ty-concierge-head strong, body.dark-theme .ctc-ty-concierge-head strong {
        color: #f8fafc !important;
    }
    html.dark-theme .ctc-ty-subtitle, body.dark-theme .ctc-ty-subtitle,
    html.dark-theme .ctc-ty-concierge-head p, body.dark-theme .ctc-ty-concierge-head p {
        color: #94a3b8 !important;
    }
    html.dark-theme .ctc-ty-details-grid, body.dark-theme .ctc-ty-details-grid {
        background: #101726 !important;
        border-color: #28354e !important;
    }
    html.dark-theme .ctc-ty-detail-item .val, body.dark-theme .ctc-ty-detail-item .val {
        color: #f8fafc !important;
    }
    html.dark-theme .ctc-ty-btn-secondary, body.dark-theme .ctc-ty-btn-secondary {
        background: #101726 !important;
        border-color: #28354e !important;
        color: #cbd5e1 !important;
    }
    html.dark-theme .ctc-ty-concierge-box, body.dark-theme .ctc-ty-concierge-box {
        background: #101726 !important;
        border-color: #28354e !important;
    }

    @media (max-width: 600px) {
        .ctc-thankyou-card {
            padding: 30px 18px;
        }
        .ctc-ty-details-grid {
            grid-template-columns: 1fr;
        }
        .ctc-ty-title {
            font-size: 1.55rem;
        }
        .ctc-ty-btn-primary {
            width: 100%;
            box-sizing: border-box;
        }
    }
</style>
