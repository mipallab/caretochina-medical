<?php
/**
 * CareToChina Email Templates & Branding Engine
 * Comprehensive branded HTML email templating system with admin editor, event mapping, live preview, and test sender.
 */

if (!defined('ABSPATH')) {
    exit;
}

class CareToChina_Email_Templates {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_menu'], 30);
        add_action('admin_init', [$this, 'handle_save_settings']);
        add_action('wp_ajax_ctc_send_test_email', [$this, 'handle_ajax_send_test_email']);
        add_action('wp_ajax_ctc_preview_email_template', [$this, 'handle_ajax_preview_template']);
    }

    public function register_admin_menu() {
        add_submenu_page(
            'caretochina-staff-desk',
            __('Email Templates & Branding', 'caretochina-medical'),
            __('Email Templates', 'caretochina-medical'),
            'manage_options',
            'caretochina-email-templates',
            [$this, 'render_admin_page']
        );
    }

    /**
     * Get default branding options
     */
    public static function get_branding() {
        $logo = get_option('ctc_brand_logo_url', '');
        if (empty($logo)) {
            $logo = get_option('ctc_email_logo_url', '');
        }

        return [
            'brand_color'   => get_option('ctc_email_brand_color', '#0F766E'),
            'accent_color'  => get_option('ctc_email_accent_color', '#14B8A6'),
            'bg_color'      => get_option('ctc_email_bg_color', '#F8FAFC'),
            'card_bg'       => get_option('ctc_email_card_bg', '#FFFFFF'),
            'logo_url'      => $logo,
            'from_name'     => get_option('ctc_email_from_name', get_bloginfo('name') . ' Medical'),
            'from_email'    => get_option('ctc_email_from_email', get_option('admin_email')),
            'footer_text'   => get_option('ctc_email_footer_text', sprintf(__('© %s CareToChina Medical Travel Services. All rights reserved. Confidential & HIPAA/GDPR compliant communication.', 'caretochina-medical'), date('Y'))),
        ];
    }

    /**
     * Get default template definitions
     */
    public static function get_default_templates() {
        return [
            'guest_booking' => [
                'id'          => 'guest_booking',
                'name'        => __('Guest Booking Confirmation & Live Chat', 'caretochina-medical'),
                'subject'     => __('[CareToChina] Medical Consultation Request Confirmed: #{booking_code}', 'caretochina-medical'),
                'heading'     => __('Medical Consultation Request Confirmed', 'caretochina-medical'),
                'preheader'   => __('Your medical consultation case has been assigned to our medical team.', 'caretochina-medical'),
                'content'     => "<p>Dear {patient_name},</p>\n<p>Thank you for submitting your medical consultation inquiry with <strong>CareToChina Medical</strong>. Your request has been securely received and assigned to our International Medical Advisory Team.</p>\n<p>Our coordinators are reviewing your symptoms and requirements for <strong>{specialty}</strong>. You can access your dedicated, private consultation room right now using the button below:</p>\n{case_summary_table}\n<p style=\"margin-top:16px;\"><em>Note: Save this email to easily return to your consultation thread anytime.</em></p>",
                'btn_text'    => __('Access Live Chat Consultation →', 'caretochina-medical'),
                'btn_url'     => '{chat_url}',
            ],
            'patient_booking' => [
                'id'          => 'patient_booking',
                'name'        => __('Registered Patient Booking Confirmed', 'caretochina-medical'),
                'subject'     => __('[CareToChina] Booking Received for #{booking_code}', 'caretochina-medical'),
                'heading'     => __('Treatment Booking Confirmed', 'caretochina-medical'),
                'preheader'   => __('Your booking has been received and linked to your patient account.', 'caretochina-medical'),
                'content'     => "<p>Dear {patient_name},</p>\n<p>Your medical treatment booking for <strong>{specialty}</strong> has been successfully linked to your patient account.</p>\n{case_summary_table}\n<p style=\"margin-top:16px;\">You can track your treatment timeline, milestones, and communicate with your care coordinator directly from your Patient Dashboard.</p>",
                'btn_text'    => __('Open Patient Dashboard →', 'caretochina-medical'),
                'btn_url'     => '{dashboard_url}',
            ],
            'guest_registered_welcome' => [
                'id'          => 'guest_registered_welcome',
                'name'        => __('Guest to Registered Patient Welcome & Records Linked', 'caretochina-medical'),
                'subject'     => __('[CareToChina] Welcome {patient_name}! Your Patient Account & Medical History Are Active', 'caretochina-medical'),
                'heading'     => __('Welcome to CareToChina Medical', 'caretochina-medical'),
                'preheader'   => __('Your patient account is active and your past medical consultations are linked.', 'caretochina-medical'),
                'content'     => "<p>Dear {patient_name},</p>\n<p>Welcome to <strong>CareToChina Medical</strong>! Your official patient portal account has been successfully created and secured.</p>\n<p>All of your past consultation inquiries, live chat messages, and medical records have been <strong>permanently linked</strong> to your account. You can now track your treatment roadmap, communicate with your medical coordinator, and view official medical invoices directly from your portal.</p>",
                'btn_text'    => __('Open My Patient Dashboard →', 'caretochina-medical'),
                'btn_url'     => '{dashboard_url}',
            ],
            'admin_booking' => [
                'id'          => 'admin_booking',
                'name'        => __('Staff Alert: New Patient Inquiry', 'caretochina-medical'),
                'subject'     => __('[New Inquiry] #{booking_code} - {patient_name} ({specialty})', 'caretochina-medical'),
                'heading'     => __('New Patient Consultation Case Received', 'caretochina-medical'),
                'preheader'   => __('A new patient has submitted a medical inquiry.', 'caretochina-medical'),
                'content'     => "<p>A new medical consultation case has been submitted and is awaiting coordinator review:</p>\n{case_summary_table}\n<p><strong>Patient Details:</strong><br>Email: {patient_email}<br>Phone: {patient_phone}<br>Condition / Notes: {quote_details}</p>",
                'btn_text'    => __('Open Staff Consultation Desk →', 'caretochina-medical'),
                'btn_url'     => '{staff_portal_url}',
            ],
            'status_update' => [
                'id'          => 'status_update',
                'name'        => __('Case Status & Approval Update', 'caretochina-medical'),
                'subject'     => __('[CareToChina] Case Status Update: #{booking_code} - {status}', 'caretochina-medical'),
                'heading'     => __('Medical Case Status Updated', 'caretochina-medical'),
                'preheader'   => __('Your consultation case status has been updated by your care coordinator.', 'caretochina-medical'),
                'content'     => "<p>Dear {patient_name},</p>\n<p>Your medical inquiry case <strong>#{booking_code}</strong> for <strong>{specialty}</strong> has been updated to:</p>\n<div style=\"background:#CCFBF1; color:#0F766E; padding:12px 18px; border-radius:10px; font-weight:bold; font-size:16px; margin:16px 0; text-align:center;\">{status}</div>\n<p>Please log in or visit your live chat consultation thread to review updated hospital itineraries and instructions.</p>",
                'btn_text'    => __('View Case & Live Chat →', 'caretochina-medical'),
                'btn_url'     => '{chat_url}',
            ],
            'payment_request' => [
                'id'          => 'payment_request',
                'name'        => __('Staff Payment Request / Deposit Invoice', 'caretochina-medical'),
                'subject'     => __('[CareToChina] Payment Request: #{request_code} for Case #{booking_code}', 'caretochina-medical'),
                'heading'     => __('Medical Payment Request Issued', 'caretochina-medical'),
                'preheader'   => __('A formal payment request has been prepared for your medical itinerary.', 'caretochina-medical'),
                'content'     => "<p>Dear {patient_name},</p>\n<p>Your Care Coordinator has prepared an authoritative payment request for your treatment plan:</p>\n<div style=\"border:1.5px solid #0F766E; background:#F0FDFA; padding:16px 20px; border-radius:12px; margin:16px 0;\"><h4 style=\"margin:0 0 8px 0; color:#0F766E;\">{custom_title}</h4><p style=\"margin:0 0 10px 0; font-size:13px; color:#475569;\">Reference Code: <strong>#{request_code}</strong> (Case: #{booking_code})</p><div style=\"font-size:22px; font-weight:800; color:#0F766E;\">Total: {amount} {currency}</div></div>\n<p>You can securely complete your payment online using credit card, debit card, or PayPal.</p>",
                'btn_text'    => __('Review & Pay Online →', 'caretochina-medical'),
                'btn_url'     => '{chat_url}',
            ],
            'payment_success' => [
                'id'          => 'payment_success',
                'name'        => __('Payment Confirmation & Receipt', 'caretochina-medical'),
                'subject'     => __('[CareToChina] Payment Confirmed: #{booking_code} - {amount} {currency}', 'caretochina-medical'),
                'heading'     => __('Payment Received Successfully', 'caretochina-medical'),
                'preheader'   => __('Your payment has been processed and your official receipt is ready.', 'caretochina-medical'),
                'content'     => "<p>Dear {patient_name},</p>\n<p>We have successfully received your payment of <strong>{amount} {currency}</strong> via {payment_method} for case <strong>#{booking_code}</strong>.</p>\n{case_summary_table}\n<p style=\"margin-top:16px;\">Your treatment confirmation and hospital itinerary are now locked. Our medical logistics team has commenced final travel and clinic arrangements.</p>",
                'btn_text'    => __('View Invoice & Receipt →', 'caretochina-medical'),
                'btn_url'     => '{dashboard_url}',
            ],
            'chat_message' => [
                'id'          => 'chat_message',
                'name'        => __('New Chat Message from Coordinator', 'caretochina-medical'),
                'subject'     => __('[CareToChina] New Message Regarding Case #{booking_code}', 'caretochina-medical'),
                'heading'     => __('New Message from Your Care Coordinator', 'caretochina-medical'),
                'preheader'   => __('You have received a new consultation message.', 'caretochina-medical'),
                'content'     => "<p>Dear {patient_name},</p>\n<p>Your Care Coordinator <strong>{sender_name}</strong> sent you a new message regarding case <strong>#{booking_code}</strong>:</p>\n<div style=\"background:#F1F5F9; border-left:4px solid #0F766E; padding:14px 18px; border-radius:0 10px 10px 0; margin:16px 0; font-style:italic; color:#334155;\">\"{message_snippet}\"</div>\n<p>Please click below to reply directly in your real-time consultation room.</p>",
                'btn_text'    => __('Reply in Live Chat →', 'caretochina-medical'),
                'btn_url'     => '{chat_url}',
            ]
        ];
    }

    /**
     * Get all active templates (merged with saved customizations)
     */
    public static function get_all_templates() {
        $defaults = self::get_default_templates();
        $saved = get_option('ctc_email_templates', []);

        if (!is_array($saved) || empty($saved)) {
            return $defaults;
        }

        $merged = $defaults;
        foreach ($saved as $id => $tpl) {
            $merged[$id] = wp_parse_args($tpl, $defaults[$id] ?? [
                'id'        => $id,
                'name'      => $tpl['name'] ?? ucfirst($id),
                'subject'   => '',
                'heading'   => '',
                'preheader' => '',
                'content'   => '',
                'btn_text'  => '',
                'btn_url'   => '',
            ]);
        }

        return $merged;
    }

    /**
     * Get event mapping
     */
    public static function get_event_mapping() {
        $defaults = [
            'guest_booking'            => 'guest_booking',
            'patient_booking'          => 'patient_booking',
            'guest_registered_welcome' => 'guest_registered_welcome',
            'admin_booking'            => 'admin_booking',
            'status_update'            => 'status_update',
            'payment_request'          => 'payment_request',
            'payment_success'          => 'payment_success',
            'chat_message'             => 'chat_message',
        ];
        $saved = get_option('ctc_email_event_mapping', []);
        return wp_parse_args($saved, $defaults);
    }

    /**
     * Render the Master HTML Email Shell
     */
    public static function render_master_html($heading, $body_content, $btn_text = '', $btn_url = '', $preheader = '') {
        $b = self::get_branding();
        $site_name = esc_html(get_bloginfo('name'));
        $home_url = esc_url(home_url('/'));

        $logo_html = '';
        if (!empty($b['logo_url'])) {
            $logo_html = '<img src="' . esc_url($b['logo_url']) . '" alt="' . $site_name . '" style="max-height:48px; max-width:240px; display:block; margin:0 auto 10px auto;">';
        } else {
            $logo_html = '<div style="font-size:24px; font-weight:900; color:' . esc_attr($b['brand_color']) . '; text-align:center; letter-spacing:-0.5px;"><span style="color:#0F766E;">Care</span><span style="color:#14B8A6;">ToChina</span> <span style="font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:1.5px; color:#64748B; display:block; margin-top:2px;">Medical Travel & Access</span></div>';
        }

        $btn_html = '';
        if (!empty($btn_text) && !empty($btn_url)) {
            $btn_html = '
            <div style="text-align:center; margin:32px 0 20px 0;">
                <a href="' . esc_url($btn_url) . '" target="_blank" style="background:' . esc_attr($b['brand_color']) . '; color:#FFFFFF; text-decoration:none; padding:14px 28px; border-radius:12px; font-weight:700; font-size:15px; display:inline-block; box-shadow:0 4px 14px rgba(15,118,110,0.3); letter-spacing:0.3px;">
                    ' . esc_html($btn_text) . '
                </a>
            </div>';
        }

        $preheader_html = '';
        if (!empty($preheader)) {
            $preheader_html = '<div style="display:none; font-size:1px; color:#333333; line-height:1px; max-height:0px; max-width:0px; opacity:0; overflow:hidden;">' . esc_html($preheader) . '</div>';
        }

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html($heading); ?></title>
            <style>
                @media only screen and (max-width: 600px) {
                    .ctc-email-container { width: 100% !important; padding: 12px !important; }
                    .ctc-email-card { padding: 24px 18px !important; border-radius: 12px !important; }
                    .ctc-email-heading { font-size: 20px !important; }
                }
            </style>
        </head>
        <body style="margin:0; padding:0; background-color:<?php echo esc_attr($b['bg_color']); ?>; font-family:'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing:antialiased;">
            <?php echo $preheader_html; ?>
            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:<?php echo esc_attr($b['bg_color']); ?>; padding:30px 10px;">
                <tr>
                    <td align="center">
                        <table border="0" cellpadding="0" cellspacing="0" width="100%" class="ctc-email-container" style="max-width:620px; margin:0 auto;">
                            <!-- BRAND HEADER -->
                            <tr>
                                <td align="center" style="padding-bottom:24px;">
                                    <a href="<?php echo $home_url; ?>" style="text-decoration:none;">
                                        <?php echo $logo_html; ?>
                                    </a>
                                </td>
                            </tr>

                            <!-- MAIN CARD -->
                            <tr>
                                <td class="ctc-email-card" style="background-color:<?php echo esc_attr($b['card_bg']); ?>; border-radius:18px; padding:36px 32px; box-shadow:0 6px 20px rgba(15,23,42,0.06); border:1px solid #E2E8F0;">
                                    <!-- TOP ACCENT BAR -->
                                    <div style="height:4px; width:60px; background:linear-gradient(90deg, <?php echo esc_attr($b['brand_color']); ?>, <?php echo esc_attr($b['accent_color']); ?>); border-radius:4px; margin-bottom:20px;"></div>

                                    <!-- HEADING -->
                                    <h1 class="ctc-email-heading" style="margin:0 0 18px 0; font-size:22px; font-weight:800; color:#0F172A; line-height:1.35; letter-spacing:-0.4px;">
                                        <?php echo esc_html($heading); ?>
                                    </h1>

                                    <!-- CONTENT -->
                                    <div style="font-size:15px; line-height:1.65; color:#334155; margin-bottom:24px;">
                                        <?php echo $body_content; ?>
                                    </div>

                                    <!-- CTA BUTTON -->
                                    <?php echo $btn_html; ?>

                                    <!-- SECURITY BADGE -->
                                    <div style="border-top:1px solid #F1F5F9; margin-top:30px; padding-top:16px; display:flex; align-items:center; justify-content:space-between; font-size:12px; color:#94A3B8;">
                                        <span>🔒 Verified Medical Communication</span>
                                        <span>CareToChina Global Care</span>
                                    </div>
                                </td>
                            </tr>

                            <!-- FOOTER -->
                            <tr>
                                <td align="center" style="padding-top:24px; font-size:12px; line-height:1.6; color:#64748B; text-align:center;">
                                    <p style="margin:0 0 8px 0;">
                                        <?php echo esc_html($b['footer_text']); ?>
                                    </p>
                                    <p style="margin:0; font-size:11px; color:#94A3B8;">
                                        <a href="<?php echo $home_url; ?>" style="color:<?php echo esc_attr($b['brand_color']); ?>; text-decoration:none; font-weight:600;"><?php echo $site_name; ?></a> | 
                                        <a href="<?php echo $home_url . 'contact/'; ?>" style="color:#64748B; text-decoration:none;">Support Desk</a> | 
                                        <a href="<?php echo $home_url . 'privacy-policy/'; ?>" style="color:#64748B; text-decoration:none;">Privacy Policy</a>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate HTML summary table for case details
     */
    public static function build_case_summary_table($data) {
        ob_start();
        ?>
        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:12px; margin:16px 0; overflow:hidden; font-size:13.5px; border-collapse:collapse;">
            <?php if (!empty($data['booking_code'])) : ?>
                <tr style="border-bottom:1px solid #E2E8F0;">
                    <td style="padding:10px 14px; font-weight:700; color:#64748B; width:38%; background:#F1F5F9;"><?php _e('Case Reference:', 'caretochina-medical'); ?></td>
                    <td style="padding:10px 14px; font-weight:800; color:#0F766E;"><?php echo esc_html('#' . $data['booking_code']); ?></td>
                </tr>
            <?php endif; ?>
            <?php if (!empty($data['specialty'])) : ?>
                <tr style="border-bottom:1px solid #E2E8F0;">
                    <td style="padding:10px 14px; font-weight:700; color:#64748B; background:#F1F5F9;"><?php _e('Medical Specialty:', 'caretochina-medical'); ?></td>
                    <td style="padding:10px 14px; font-weight:600; color:#0F172A;"><?php echo esc_html($data['specialty']); ?></td>
                </tr>
            <?php endif; ?>
            <?php if (!empty($data['hospital_name'])) : ?>
                <tr style="border-bottom:1px solid #E2E8F0;">
                    <td style="padding:10px 14px; font-weight:700; color:#64748B; background:#F1F5F9;"><?php _e('Assigned Hospital:', 'caretochina-medical'); ?></td>
                    <td style="padding:10px 14px; font-weight:600; color:#0F172A;"><?php echo esc_html($data['hospital_name']); ?></td>
                </tr>
            <?php endif; ?>
            <?php if (!empty($data['status'])) : ?>
                <tr style="border-bottom:1px solid #E2E8F0;">
                    <td style="padding:10px 14px; font-weight:700; color:#64748B; background:#F1F5F9;"><?php _e('Current Status:', 'caretochina-medical'); ?></td>
                    <td style="padding:10px 14px; font-weight:700; color:#0F766E; text-transform:uppercase;"><?php echo esc_html($data['status']); ?></td>
                </tr>
            <?php endif; ?>
            <?php if (!empty($data['patient_phone'])) : ?>
                <tr>
                    <td style="padding:10px 14px; font-weight:700; color:#64748B; background:#F1F5F9;"><?php _e('Contact Phone:', 'caretochina-medical'); ?></td>
                    <td style="padding:10px 14px; font-weight:600; color:#0F172A;"><?php echo esc_html($data['patient_phone']); ?></td>
                </tr>
            <?php endif; ?>
        </table>
        <?php
        return ob_get_clean();
    }

    /**
     * Render and send an email notification for a specific event
     */
    public static function send_notification($event_key, $recipient_email, $data = []) {
        if (empty($recipient_email)) {
            return false;
        }

        $event_mapping = self::get_event_mapping();
        $template_id = $event_mapping[$event_key] ?? $event_key;

        $templates = self::get_all_templates();
        $tpl = $templates[$template_id] ?? ($templates['guest_booking'] ?? null);

        if (!$tpl) {
            return false;
        }

        // Prepare placeholder replacements
        $replacements = [
            '{patient_name}'      => esc_html($data['patient_name'] ?? $data['full_name'] ?? __('Patient', 'caretochina-medical')),
            '{patient_email}'     => esc_html($data['patient_email'] ?? $data['email'] ?? ''),
            '{patient_phone}'     => esc_html($data['patient_phone'] ?? $data['phone'] ?? ''),
            '{booking_code}'      => esc_html($data['booking_code'] ?? ''),
            '{request_code}'      => esc_html($data['request_code'] ?? ''),
            '{specialty}'         => esc_html($data['specialty'] ?? ''),
            '{hospital_name}'     => esc_html($data['hospital_name'] ?? __('Pending Assignment', 'caretochina-medical')),
            '{quote_details}'     => esc_html($data['quote_details'] ?? ''),
            '{status}'            => esc_html($data['status'] ?? 'Pending'),
            '{amount}'            => esc_html($data['amount'] ?? '0.00'),
            '{currency}'          => esc_html($data['currency'] ?? 'USD'),
            '{payment_method}'    => esc_html($data['payment_method'] ?? 'Online Gateway'),
            '{custom_title}'      => esc_html($data['custom_title'] ?? ''),
            '{sender_name}'       => esc_html($data['sender_name'] ?? 'Care Coordinator'),
            '{message_snippet}'   => esc_html($data['message_snippet'] ?? ''),
            '{chat_url}'          => esc_url($data['chat_url'] ?? home_url('/patient-dashboard/?tab=messages')),
            '{dashboard_url}'     => esc_url($data['dashboard_url'] ?? home_url('/patient-dashboard/')),
            '{staff_portal_url}'  => esc_url(admin_url('admin.php?page=caretochina-staff-desk')),
            '{site_name}'         => esc_html(get_bloginfo('name')),
            '{site_url}'          => esc_url(home_url('/')),
            '{current_year}'      => date('Y'),
            '{case_summary_table}'=> self::build_case_summary_table($data),
        ];

        $subject   = str_replace(array_keys($replacements), array_values($replacements), $tpl['subject']);
        $heading   = str_replace(array_keys($replacements), array_values($replacements), $tpl['heading']);
        $preheader = str_replace(array_keys($replacements), array_values($replacements), $tpl['preheader'] ?? '');
        $raw_body  = str_replace(array_keys($replacements), array_values($replacements), $tpl['content']);
        $btn_text  = str_replace(array_keys($replacements), array_values($replacements), $tpl['btn_text'] ?? '');
        $btn_url   = str_replace(array_keys($replacements), array_values($replacements), $tpl['btn_url'] ?? '');

        // Format body paragraphs if not already rich HTML
        if (strpos($raw_body, '<p>') === false && strpos($raw_body, '<div>') === false) {
            $raw_body = nl2br($raw_body);
        }

        $full_html = self::render_master_html($heading, $raw_body, $btn_text, $btn_url, $preheader);

        $branding = self::get_branding();
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $branding['from_name'] . ' <' . $branding['from_email'] . '>',
            'Reply-To: ' . $branding['from_email'],
        ];

        if (class_exists('CareToChina_Async_Mailer')) {
            return CareToChina_Async_Mailer::send($recipient_email, $subject, $full_html, $headers);
        }

        return wp_mail($recipient_email, $subject, $full_html, $headers);
    }

    /**
     * Admin Save Settings Handler
     */
    public function handle_save_settings() {
        if (!isset($_POST['ctc_email_settings_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['ctc_email_settings_nonce'], 'ctc_save_email_settings')) {
            wp_die(__('Security verification failed.', 'caretochina-medical'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied.', 'caretochina-medical'));
        }

        // 1. Save Branding Settings
        if (isset($_POST['ctc_email_brand_color'])) {
            update_option('ctc_email_brand_color', sanitize_hex_color($_POST['ctc_email_brand_color']));
        }
        if (isset($_POST['ctc_email_accent_color'])) {
            update_option('ctc_email_accent_color', sanitize_hex_color($_POST['ctc_email_accent_color']));
        }
        if (isset($_POST['ctc_email_bg_color'])) {
            update_option('ctc_email_bg_color', sanitize_hex_color($_POST['ctc_email_bg_color']));
        }
        if (isset($_POST['ctc_email_card_bg'])) {
            update_option('ctc_email_card_bg', sanitize_hex_color($_POST['ctc_email_card_bg']));
        }
        if (isset($_POST['ctc_email_logo_url'])) {
            update_option('ctc_email_logo_url', esc_url_raw($_POST['ctc_email_logo_url']));
        }
        if (isset($_POST['ctc_email_from_name'])) {
            update_option('ctc_email_from_name', sanitize_text_field($_POST['ctc_email_from_name']));
        }
        if (isset($_POST['ctc_email_from_email'])) {
            update_option('ctc_email_from_email', sanitize_email($_POST['ctc_email_from_email']));
        }
        if (isset($_POST['ctc_email_footer_text'])) {
            update_option('ctc_email_footer_text', sanitize_textarea_field($_POST['ctc_email_footer_text']));
        }

        // 2. Save Event Mapping
        if (isset($_POST['ctc_event_map']) && is_array($_POST['ctc_event_map'])) {
            $cleaned_map = [];
            foreach ($_POST['ctc_event_map'] as $ev => $tid) {
                $cleaned_map[sanitize_key($ev)] = sanitize_key($tid);
            }
            update_option('ctc_email_event_mapping', $cleaned_map);
        }

        // 3. Save Templates
        if (isset($_POST['ctc_templates']) && is_array($_POST['ctc_templates'])) {
            $saved_templates = self::get_all_templates();
            foreach ($_POST['ctc_templates'] as $tid => $tdata) {
                $tid = sanitize_key($tid);
                $saved_templates[$tid] = [
                    'id'        => $tid,
                    'name'      => sanitize_text_field($tdata['name'] ?? $tid),
                    'subject'   => sanitize_text_field($tdata['subject'] ?? ''),
                    'heading'   => sanitize_text_field($tdata['heading'] ?? ''),
                    'preheader' => sanitize_text_field($tdata['preheader'] ?? ''),
                    'content'   => wp_kses_post($tdata['content'] ?? ''),
                    'btn_text'  => sanitize_text_field($tdata['btn_text'] ?? ''),
                    'btn_url'   => sanitize_text_field($tdata['btn_url'] ?? ''),
                ];
            }
            update_option('ctc_email_templates', $saved_templates);
        }

        // Add custom new template if submitted
        if (!empty($_POST['new_template_name'])) {
            $new_name = sanitize_text_field($_POST['new_template_name']);
            $new_id = sanitize_key(str_replace(' ', '_', strtolower($new_name))) . '_' . rand(100, 999);
            $saved_templates = self::get_all_templates();
            $saved_templates[$new_id] = [
                'id'        => $new_id,
                'name'      => $new_name,
                'subject'   => sanitize_text_field($_POST['new_template_subject'] ?? $new_name),
                'heading'   => sanitize_text_field($_POST['new_template_heading'] ?? $new_name),
                'preheader' => '',
                'content'   => wp_kses_post($_POST['new_template_content'] ?? '<p>Dear {patient_name},</p><p>Custom template content here.</p>'),
                'btn_text'  => sanitize_text_field($_POST['new_template_btn_text'] ?? __('View Details →', 'caretochina-medical')),
                'btn_url'   => sanitize_text_field($_POST['new_template_btn_url'] ?? '{chat_url}'),
            ];
            update_option('ctc_email_templates', $saved_templates);
        }

        wp_redirect(add_query_arg(['page' => 'caretochina-email-templates', 'saved' => '1'], admin_url('admin.php')));
        exit;
    }

    /**
     * AJAX Test Email Sender
     */
    public function handle_ajax_send_test_email() {
        check_ajax_referer('ctc_email_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'caretochina-medical')]);
        }

        $email = sanitize_email($_POST['test_email'] ?? '');
        $template_id = sanitize_key($_POST['template_id'] ?? 'guest_booking');

        if (empty($email)) {
            wp_send_json_error(['message' => __('Please enter a valid recipient email address.', 'caretochina-medical')]);
        }

        $sample_data = [
            'patient_name'    => 'Sarah Jenkins (Test)',
            'full_name'       => 'Sarah Jenkins',
            'patient_email'   => $email,
            'patient_phone'   => '+1 (800) 555-0199',
            'booking_code'    => 'MED-948271',
            'request_code'    => 'REQ-48291',
            'specialty'       => 'Cardiology & Heart Care',
            'hospital_name'   => 'Shanghai Ruijin International Hospital',
            'quote_details'   => 'Inquiring about advanced minimally invasive valve replacement options.',
            'status'          => 'Confirmed & Approved',
            'amount'          => '1,200.00',
            'currency'        => 'USD',
            'payment_method'  => 'Stripe Secure Gateway',
            'custom_title'    => 'Cardiology Diagnostic Consultation & Deposit',
            'sender_name'     => 'Dr. Lin (Senior Medical Director)',
            'message_snippet' => 'We have reviewed your ECG report and confirmed availability for Dr. Wang.',
            'chat_url'        => home_url('/guest-chat/?token=demo_token_123'),
            'dashboard_url'   => home_url('/patient-dashboard/'),
        ];

        $sent = self::send_notification($template_id, $email, $sample_data);

        if ($sent) {
            wp_send_json_success(['message' => sprintf(__('Test email successfully queued & sent to %s!', 'caretochina-medical'), $email)]);
        } else {
            wp_send_json_error(['message' => __('Failed to dispatch test email. Please verify WordPress mail settings.', 'caretochina-medical')]);
        }
    }

    /**
     * AJAX Live HTML Preview
     */
    public function handle_ajax_preview_template() {
        check_ajax_referer('ctc_email_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }

        $template_id = sanitize_key($_POST['template_id'] ?? 'guest_booking');
        $templates = self::get_all_templates();
        $tpl = $templates[$template_id] ?? reset($templates);

        $sample_data = [
            'patient_name'    => 'Sarah Jenkins',
            'full_name'       => 'Sarah Jenkins',
            'patient_email'   => 'sarah.jenkins@example.com',
            'patient_phone'   => '+1 (800) 555-0199',
            'booking_code'    => 'MED-948271',
            'request_code'    => 'REQ-48291',
            'specialty'       => 'Cardiology & Heart Care',
            'hospital_name'   => 'Shanghai Ruijin International Hospital',
            'quote_details'   => 'Inquiring about advanced valve repair consultation.',
            'status'          => 'Confirmed & Approved',
            'amount'          => '1,200.00',
            'currency'        => 'USD',
            'payment_method'  => 'Stripe Secure Gateway',
            'custom_title'    => 'Cardiology Diagnostic Consultation & Deposit',
            'sender_name'     => 'Dr. Lin (Care Coordinator)',
            'message_snippet' => 'We have scheduled your pre-consultation review with the cardiology department.',
            'chat_url'        => home_url('/guest-chat/?token=demo'),
            'dashboard_url'   => home_url('/patient-dashboard/'),
            'current_year'    => date('Y'),
        ];

        $replacements = [
            '{patient_name}'      => $sample_data['patient_name'],
            '{patient_email}'     => $sample_data['patient_email'],
            '{patient_phone}'     => $sample_data['patient_phone'],
            '{booking_code}'      => $sample_data['booking_code'],
            '{request_code}'      => $sample_data['request_code'],
            '{specialty}'         => $sample_data['specialty'],
            '{hospital_name}'     => $sample_data['hospital_name'],
            '{quote_details}'     => $sample_data['quote_details'],
            '{status}'            => $sample_data['status'],
            '{amount}'            => $sample_data['amount'],
            '{currency}'          => $sample_data['currency'],
            '{payment_method}'    => $sample_data['payment_method'],
            '{custom_title}'      => $sample_data['custom_title'],
            '{sender_name}'       => $sample_data['sender_name'],
            '{message_snippet}'   => $sample_data['message_snippet'],
            '{chat_url}'          => $sample_data['chat_url'],
            '{dashboard_url}'     => $sample_data['dashboard_url'],
            '{staff_portal_url}'  => admin_url('admin.php?page=caretochina-staff-desk'),
            '{site_name}'         => get_bloginfo('name'),
            '{site_url}'          => home_url('/'),
            '{current_year}'      => date('Y'),
            '{case_summary_table}'=> self::build_case_summary_table($sample_data),
        ];

        $heading   = str_replace(array_keys($replacements), array_values($replacements), $tpl['heading']);
        $preheader = str_replace(array_keys($replacements), array_values($replacements), $tpl['preheader'] ?? '');
        $raw_body  = str_replace(array_keys($replacements), array_values($replacements), $tpl['content']);
        $btn_text  = str_replace(array_keys($replacements), array_values($replacements), $tpl['btn_text'] ?? '');
        $btn_url   = str_replace(array_keys($replacements), array_values($replacements), $tpl['btn_url'] ?? '');

        if (strpos($raw_body, '<p>') === false && strpos($raw_body, '<div>') === false) {
            $raw_body = nl2br($raw_body);
        }

        echo self::render_master_html($heading, $raw_body, $btn_text, $btn_url, $preheader);
        exit;
    }

    /**
     * Render the Admin Page UI
     */
    public function render_admin_page() {
        $branding = self::get_branding();
        $templates = self::get_all_templates();
        $event_mapping = self::get_event_mapping();
        $ajax_nonce = wp_create_nonce('ctc_email_ajax_nonce');

        $events_def = [
            'guest_booking'            => __('Guest Booking Confirmation (Sent to Guest Patient)', 'caretochina-medical'),
            'patient_booking'          => __('Registered Patient Booking (Sent to Logged-in Patient)', 'caretochina-medical'),
            'guest_registered_welcome' => __('Guest to Registered Patient Welcome (Sent after Account Creation)', 'caretochina-medical'),
            'admin_booking'            => __('New Booking / Inquiry Alert (Sent to Medical Staff & Coordinators)', 'caretochina-medical'),
            'status_update'            => __('Case Approval & Status Update (Sent on Case Status Change)', 'caretochina-medical'),
            'payment_request'          => __('Medical Payment Request (Sent when Coordinator creates Chat Invoice)', 'caretochina-medical'),
            'payment_success'          => __('Payment Confirmation & Receipt (Sent after Stripe/PayPal payment)', 'caretochina-medical'),
            'chat_message'             => __('Live Chat Message Alert (Sent when Coordinator messages Patient)', 'caretochina-medical'),
        ];

        $active_subtab = sanitize_key($_GET['tab'] ?? 'templates');
        ?>
        <div class="wrap" style="max-width:1120px; font-family:'Manrope', -apple-system, sans-serif;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; border-bottom:1px solid #CBD5E1; padding-bottom:14px;">
                <div>
                    <h1 style="margin:0 0 4px 0; font-size:24px; font-weight:800; color:#0F172A; display:flex; align-items:center; gap:10px;">
                        <i class="fa-solid fa-envelope-circle-check" style="color:#0F766E;"></i> <?php _e('CareToChina Email Templates & Brand Center', 'caretochina-medical'); ?>
                    </h1>
                    <p style="margin:0; font-size:13.5px; color:#64748B;">
                        <?php _e('Customize brand colors, typography, email copywriting, dynamic placeholders, and notification routing for all outbound emails.', 'caretochina-medical'); ?>
                    </p>
                </div>
                <div>
                    <button type="button" onclick="openTestEmailModal()" class="button button-primary" style="background:#0F766E; border-color:#0F766E; font-weight:700; padding:6px 16px; height:auto; font-size:13.5px; display:inline-flex; align-items:center; gap:6px;">
                        <i class="fa-solid fa-paper-plane"></i> <?php _e('Send Test Email', 'caretochina-medical'); ?>
                    </button>
                </div>
            </div>

            <?php if (isset($_GET['saved'])) : ?>
                <div class="notice notice-success is-dismissible" style="border-left-color:#0F766E;">
                    <p><strong><i class="fa-solid fa-circle-check" style="color:#0F766E;"></i> <?php _e('Email templates and branding settings saved successfully!', 'caretochina-medical'); ?></strong></p>
                </div>
            <?php endif; ?>

            <!-- NAVIGATION TABS -->
            <h2 class="nav-tab-wrapper" style="margin-bottom:24px;">
                <a href="<?php echo esc_url(add_query_arg('tab', 'templates')); ?>" class="nav-tab <?php echo ($active_subtab === 'templates') ? 'nav-tab-active' : ''; ?>" style="<?php echo ($active_subtab === 'templates') ? 'background:#FFFFFF; color:#0F766E; font-weight:700;' : ''; ?>">
                    <i class="fa-solid fa-file-signature"></i> <?php _e('Email Templates Editor', 'caretochina-medical'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'routing')); ?>" class="nav-tab <?php echo ($active_subtab === 'routing') ? 'nav-tab-active' : ''; ?>" style="<?php echo ($active_subtab === 'routing') ? 'background:#FFFFFF; color:#0F766E; font-weight:700;' : ''; ?>">
                    <i class="fa-solid fa-arrows-split-up-and-left"></i> <?php _e('Notification Event Routing', 'caretochina-medical'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'branding')); ?>" class="nav-tab <?php echo ($active_subtab === 'branding') ? 'nav-tab-active' : ''; ?>" style="<?php echo ($active_subtab === 'branding') ? 'background:#FFFFFF; color:#0F766E; font-weight:700;' : ''; ?>">
                    <i class="fa-solid fa-palette"></i> <?php _e('Brand Theme & Styling', 'caretochina-medical'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'add_new')); ?>" class="nav-tab <?php echo ($active_subtab === 'add_new') ? 'nav-tab-active' : ''; ?>" style="<?php echo ($active_subtab === 'add_new') ? 'background:#FFFFFF; color:#0F766E; font-weight:700;' : ''; ?>">
                    <i class="fa-solid fa-plus-circle"></i> <?php _e('Add New Template', 'caretochina-medical'); ?>
                </a>
            </h2>

            <form method="post" action="">
                <?php wp_nonce_field('ctc_save_email_settings', 'ctc_email_settings_nonce'); ?>

                <!-- TAB 1: TEMPLATES EDITOR -->
                <?php if ($active_subtab === 'templates') : ?>
                    <div style="background:#FFFFFF; border:1px solid #CBD5E1; border-radius:14px; padding:24px; box-shadow:0 2px 8px rgba(0,0,0,0.04); margin-bottom:24px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
                            <div>
                                <label for="active_template_selector" style="font-weight:700; font-size:14px; margin-right:8px; color:#0F172A;"><?php _e('Select Template to Edit:', 'caretochina-medical'); ?></label>
                                <select id="active_template_selector" onchange="switchTemplateEditor(this.value)" style="padding:6px 12px; border-radius:8px; font-weight:700; font-size:14px; border-color:#CBD5E1;">
                                    <?php foreach ($templates as $tid => $t) : ?>
                                        <option value="<?php echo esc_attr($tid); ?>"><?php echo esc_html($t['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div style="display:flex; gap:10px;">
                                <button type="button" onclick="previewCurrentTemplate()" class="button" style="font-weight:600; color:#0F766E; border-color:#0F766E; display:flex; align-items:center; gap:6px;">
                                    <i class="fa-solid fa-eye"></i> <?php _e('Live Preview', 'caretochina-medical'); ?>
                                </button>
                                <button type="submit" class="button button-primary" style="background:#0F766E; border-color:#0F766E; font-weight:700; display:flex; align-items:center; gap:6px;">
                                    <i class="fa-solid fa-floppy-disk"></i> <?php _e('Save Changes', 'caretochina-medical'); ?>
                                </button>
                            </div>
                        </div>

                        <!-- PLACEHOLDERS GUIDE -->
                        <div style="background:#F0FDFA; border:1px solid #99F6E4; border-radius:10px; padding:14px 18px; margin-bottom:24px;">
                            <div style="font-size:13px; font-weight:700; color:#0F766E; margin-bottom:6px;">
                                <i class="fa-solid fa-tags"></i> <?php _e('Available Merge Tags (Click to Copy):', 'caretochina-medical'); ?>
                            </div>
                            <div style="display:flex; flex-wrap:wrap; gap:6px; font-size:12px;">
                                <?php
                                $tags = [
                                    '{patient_name}', '{patient_email}', '{patient_phone}', '{booking_code}',
                                    '{request_code}', '{specialty}', '{hospital_name}', '{status}', '{amount}',
                                    '{currency}', '{payment_method}', '{custom_title}', '{sender_name}',
                                    '{message_snippet}', '{chat_url}', '{dashboard_url}', '{case_summary_table}',
                                    '{site_name}', '{current_year}'
                                ];
                                foreach ($tags as $tg) {
                                    echo '<span class="ctc-tag-pill" onclick="copyTag(this)" style="background:#FFFFFF; border:1px solid #CCFBF1; color:#0F766E; padding:3px 8px; border-radius:6px; font-family:monospace; cursor:pointer; font-weight:600; transition:all 0.2s;" title="' . esc_attr__('Click to copy tag', 'caretochina-medical') . '">' . esc_html($tg) . '</span>';
                                }
                                ?>
                            </div>
                        </div>

                        <!-- TEMPLATE FORM FIELDS -->
                        <?php $i = 0; foreach ($templates as $tid => $t) : $i++; ?>
                            <div class="template-editor-pane" id="pane_<?php echo esc_attr($tid); ?>" style="display:<?php echo ($i === 1) ? 'block' : 'none'; ?>;">
                                <input type="hidden" name="ctc_templates[<?php echo esc_attr($tid); ?>][name]" value="<?php echo esc_attr($t['name']); ?>">
                                
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:18px; margin-bottom:16px;">
                                    <div>
                                        <label style="display:block; font-weight:700; margin-bottom:6px; color:#334155;"><?php _e('Email Subject Line:', 'caretochina-medical'); ?></label>
                                        <input type="text" name="ctc_templates[<?php echo esc_attr($tid); ?>][subject]" value="<?php echo esc_attr($t['subject']); ?>" style="width:100%; border-radius:8px; padding:8px 12px; font-size:14px; border:1px solid #CBD5E1;" required>
                                    </div>
                                    <div>
                                        <label style="display:block; font-weight:700; margin-bottom:6px; color:#334155;"><?php _e('Email Heading (H1 inside Card):', 'caretochina-medical'); ?></label>
                                        <input type="text" name="ctc_templates[<?php echo esc_attr($tid); ?>][heading]" value="<?php echo esc_attr($t['heading']); ?>" style="width:100%; border-radius:8px; padding:8px 12px; font-size:14px; border:1px solid #CBD5E1;" required>
                                    </div>
                                </div>

                                <div style="margin-bottom:16px;">
                                    <label style="display:block; font-weight:700; margin-bottom:6px; color:#334155;"><?php _e('Preheader Text (Snippet shown in Inbox):', 'caretochina-medical'); ?></label>
                                    <input type="text" name="ctc_templates[<?php echo esc_attr($tid); ?>][preheader]" value="<?php echo esc_attr($t['preheader'] ?? ''); ?>" style="width:100%; border-radius:8px; padding:8px 12px; font-size:14px; border:1px solid #CBD5E1;" placeholder="<?php _e('Optional short teaser shown next to the subject line in email clients', 'caretochina-medical'); ?>">
                                </div>

                                <div style="margin-bottom:18px;">
                                    <label style="display:block; font-weight:700; margin-bottom:6px; color:#334155;"><?php _e('Email Body Content (HTML allowed):', 'caretochina-medical'); ?></label>
                                    <textarea name="ctc_templates[<?php echo esc_attr($tid); ?>][content]" rows="10" style="width:100%; border-radius:8px; padding:12px; font-size:13.5px; font-family:monospace; line-height:1.6; border:1px solid #CBD5E1;"><?php echo esc_textarea($t['content']); ?></textarea>
                                </div>

                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:18px; margin-bottom:16px; background:#F8FAFC; padding:16px; border-radius:10px; border:1px solid #E2E8F0;">
                                    <div>
                                        <label style="display:block; font-weight:700; margin-bottom:6px; color:#334155;"><?php _e('Primary Action Button Text:', 'caretochina-medical'); ?></label>
                                        <input type="text" name="ctc_templates[<?php echo esc_attr($tid); ?>][btn_text]" value="<?php echo esc_attr($t['btn_text'] ?? ''); ?>" style="width:100%; border-radius:8px; padding:8px 12px; font-size:14px; border:1px solid #CBD5E1;" placeholder="<?php _e('e.g. Access Live Chat Consultation →', 'caretochina-medical'); ?>">
                                    </div>
                                    <div>
                                        <label style="display:block; font-weight:700; margin-bottom:6px; color:#334155;"><?php _e('Button Destination URL / Placeholder:', 'caretochina-medical'); ?></label>
                                        <input type="text" name="ctc_templates[<?php echo esc_attr($tid); ?>][btn_url]" value="<?php echo esc_attr($t['btn_url'] ?? ''); ?>" style="width:100%; border-radius:8px; padding:8px 12px; font-size:14px; border:1px solid #CBD5E1;" placeholder="<?php _e('e.g. {chat_url} or {dashboard_url}', 'caretochina-medical'); ?>">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- TAB 2: NOTIFICATION ROUTING -->
                <?php if ($active_subtab === 'routing') : ?>
                    <div style="background:#FFFFFF; border:1px solid #CBD5E1; border-radius:14px; padding:24px; box-shadow:0 2px 8px rgba(0,0,0,0.04); margin-bottom:24px;">
                        <h3 style="margin:0 0 8px 0; font-size:18px; font-weight:800; color:#0F172A;"><?php _e('Notification Event to Template Assignment', 'caretochina-medical'); ?></h3>
                        <p style="margin:0 0 20px 0; font-size:13.5px; color:#64748B;"><?php _e('Assign which email template is triggered for each medical and payment event across the plugin.', 'caretochina-medical'); ?></p>

                        <table class="widefat striped" style="border:1px solid #E2E8F0; border-radius:10px; overflow:hidden;">
                            <thead>
                                <tr>
                                    <th style="font-weight:700; padding:12px 16px;"><?php _e('System Event Trigger', 'caretochina-medical'); ?></th>
                                    <th style="font-weight:700; padding:12px 16px;"><?php _e('Assigned Email Template', 'caretochina-medical'); ?></th>
                                    <th style="font-weight:700; padding:12px 16px; text-align:right;"><?php _e('Quick Action', 'caretochina-medical'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($events_def as $ev_key => $ev_label) : 
                                    $assigned = $event_mapping[$ev_key] ?? $ev_key;
                                    ?>
                                    <tr>
                                        <td style="padding:14px 16px; vertical-align:middle;">
                                            <strong style="color:#0F172A; font-size:14px;"><?php echo esc_html($ev_label); ?></strong>
                                            <div style="font-size:11px; color:#64748B; font-family:monospace; margin-top:2px;">Trigger Hook: <code><?php echo esc_html($ev_key); ?></code></div>
                                        </td>
                                        <td style="padding:14px 16px; vertical-align:middle;">
                                            <select name="ctc_event_map[<?php echo esc_attr($ev_key); ?>]" style="padding:6px 12px; border-radius:8px; font-weight:700; font-size:13.5px; border-color:#CBD5E1; width:100%; max-width:320px;">
                                                <?php foreach ($templates as $tid => $t) : ?>
                                                    <option value="<?php echo esc_attr($tid); ?>" <?php selected($assigned, $tid); ?>>
                                                        <?php echo esc_html($t['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td style="padding:14px 16px; vertical-align:middle; text-align:right;">
                                            <button type="button" onclick="previewSpecificTemplate('<?php echo esc_attr($assigned); ?>')" class="button" style="font-size:12px; font-weight:600; color:#0F766E;">
                                                <i class="fa-solid fa-eye"></i> <?php _e('Preview', 'caretochina-medical'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <!-- TAB 3: BRAND THEME & STYLING -->
                <?php if ($active_subtab === 'branding') : ?>
                    <div style="background:#FFFFFF; border:1px solid #CBD5E1; border-radius:14px; padding:24px; box-shadow:0 2px 8px rgba(0,0,0,0.04); margin-bottom:24px;">
                        <h3 style="margin:0 0 8px 0; font-size:18px; font-weight:800; color:#0F172A;"><?php _e('Brand Theme, Logo & Sender Information', 'caretochina-medical'); ?></h3>
                        <p style="margin:0 0 20px 0; font-size:13.5px; color:#64748B;"><?php _e('These color tokens and credentials apply to every outbound email sent by CareToChina Medical.', 'caretochina-medical'); ?></p>

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px;">
                            <div>
                                <label style="display:block; font-weight:700; margin-bottom:6px; color:#334155;"><?php _e('Primary Brand Color (Buttons, Accents, Links):', 'caretochina-medical'); ?></label>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <input type="color" name="ctc_email_brand_color" value="<?php echo esc_attr($branding['brand_color']); ?>" style="width:48px; height:38px; padding:2px; border-radius:8px; border:1px solid #CBD5E1; cursor:pointer;">
                                    <input type="text" value="<?php echo esc_attr($branding['brand_color']); ?>" style="width:120px; border-radius:8px; padding:8px 10px; font-family:monospace; font-weight:700;" readonly>
                                </div>
                            </div>
                            <div>
                                <label style="display:block; font-weight:700; margin-bottom:6px; color:#334155;"><?php _e('Accent Glow / Gradient Color:', 'caretochina-medical'); ?></label>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <input type="color" name="ctc_email_accent_color" value="<?php echo esc_attr($branding['accent_color']); ?>" style="width:48px; height:38px; padding:2px; border-radius:8px; border:1px solid #CBD5E1; cursor:pointer;">
                                    <input type="text" value="<?php echo esc_attr($branding['accent_color']); ?>" style="width:120px; border-radius:8px; padding:8px 10px; font-family:monospace; font-weight:700;" readonly>
                                </div>
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px;">
                            <div>
                                <label style="display:block; font-weight:700; margin-bottom:6px; color:#334155;"><?php _e('Email Background Color:', 'caretochina-medical'); ?></label>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <input type="color" name="ctc_email_bg_color" value="<?php echo esc_attr($branding['bg_color']); ?>" style="width:48px; height:38px; padding:2px; border-radius:8px; border:1px solid #CBD5E1; cursor:pointer;">
                                    <input type="text" value="<?php echo esc_attr($branding['bg_color']); ?>" style="width:120px; border-radius:8px; padding:8px 10px; font-family:monospace; font-weight:700;" readonly>
                                </div>
                            </div>
                            <div>
                                <label style="display:block; font-weight:700; margin-bottom:6px; color:#334155;"><?php _e('Card Container Background:', 'caretochina-medical'); ?></label>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <input type="color" name="ctc_email_card_bg" value="<?php echo esc_attr($branding['card_bg']); ?>" style="width:48px; height:38px; padding:2px; border-radius:8px; border:1px solid #CBD5E1; cursor:pointer;">
                                    <input type="text" value="<?php echo esc_attr($branding['card_bg']); ?>" style="width:120px; border-radius:8px; padding:8px 10px; font-family:monospace; font-weight:700;" readonly>
                                </div>
                            </div>
                        </div>

                        <div style="margin-bottom:20px;">
                            <label style="display:block; font-weight:700; margin-bottom:6px; color:#334155;"><?php _e('Custom Email Header Logo Image URL:', 'caretochina-medical'); ?></label>
                            <input type="url" name="ctc_email_logo_url" value="<?php echo esc_attr($branding['logo_url']); ?>" style="width:100%; border-radius:8px; padding:8px 12px; font-size:14px; border:1px solid #CBD5E1;" placeholder="https://caretochina.com/wp-content/uploads/logo.png">
                            <span class="description" style="color:#64748B; font-size:12px;"><?php _e('Leave blank to use the modern CareToChina SVG/text brand header.', 'caretochina-medical'); ?></span>
                        </div>

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px;">
                            <div>
                                <label style="display:block; font-weight:700; margin-bottom:6px; color:#334155;"><?php _e('Sender Name (From Name):', 'caretochina-medical'); ?></label>
                                <input type="text" name="ctc_email_from_name" value="<?php echo esc_attr($branding['from_name']); ?>" style="width:100%; border-radius:8px; padding:8px 12px; font-size:14px; border:1px solid #CBD5E1;" required>
                            </div>
                            <div>
                                <label style="display:block; font-weight:700; margin-bottom:6px; color:#334155;"><?php _e('Sender Email (From Address):', 'caretochina-medical'); ?></label>
                                <input type="email" name="ctc_email_from_email" value="<?php echo esc_attr($branding['from_email']); ?>" style="width:100%; border-radius:8px; padding:8px 12px; font-size:14px; border:1px solid #CBD5E1;" required>
                            </div>
                        </div>

                        <div style="margin-bottom:20px;">
                            <label style="display:block; font-weight:700; margin-bottom:6px; color:#334155;"><?php _e('Footer Copyright & Legal Disclaimer:', 'caretochina-medical'); ?></label>
                            <textarea name="ctc_email_footer_text" rows="3" style="width:100%; border-radius:8px; padding:10px; font-size:13.5px; border:1px solid #CBD5E1;"><?php echo esc_textarea($branding['footer_text']); ?></textarea>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- TAB 4: ADD NEW TEMPLATE -->
                <?php if ($active_subtab === 'add_new') : ?>
                    <div style="background:#FFFFFF; border:1px solid #CBD5E1; border-radius:14px; padding:24px; box-shadow:0 2px 8px rgba(0,0,0,0.04); margin-bottom:24px;">
                        <h3 style="margin:0 0 8px 0; font-size:18px; font-weight:800; color:#0F766E;"><?php _e('Create a Custom Email Template', 'caretochina-medical'); ?></h3>
                        <p style="margin:0 0 20px 0; font-size:13.5px; color:#64748B;"><?php _e('Create a new custom template that can be mapped to any notification hook or sent via API.', 'caretochina-medical'); ?></p>

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:18px; margin-bottom:16px;">
                            <div>
                                <label style="display:block; font-weight:700; margin-bottom:6px; color:#334155;"><?php _e('Template Name *', 'caretochina-medical'); ?></label>
                                <input type="text" name="new_template_name" style="width:100%; border-radius:8px; padding:8px 12px; font-size:14px; border:1px solid #CBD5E1;" placeholder="<?php _e('e.g. VIP Concierge Travel Package Ready', 'caretochina-medical'); ?>">
                            </div>
                            <div>
                                <label style="display:block; font-weight:700; margin-bottom:6px; color:#334155;"><?php _e('Subject Line *', 'caretochina-medical'); ?></label>
                                <input type="text" name="new_template_subject" style="width:100%; border-radius:8px; padding:8px 12px; font-size:14px; border:1px solid #CBD5E1;" placeholder="<?php _e('[CareToChina] Your VIP Package is Ready: #{booking_code}', 'caretochina-medical'); ?>">
                            </div>
                        </div>

                        <div style="margin-bottom:16px;">
                            <label style="display:block; font-weight:700; margin-bottom:6px; color:#334155;"><?php _e('Email Card Heading *', 'caretochina-medical'); ?></label>
                            <input type="text" name="new_template_heading" style="width:100%; border-radius:8px; padding:8px 12px; font-size:14px; border:1px solid #CBD5E1;" placeholder="<?php _e('Your Medical Itinerary is Ready for Departure', 'caretochina-medical'); ?>">
                        </div>

                        <div style="margin-bottom:18px;">
                            <label style="display:block; font-weight:700; margin-bottom:6px; color:#334155;"><?php _e('Body Content (HTML & Placeholders allowed) *', 'caretochina-medical'); ?></label>
                            <textarea name="new_template_content" rows="8" style="width:100%; border-radius:8px; padding:12px; font-size:13.5px; font-family:monospace; line-height:1.6; border:1px solid #CBD5E1;"><p>Dear {patient_name},</p>
<p>We are pleased to inform you that your customized medical consultation schedule for <strong>{specialty}</strong> at <strong>{hospital_name}</strong> is ready.</p>
{case_summary_table}</textarea>
                        </div>

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:18px; margin-bottom:16px;">
                            <div>
                                <label style="display:block; font-weight:700; margin-bottom:6px; color:#334155;"><?php _e('Button Text:', 'caretochina-medical'); ?></label>
                                <input type="text" name="new_template_btn_text" style="width:100%; border-radius:8px; padding:8px 12px; font-size:14px; border:1px solid #CBD5E1;" placeholder="<?php _e('View My Itinerary →', 'caretochina-medical'); ?>">
                            </div>
                            <div>
                                <label style="display:block; font-weight:700; margin-bottom:6px; color:#334155;"><?php _e('Button Destination URL:', 'caretochina-medical'); ?></label>
                                <input type="text" name="new_template_btn_url" style="width:100%; border-radius:8px; padding:8px 12px; font-size:14px; border:1px solid #CBD5E1;" placeholder="{dashboard_url}">
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div style="margin-top:20px;">
                    <button type="submit" class="button button-primary" style="background:#0F766E; border-color:#0F766E; font-weight:800; font-size:15px; padding:8px 24px; height:auto; border-radius:8px; box-shadow:0 4px 12px rgba(15,118,110,0.25);">
                        <i class="fa-solid fa-floppy-disk"></i> <?php _e('Save All Email Settings', 'caretochina-medical'); ?>
                    </button>
                </div>
            </form>

            <!-- LIVE PREVIEW MODAL -->
            <div id="ctc-preview-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.75); z-index:999999; align-items:center; justify-content:center; backdrop-filter:blur(4px);">
                <div style="background:#FFFFFF; border-radius:18px; width:92%; max-width:740px; max-height:90vh; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 25px 50px rgba(0,0,0,0.35);">
                    <div style="padding:16px 24px; border-bottom:1px solid #E2E8F0; display:flex; justify-content:space-between; align-items:center; background:#F8FAFC;">
                        <h3 style="margin:0; font-size:16px; font-weight:800; color:#0F172A; display:flex; align-items:center; gap:8px;">
                            <i class="fa-solid fa-eye" style="color:#0F766E;"></i> <?php _e('Live Responsive Email Preview', 'caretochina-medical'); ?>
                        </h3>
                        <div style="display:flex; align-items:center; gap:12px;">
                            <button type="button" onclick="setPreviewDevice('desktop')" class="button" id="btn-prev-desktop" style="font-weight:700;"><i class="fa-solid fa-desktop"></i></button>
                            <button type="button" onclick="setPreviewDevice('mobile')" class="button" id="btn-prev-mobile"><i class="fa-solid fa-mobile-screen"></i></button>
                            <button type="button" onclick="closePreviewModal()" style="background:none; border:none; font-size:22px; cursor:pointer; color:#64748B;">&times;</button>
                        </div>
                    </div>
                    <div id="preview-iframe-wrapper" style="flex:1; padding:20px; background:#E2E8F0; overflow-y:auto; display:flex; justify-content:center;">
                        <iframe id="preview-iframe" style="width:100%; height:620px; border:none; border-radius:10px; background:#FFFFFF; box-shadow:0 4px 15px rgba(0,0,0,0.1); transition:width 0.3s ease;"></iframe>
                    </div>
                </div>
            </div>

            <!-- TEST EMAIL SENDER MODAL -->
            <div id="ctc-test-email-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.75); z-index:999999; align-items:center; justify-content:center; backdrop-filter:blur(4px);">
                <div style="background:#FFFFFF; border-radius:18px; width:92%; max-width:480px; padding:26px; box-shadow:0 25px 50px rgba(0,0,0,0.35); position:relative;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                        <h3 style="margin:0; font-size:18px; font-weight:800; color:#0F172A; display:flex; align-items:center; gap:8px;">
                            <i class="fa-solid fa-paper-plane" style="color:#0F766E;"></i> <?php _e('Send Test Email', 'caretochina-medical'); ?>
                        </h3>
                        <button type="button" onclick="closeTestEmailModal()" style="background:none; border:none; font-size:22px; cursor:pointer; color:#64748B;">&times;</button>
                    </div>
                    <p style="margin:0 0 16px 0; font-size:13px; color:#64748B;"><?php _e('Send a live rendered test email to your inbox to test SMTP delivery and email client layout.', 'caretochina-medical'); ?></p>
                    
                    <div style="margin-bottom:14px;">
                        <label style="display:block; font-weight:700; margin-bottom:6px; color:#334155;"><?php _e('Recipient Email Address:', 'caretochina-medical'); ?></label>
                        <input type="email" id="test_email_recipient" value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>" style="width:100%; border-radius:8px; padding:8px 12px; font-size:14px; border:1px solid #CBD5E1;">
                    </div>

                    <div style="margin-bottom:20px;">
                        <label style="display:block; font-weight:700; margin-bottom:6px; color:#334155;"><?php _e('Select Template to Test:', 'caretochina-medical'); ?></label>
                        <select id="test_email_template_id" style="width:100%; border-radius:8px; padding:8px 12px; font-size:14px; border:1px solid #CBD5E1; font-weight:600;">
                            <?php foreach ($templates as $tid => $t) : ?>
                                <option value="<?php echo esc_attr($tid); ?>"><?php echo esc_html($t['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="test-email-status" style="display:none; margin-bottom:14px; padding:10px 14px; border-radius:8px; font-size:13px; font-weight:700;"></div>

                    <div style="display:flex; justify-content:flex-end; gap:10px;">
                        <button type="button" onclick="closeTestEmailModal()" class="button"><?php _e('Cancel', 'caretochina-medical'); ?></button>
                        <button type="button" id="btn_send_test_now" onclick="sendTestEmailNow()" class="button button-primary" style="background:#0F766E; border-color:#0F766E; font-weight:700;">
                            <i class="fa-solid fa-paper-plane"></i> <?php _e('Send Test Now', 'caretochina-medical'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        function switchTemplateEditor(templateId) {
            jQuery('.template-editor-pane').hide();
            jQuery('#pane_' + templateId).show();
        }

        function copyTag(element) {
            var tag = jQuery(element).text();
            navigator.clipboard.writeText(tag).then(function() {
                var origBg = element.style.backgroundColor;
                element.style.backgroundColor = '#0F766E';
                element.style.color = '#FFFFFF';
                setTimeout(function() {
                    element.style.backgroundColor = origBg;
                    element.style.color = '#0F766E';
                }, 400);
            });
        }

        function previewCurrentTemplate() {
            var activeId = jQuery('#active_template_selector').val();
            previewSpecificTemplate(activeId);
        }

        function previewSpecificTemplate(templateId) {
            jQuery('#preview-iframe').attr('src', 'about:blank');
            jQuery('#ctc-preview-modal').css('display', 'flex');

            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ctc_preview_email_template',
                    template_id: templateId,
                    nonce: '<?php echo esc_js($ajax_nonce); ?>'
                },
                success: function(html) {
                    var doc = document.getElementById('preview-iframe').contentWindow.document;
                    doc.open();
                    doc.write(html);
                    doc.close();
                }
            });
        }

        function setPreviewDevice(device) {
            if (device === 'mobile') {
                jQuery('#preview-iframe').css('width', '380px');
                jQuery('#btn-prev-mobile').addClass('button-primary');
                jQuery('#btn-prev-desktop').removeClass('button-primary');
            } else {
                jQuery('#preview-iframe').css('width', '100%');
                jQuery('#btn-prev-desktop').addClass('button-primary');
                jQuery('#btn-prev-mobile').removeClass('button-primary');
            }
        }

        function closePreviewModal() {
            jQuery('#ctc-preview-modal').hide();
        }

        function openTestEmailModal() {
            jQuery('#test-email-status').hide().empty();
            jQuery('#ctc-test-email-modal').css('display', 'flex');
        }

        function closeTestEmailModal() {
            jQuery('#ctc-test-email-modal').hide();
        }

        function sendTestEmailNow() {
            var email = jQuery('#test_email_recipient').val();
            var tplId = jQuery('#test_email_template_id').val();
            var btn = jQuery('#btn_send_test_now');
            var box = jQuery('#test-email-status');

            if (!email) {
                alert('Please enter a recipient email address.');
                return;
            }

            btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Sending...');
            box.hide().empty();

            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ctc_send_test_email',
                    test_email: email,
                    template_id: tplId,
                    nonce: '<?php echo esc_js($ajax_nonce); ?>'
                },
                success: function(res) {
                    btn.prop('disabled', false).html('<i class="fa-solid fa-paper-plane"></i> Send Test Now');
                    box.show();
                    if (res.success) {
                        box.css({background: '#D1FAE5', color: '#065F46'}).html('<i class="fa-solid fa-circle-check"></i> ' + res.data.message);
                    } else {
                        box.css({background: '#FEE2E2', color: '#991B1B'}).html('<i class="fa-solid fa-triangle-exclamation"></i> ' + ((res.data && res.data.message) || 'Failed to send test email.'));
                    }
                },
                error: function() {
                    btn.prop('disabled', false).html('<i class="fa-solid fa-paper-plane"></i> Send Test Now');
                    box.show().css({background: '#FEE2E2', color: '#991B1B'}).html('<i class="fa-solid fa-triangle-exclamation"></i> Network error communicating with server.');
                }
            });
        }
        </script>
        <?php
    }
}
