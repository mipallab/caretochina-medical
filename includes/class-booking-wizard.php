<?php
if (!defined('ABSPATH')) {
    exit;
}

class CareToChina_Booking_Wizard {
    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_shortcode('caretochina_booking_wizard', [$this, 'render_wizard']);
        add_shortcode('careyou_booking_wizard', [$this, 'render_wizard']); // Backward-compatibility alias

        add_action('wp_ajax_caretochina_submit_booking', [$this, 'handle_booking_submission']);
        add_action('wp_ajax_nopriv_caretochina_submit_booking', [$this, 'handle_booking_submission']);

        // Legacy AJAX action aliases
        add_action('wp_ajax_careyou_submit_booking', [$this, 'handle_booking_submission']);
        add_action('wp_ajax_nopriv_careyou_submit_booking', [$this, 'handle_booking_submission']);

        // Render modal globally in the footer
        add_action('wp_footer', [$this, 'render_booking_modal_in_footer']);
    }

    public function render_wizard($atts = []) {
        $atts = shortcode_atts([
            'label' => __('Request Free Quote & Consultation', 'caretochina-medical'),
            'class' => '',
        ], $atts, 'caretochina_booking_wizard');

        ob_start();
        ?>
        <div class="ctc-wizard-shortcode-trigger">
            <button type="button" class="ctc-trigger-booking ctc-solid-btn btn-teal-primary <?php echo esc_attr($atts['class']); ?>" onclick="if(typeof appWizard !== 'undefined') appWizard.openScenario1();">
                <i class="fa-solid fa-wand-magic-sparkles"></i> <?php echo esc_html($atts['label']); ?>
            </button>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_booking_modal_in_footer() {
        $is_logged_in = is_user_logged_in();
        $current_user = wp_get_current_user();
        $user_id = ($is_logged_in && $current_user) ? $current_user->ID : 0;
        
        $profile_name      = $is_logged_in ? ($current_user->display_name ?: $current_user->user_login) : '';
        $profile_email     = $is_logged_in ? $current_user->user_email : '';
        $profile_country   = $user_id ? get_user_meta($user_id, 'patient_country', true) : '';
        $profile_phone     = $user_id ? (get_user_meta($user_id, 'patient_phone', true) ?: get_user_meta($user_id, 'billing_phone', true)) : '';
        $profile_gender    = $user_id ? get_user_meta($user_id, 'patient_gender', true) : '';
        $profile_age       = $user_id ? get_user_meta($user_id, 'patient_age', true) : '';
        $profile_whatsapp  = $user_id ? get_user_meta($user_id, 'patient_whatsapp', true) : '';
        $profile_wechat    = $user_id ? get_user_meta($user_id, 'patient_wechat', true) : '';
        $profile_messenger = $user_id ? get_user_meta($user_id, 'patient_messenger', true) : '';
        $profile_linkedin  = $user_id ? get_user_meta($user_id, 'patient_linkedin', true) : '';
        
        ?>
        <!-- CARETOCHINA BOOKING MODAL POPUP -->
        <div id="ctc-booking-modal" class="ctc-booking-modal-overlay caretochina-booking-wizard-container" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="wiz-modal-title">
            <div class="ctc-booking-modal-container">
                <button type="button" class="ctc-booking-modal-close" onclick="appWizard.closeModal()" aria-label="<?php esc_attr_e('Close booking wizard', 'caretochina-medical'); ?>" role="button">&times;</button>
                
                <div class="wiz-header text-center">
                    <?php $brand_logo = get_option('ctc_brand_logo_url', ''); if (!empty($brand_logo)) : ?>
                        <div class="wiz-brand-logo-wrap">
                            <img src="<?php echo esc_url($brand_logo); ?>" alt="CareToChina Logo" class="wiz-brand-logo">
                        </div>
                    <?php endif; ?>
                    <span class="badge-pill ctc-badge-pill"><i class="fa-solid fa-wand-magic-sparkles"></i> <?php esc_html_e('CareToChina Consultation Wizard', 'caretochina-medical'); ?></span>
                    <h2 id="wiz-modal-title" class="section-title wiz-modal-title"><?php esc_html_e('Medical Consultation Booking', 'caretochina-medical'); ?></h2>
                    <p class="section-subtitle text-muted wiz-modal-subtitle">
                        <?php esc_html_e('Select your service package and consultation details in 2 simple steps. Our medical team coordinates every aspect of your journey.', 'caretochina-medical'); ?>
                    </p>
                </div>

                <!-- STEP INDICATORS (2 STEPS) -->
                <div class="wizard-steps-indicator">
                    <div class="wiz-step active" data-step="1">
                        <span class="wiz-step-num">1</span>
                        <span class="wiz-step-label"><span class="wiz-lbl-short"><?php esc_html_e('Package', 'caretochina-medical'); ?></span><span class="wiz-lbl-full"><?php esc_html_e('Service Package', 'caretochina-medical'); ?></span></span>
                    </div>
                    <div class="wiz-step-divider"></div>
                    <div class="wiz-step" data-step="2">
                        <span class="wiz-step-num">2</span>
                        <span class="wiz-step-label"><span class="wiz-lbl-short"><?php esc_html_e('Details', 'caretochina-medical'); ?></span><span class="wiz-lbl-full"><?php esc_html_e('Patient Details', 'caretochina-medical'); ?></span></span>
                    </div>
                </div>

                <form id="ctc-booking-wizard-form">
                    <!-- HIDDEN FIELDS FOR FORM SUBMISSION -->
                    <input type="hidden" name="hospital_id" id="wiz_hospital_id" value="0">
                    <input type="hidden" name="hospital_name" id="wiz_hospital_name" value="">
                    <input type="hidden" name="package_id" id="wiz_package_id" value="0">
                    <input type="hidden" name="package_name" id="wiz_package_name" value="">
                    <input type="hidden" name="package_price" id="wiz_package_price" value="0">
                    
                    <!-- STEP 1: SELECT SERVICE PACKAGE -->
                    <div class="wiz-page active" id="wiz-step-1">
                        <div class="form-group mb-16">
                            <label class="form-label wiz-plan-title"><?php esc_html_e('Select Your Service Package (Optional)', 'caretochina-medical'); ?></label>
                            <p class="wiz-plan-subtext"><?php esc_html_e('Choose an authorized medical escort & concierge tier, or skip to request a general consultation.', 'caretochina-medical'); ?></p>
                            
                            <div id="wiz-packages-list-grid" class="wiz-packages-grid">
                                <!-- Populated Dynamically via JS / Localized Object -->
                            </div>

                            <!-- Pricing Page CTA Link (Opens in New Tab) -->
                            <div class="wiz-pricing-cta-wrap">
                                <a href="<?php echo esc_url(home_url('/pricing/')); ?>" target="_blank" rel="noopener noreferrer" class="wiz-pricing-cta-btn">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i> <?php esc_html_e('View Full Pricing & Plan Details', 'caretochina-medical'); ?>
                                </a>
                            </div>
                        </div>

                        <div class="wiz-action-footer">
                            <button type="button" class="ctc-solid-btn btn-wiz-secondary" onclick="appWizard.skipPackage()"><i class="fa-solid fa-forward"></i> <?php esc_html_e('Skip Package & Continue', 'caretochina-medical'); ?></button>
                            <button type="button" class="ctc-solid-btn btn-teal-primary btn-wiz-primary" onclick="appWizard.nextStep(2)"><?php esc_html_e('Next: Patient Details', 'caretochina-medical'); ?> <i class="fa-solid fa-arrow-right"></i></button>
                        </div>
                    </div>

                    <!-- STEP 2: PATIENT DETAILS & DIRECT CONFIRMATION -->
                    <div class="wiz-page" id="wiz-step-2" style="display:none;">
                        <div class="wiz-step3-fields-scroll">
                            <div class="form-group mb-12">
                                <label class="form-label"><?php esc_html_e('Briefly describe your condition or medical needs *', 'caretochina-medical'); ?></label>
                                <textarea name="quote_details" id="wiz_quote_details" class="form-input" rows="3" required placeholder="<?php esc_html_e('Enter details about your symptoms, medical history, diagnosis, or travel requirements...', 'caretochina-medical'); ?>"></textarea>
                            </div>
                            
                            <div class="ctc-form-grid-2">
                                <div class="form-group">
                                    <label class="form-label"><?php esc_html_e('Full Name *', 'caretochina-medical'); ?></label>
                                    <input type="text" name="full_name" id="wiz_full_name" class="form-input" value="<?php echo esc_attr($profile_name); ?>" required placeholder="<?php esc_html_e('Sarah Jenkins', 'caretochina-medical'); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label"><?php esc_html_e('Country *', 'caretochina-medical'); ?></label>
                                    <input type="text" name="country" id="wiz_country" class="form-input" value="<?php echo esc_attr($profile_country); ?>" required placeholder="<?php esc_html_e('United States', 'caretochina-medical'); ?>">
                                </div>
                            </div>

                            <div class="ctc-form-grid-2">
                                <div class="form-group">
                                    <label class="form-label"><?php esc_html_e('Age', 'caretochina-medical'); ?></label>
                                    <input type="number" name="age" id="wiz_age" class="form-input" value="<?php echo esc_attr($profile_age); ?>" placeholder="<?php esc_html_e('e.g. 35', 'caretochina-medical'); ?>" min="0" max="130">
                                </div>
                                <div class="form-group">
                                    <label class="form-label"><?php esc_html_e('Gender *', 'caretochina-medical'); ?></label>
                                    <select name="gender" id="wiz_gender" class="form-select" required>
                                        <option value=""><?php esc_html_e('Select Gender', 'caretochina-medical'); ?></option>
                                        <option value="Male" <?php selected($profile_gender, 'Male'); ?>><?php esc_html_e('Male', 'caretochina-medical'); ?></option>
                                        <option value="Female" <?php selected($profile_gender, 'Female'); ?>><?php esc_html_e('Female', 'caretochina-medical'); ?></option>
                                        <option value="Other" <?php selected($profile_gender, 'Other'); ?>><?php esc_html_e('Other', 'caretochina-medical'); ?></option>
                                    </select>
                                </div>
                            </div>

                            <div class="ctc-form-grid-2">
                                <div class="form-group">
                                    <label class="form-label"><?php esc_html_e('Email Address *', 'caretochina-medical'); ?></label>
                                    <input type="email" name="email" id="wiz_email" class="form-input" value="<?php echo esc_attr($profile_email); ?>" required placeholder="name@example.com">
                                </div>
                                <div class="form-group">
                                    <label class="form-label"><?php esc_html_e('Phone Number *', 'caretochina-medical'); ?></label>
                                    <?php 
                                    if (class_exists('CareToChina_Country_Helper')) { 
                                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped HTML from CareToChina_Country_Helper.
                                        echo CareToChina_Country_Helper::render_phone_input_group('phone', $profile_phone, true, '555-0199', 'wiz_phone'); 
                                        ?>
                                        <div class="ctc-phone-guide-hint">
                                            <span>💡 <strong><?php esc_html_e('Tip:', 'caretochina-medical'); ?></strong> <?php esc_html_e('Click the flag to choose country code or type number.', 'caretochina-medical'); ?></span>
                                        </div>
                                        <?php
                                    } else { 
                                        echo '<input type="tel" name="phone" id="wiz_phone" class="form-input" value="' . esc_attr($profile_phone) . '" required placeholder="+1 (800) 555-0199">'; 
                                    } 
                                    ?>
                                </div>
                            </div>

                            <div class="ctc-form-grid-2">
                                <div class="form-group">
                                    <label class="form-label"><?php esc_html_e('WhatsApp Number (Optional)', 'caretochina-medical'); ?></label>
                                    <?php 
                                    if (class_exists('CareToChina_Country_Helper')) { 
                                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped HTML from CareToChina_Country_Helper.
                                        echo CareToChina_Country_Helper::render_phone_input_group('whatsapp', $profile_whatsapp, false, '555-0199', 'wiz_whatsapp'); 
                                    } else { 
                                        echo '<input type="tel" name="whatsapp" id="wiz_whatsapp" class="form-input" value="' . esc_attr($profile_whatsapp) . '" placeholder="+1 (800) 555-0199">'; 
                                    } 
                                    ?>
                                </div>
                                <div class="form-group">
                                    <label class="form-label"><?php esc_html_e('WeChat / Messenger ID (Optional)', 'caretochina-medical'); ?></label>
                                    <input type="text" name="wechat" id="wiz_wechat" class="form-input" value="<?php echo esc_attr($profile_wechat); ?>" placeholder="<?php esc_html_e('WeChat ID or Messenger handle', 'caretochina-medical'); ?>">
                                </div>
                            </div>

                            <input type="hidden" name="messenger" id="wiz_messenger" value="<?php echo esc_attr($profile_messenger); ?>">
                            <input type="hidden" name="linkedin" id="wiz_linkedin" value="<?php echo esc_attr($profile_linkedin); ?>">

                            <?php if (class_exists('CareToChina_Recaptcha')) { echo CareToChina_Recaptcha::render_field($is_logged_in ? 'booking' : 'guest_booking'); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ } ?>
                        </div>

                        <div class="wiz-action-footer">
                            <button type="button" class="ctc-solid-btn btn-wiz-secondary" onclick="appWizard.nextStep(1)"><i class="fa-solid fa-arrow-left"></i> <?php esc_html_e('Back to Packages', 'caretochina-medical'); ?></button>
                            <button type="submit" id="ctc-wizard-submit-btn" class="ctc-solid-btn btn-teal-primary btn-wiz-primary"><i class="fa-solid fa-check-circle"></i> <?php echo esc_html($is_logged_in ? __('Confirm & Submit Booking', 'caretochina-medical') : __('Complete Booking Request', 'caretochina-medical')); ?></button>
                        </div>
                    </div>
                </form>
                <div id="ctc-wizard-status" style="display:none; margin-top:20px; text-align:center;"></div>
            </div>
        </div>
        <?php
    }

    public function handle_booking_submission() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'caretochina_booking_nonce') && !wp_verify_nonce($nonce, 'careyou_booking_nonce')) {
            wp_send_json_error(['message' => __('Security verification failed.', 'caretochina-medical')]);
        }

        // Rate limiting for guest submissions
        if (!is_user_logged_in()) {
            $ip = sanitize_text_field(sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? '')));
            $rate_key = 'ctc_rate_limit_guest_booking_' . md5($ip);
            $count = intval(get_transient($rate_key) ?: 0);
            if ($count >= 6) {
                wp_send_json_error(['message' => __('Too many booking attempts. Please wait 10 minutes and try again.', 'caretochina-medical')]);
            }
            set_transient($rate_key, $count + 1, 600);
        }

        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';

        // Retrieve and sanitize fields
        $hospital_id       = 0;
        $hospital_name     = '';
        $package_id        = isset($_POST['package_id']) ? absint(wp_unslash($_POST['package_id'])) : 0;
        $quote_details     = isset($_POST['quote_details']) ? sanitize_textarea_field(wp_unslash($_POST['quote_details'])) : '';
        $country           = isset($_POST['country']) ? sanitize_text_field(wp_unslash($_POST['country'])) : '';
        
        $full_name         = isset($_POST['full_name']) ? sanitize_text_field(wp_unslash($_POST['full_name'])) : '';
        $age               = (isset($_POST['age']) && $_POST['age'] !== '') ? intval(wp_unslash($_POST['age'])) : null;
        $gender            = isset($_POST['gender']) ? sanitize_text_field(wp_unslash($_POST['gender'])) : '';
        
        $email             = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $phone             = class_exists('CareToChina_Country_Helper') ? CareToChina_Country_Helper::extract_submitted_phone($_POST, 'phone') : (isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '');
        
        $whatsapp          = class_exists('CareToChina_Country_Helper') ? CareToChina_Country_Helper::extract_submitted_phone($_POST, 'whatsapp') : (isset($_POST['whatsapp']) ? sanitize_text_field(wp_unslash($_POST['whatsapp'])) : '');
        $wechat            = isset($_POST['wechat']) ? sanitize_text_field(wp_unslash($_POST['wechat'])) : '';
        $messenger         = isset($_POST['messenger']) ? sanitize_text_field(wp_unslash($_POST['messenger'])) : '';
        $linkedin          = isset($_POST['linkedin']) ? sanitize_text_field(wp_unslash($_POST['linkedin'])) : '';

        // Validation checks
        if (empty($full_name) || empty($email) || empty($phone) || empty($gender) || empty($quote_details)) {
            wp_send_json_error(['message' => __('Please fill in all required fields (Full Name, Gender, Email, Phone, Description).', 'caretochina-medical')]);
        }

        // Google reCAPTCHA Verification
        if (class_exists('CareToChina_Recaptcha')) {
            $recaptcha_token = isset($_POST['g-recaptcha-response']) ? sanitize_text_field(wp_unslash($_POST['g-recaptcha-response'])) : '';
            $rc_loc = is_user_logged_in() ? 'booking' : 'guest_booking';
            $rc_check = CareToChina_Recaptcha::verify_submission($recaptcha_token, $rc_loc);
            if (is_wp_error($rc_check)) {
                wp_send_json_error(['message' => $rc_check->get_error_message()]);
            }
        }

        $booking_code = 'CTC-' . strtoupper(substr(md5(uniqid(wp_rand(), true)), 0, 6));
        
        $patient_id = 0;
        $is_guest   = 1;
        $raw_guest_token = '';
        $guest_token_hash = '';
        $snapshotted_price = 0.00;
        $currency = class_exists('CareToChina_Packages') ? CareToChina_Packages::get_store_currency() : get_option('ctc_payment_currency', 'USD');
        $package_title = '';
        $package_price_formatted = '';
        $package_timeline = '';

        if ($package_id > 0 && class_exists('CareToChina_Packages')) {
            $pkg = CareToChina_Packages::instance()->get_package($package_id);
            if ($pkg) {
                $snapshotted_price       = floatval($pkg->price);
                $package_title           = $pkg->name;
                $package_price_formatted = $pkg->price_formatted;
                $package_timeline        = $pkg->timeline ?? '';
                $currency                = $pkg->currency ?: $currency;
                $quote_details          .= "\n[Selected Service Package: " . $pkg->name . ' (' . $pkg->price_formatted . ')]';
            }
        }

        if (is_user_logged_in()) {
            $patient_id = get_current_user_id();
            $is_guest   = 0;
        } else {
            $raw_guest_token = bin2hex(random_bytes(32));
            $guest_token_hash = hash('sha256', $raw_guest_token);
        }

        $specialty_label = $package_title ?: __('Medical Service Consultation', 'caretochina-medical');

        // DUPLICATE CONSULTATION / BOOKING PROTECTION
        $lock_key = 'ctc_sub_lock_' . md5($email . '|' . strtolower($specialty_label));
        if (get_transient($lock_key)) {
            wp_send_json_error(['message' => __('A booking request is currently being processed. Please wait a moment.', 'caretochina-medical')]);
        }
        set_transient($lock_key, 1, 10);

        // Active Consultation / Booking Duplicate Check
        $existing_active = null;
        if ($patient_id > 0) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $existing_active = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}caretochina_bookings WHERE (patient_id = %d OR email = %s) AND package_id = %d AND status IN ('pending', 'confirmed', 'waiting') ORDER BY id DESC LIMIT 1",
                $patient_id, $email, $package_id
            ));
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $existing_active = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}caretochina_bookings WHERE email = %s AND package_id = %d AND status IN ('pending', 'confirmed', 'waiting') ORDER BY id DESC LIMIT 1",
                $email, $package_id
            ));
        }

        if ($existing_active && $package_id > 0) {
            delete_transient($lock_key);
            $dash_url = class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_url('patient_dashboard') : home_url('/patient-dashboard/');
            $existing_chat_url = (!empty($existing_active->is_guest)) ? add_query_arg([
                'booking_code' => $existing_active->booking_code,
            ], $dash_url) : $dash_url;

            /* translators: 1: Booking Code, 2: Specialty Name */
            $duplicate_active_msg = sprintf(__('You already have an active booking (#%1$s) for %2$s. Redirecting to your consultation desk...', 'caretochina-medical'), $existing_active->booking_code, $existing_active->specialty);

            wp_send_json_success([
                'booking_id'      => $existing_active->id,
                'booking_code'    => $existing_active->booking_code,
                'is_guest'        => (bool) $existing_active->is_guest,
                'chat_url'        => $existing_chat_url,
                'amount'          => floatval($existing_active->amount),
                'currency'        => $existing_active->currency ?: $currency,
                'specialty'       => $existing_active->specialty,
                'already_active'  => true,
                'message'         => $duplicate_active_msg
            ]);
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $inserted = $wpdb->insert($table_bookings, [
            'booking_code'     => $booking_code,
            'patient_id'       => $patient_id,
            'is_guest'         => $is_guest,
            'guest_token_hash' => $guest_token_hash,
            'hospital_id'      => 0,
            'hospital_name'    => '',
            'specialty'        => $specialty_label,
            'package_id'       => $package_id,
            'treatment_timing' => 'Flexible',
            'quote_details'    => $quote_details,
            'full_name'        => $full_name,
            'country'          => $country,
            'age'              => $age,
            'gender'           => $gender,
            'email'            => $email,
            'phone'            => $phone,
            'whatsapp'         => $whatsapp,
            'wechat'           => $wechat,
            'messenger'        => $messenger,
            'linkedin'         => $linkedin,
            'amount'           => $snapshotted_price,
            'currency'         => $currency,
            'status'           => 'pending',
            'created_at'       => current_time('mysql'),
        ], [
            '%s', '%d', '%d', '%s', '%d', '%s', '%s', '%d', '%s', '%s',
            '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
            '%f', '%s', '%s', '%s',
        ]);

        if ($inserted === false) {
            delete_transient($lock_key);
            wp_send_json_error(['message' => __('Database error saving booking. Please try again.', 'caretochina-medical')]);
        }

        $booking_id = $wpdb->insert_id;
        delete_transient($lock_key);

        // Auto-Generate Initial Patient & Admin Initial Messages
        $table_messages = $wpdb->prefix . 'caretochina_messages';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert($table_messages, [
            'booking_id'   => $booking_id,
            'sender_id'    => $patient_id,
            'sender_type'  => 'patient',
            'message_text' => sprintf(
                __("Initial Inquiry / Medical Request:\n%s\n\nContact Details:\nPhone: %s\nWhatsApp: %s\nWeChat/Messenger: %s", 'caretochina-medical'),
                $quote_details,
                $phone,
                $whatsapp ?: __('N/A', 'caretochina-medical'),
                $wechat ?: __('N/A', 'caretochina-medical')
            ),
            'created_at'   => current_time('mysql'),
        ], ['%d', '%d', '%s', '%s', '%s']);

        // System Welcome Message
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert($table_messages, [
            'booking_id'   => $booking_id,
            'sender_id'    => 0,
            'sender_type'  => 'admin',
            'message_text' => sprintf(
                __("Hello %s! Thank you for contacting CareToChina Medical. Your case #%s for %s has been received. Our medical concierge team is currently reviewing your medical details and will respond shortly with next steps and consultation itinerary.", 'caretochina-medical'),
                $full_name,
                $booking_code,
                $specialty_label
            ),
            'created_at'   => current_time('mysql'),
        ], ['%d', '%d', '%s', '%s', '%s']);

        // Save Guest Token in Cookie for Instant Dashboard Auto-Authentication
        if ($is_guest && !empty($raw_guest_token)) {
            $cookie_name = 'ctc_guest_token_' . $booking_code;
            $cookie_expiry = time() + (30 * DAY_IN_SECONDS);
            $secure = is_ssl();
            setcookie($cookie_name, $raw_guest_token, [
                'expires'  => $cookie_expiry,
                'path'     => COOKIEPATH,
                'domain'   => COOKIE_DOMAIN,
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }

        // Asynchronous Notification Delivery (Patient Confirmation + Admin Alert)
        if (class_exists('CareToChina_Async_Mailer')) {
            CareToChina_Async_Mailer::dispatch_booking_created($booking_id, $raw_guest_token);
        }

        $dash_url = class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_url('patient_dashboard') : home_url('/patient-dashboard/');
        
        $chat_url = ($is_guest && !empty($raw_guest_token)) ? add_query_arg([
            'booking_code' => $booking_code,
            'ctc_token'    => $raw_guest_token,
        ], $dash_url) : $dash_url;

        // Clean user-friendly success response
        wp_send_json_success([
            'booking_id'      => $booking_id,
            'booking_code'    => $booking_code,
            'is_guest'        => (bool) $is_guest,
            'guest_token'     => $raw_guest_token,
            'chat_url'        => $chat_url,
            'amount'          => $snapshotted_price,
            'currency'        => $currency,
            'specialty'       => $specialty_label,
            'message'         => __('Booking submitted successfully! Redirecting to your personal consultation desk...', 'caretochina-medical')
        ]);
    }
}