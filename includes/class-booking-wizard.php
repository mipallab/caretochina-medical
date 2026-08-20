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
            'label' => __('Request Free Quote & Consultation', 'caretochina-booking'),
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

    public function get_hospitals_data() {
        $hospitals_query = new WP_Query([
            'post_type' => 'hospital',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ]);

        $hospitals = [];
        if ($hospitals_query->have_posts()) {
            while ($hospitals_query->have_posts()) {
                $hospitals_query->the_post();
                $id = get_the_ID();
                
                $cities = wp_get_post_terms($id, 'hospital_city', ['fields' => 'all']);
                $specialties = wp_get_post_terms($id, 'hospital_specialty', ['fields' => 'all']);
                $departments = wp_get_post_terms($id, 'hospital_department', ['fields' => 'all']);

                $cities_arr = [];
                if (!is_wp_error($cities)) {
                    foreach ($cities as $c) {
                        $cities_arr[] = ['id' => $c->term_id, 'name' => $c->name, 'slug' => $c->slug];
                    }
                }

                $specialties_arr = [];
                if (!is_wp_error($specialties)) {
                    foreach ($specialties as $s) {
                        $specialties_arr[] = ['id' => $s->term_id, 'name' => $s->name, 'slug' => $s->slug];
                    }
                }

                $departments_arr = [];
                if (!is_wp_error($departments)) {
                    foreach ($departments as $d) {
                        $departments_arr[] = ['id' => $d->term_id, 'name' => $d->name, 'slug' => $d->slug];
                    }
                }

                $hospitals[] = [
                    'id' => $id,
                    'title' => get_the_title(),
                    'image' => has_post_thumbnail() ? get_the_post_thumbnail_url($id, 'medium_large') : 'https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?auto=format&fit=crop&w=800&q=80',
                    'cities' => $cities_arr,
                    'specialties' => $specialties_arr,
                    'departments' => $departments_arr,
                    'type' => get_post_meta($id, '_hospital_type', true) ?: 'General Medical Center',
                    'rating' => get_post_meta($id, '_hospital_rating', true) ?: '4.9 (1,240 Reviews)',
                    'certification' => get_post_meta($id, '_hospital_certification', true) ?: 'JCI Certified',
                ];
            }
            wp_reset_postdata();
        }
        return $hospitals;
    }

    public function get_all_specialties() {
        $terms = get_terms([
            'taxonomy' => 'hospital_specialty',
            'hide_empty' => false,
        ]);
        $specialties = [];
        if (!is_wp_error($terms)) {
            foreach ($terms as $t) {
                $specialties[] = ['id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug];
            }
        }
        return $specialties;
    }

    public function get_all_cities() {
        $terms = get_terms([
            'taxonomy' => 'hospital_city',
            'hide_empty' => false,
        ]);
        $cities = [];
        if (!is_wp_error($terms)) {
            foreach ($terms as $t) {
                $cities[] = ['id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug];
            }
        }
        return $cities;
    }

    public function render_booking_modal_in_footer() {
        $is_logged_in = is_user_logged_in();
        $current_user = wp_get_current_user();
        $user_id = ($is_logged_in && $current_user) ? $current_user->ID : 0;
        
        $profile_name = $is_logged_in ? $current_user->display_name : '';
        $profile_email = $is_logged_in ? $current_user->user_email : '';
        $profile_phone = $user_id ? get_user_meta($user_id, 'patient_phone', true) : '';
        $profile_gender = $user_id ? get_user_meta($user_id, 'patient_gender', true) : '';
        $profile_age = $user_id ? get_user_meta($user_id, 'patient_age', true) : '';
        
        ?>
        <!-- CARETOCHINA BOOKING MODAL POPUP -->
        <div id="ctc-booking-modal" class="ctc-booking-modal-overlay caretochina-booking-wizard-container" style="display:none;">
            <div class="ctc-booking-modal-container">
                <button type="button" class="ctc-booking-modal-close" onclick="appWizard.closeModal()">&times;</button>
                
                <div class="wiz-header text-center" style="text-align:center; margin-bottom: 24px;">
                    <?php $brand_logo = get_option('ctc_brand_logo_url', ''); if (!empty($brand_logo)) : ?>
                        <div style="margin-bottom:12px; display:flex; justify-content:center;">
                            <img src="<?php echo esc_url($brand_logo); ?>" alt="CareToChina Logo" style="max-height:42px; max-width:200px; object-fit:contain;">
                        </div>
                    <?php endif; ?>
                    <span class="badge-pill" style="background:#CCFBF1; color:#0F766E; padding:6px 14px; font-weight:700; font-size:12px; border-radius:999px;"><i class="fa-solid fa-wand-magic-sparkles"></i> <?php _e('CareToChina Consultation Wizard', 'caretochina-booking'); ?></span>
                    <h2 class="section-title" style="font-family:var(--cymb-font-heading); color:var(--cymb-text-dark); margin: 24px 0 16px 0; font-size:26px; font-weight:800;"><?php _e('Instant Medical Travel Booking', 'caretochina-booking'); ?></h2>
                    <p class="section-subtitle text-muted" style="margin-bottom:30px; font-size:15px; color:#64748B;">
                        <?php echo $is_logged_in ? __('Lock in guaranteed treatment packages in 4 easy steps.', 'caretochina-booking') : __('Submit your medical travel inquiry in 3 quick steps. Our coordinators will review your case and send a tailored package.', 'caretochina-booking'); ?>
                    </p>
                </div>

                <!-- STEP INDICATORS -->
                <div class="wizard-steps-indicator" style="display:flex; justify-content:space-between; margin-bottom:30px; border: 1px solid transparent; border-bottom:1px solid var(--cymb-border-color); padding-bottom:12px; font-family:var(--cymb-font-heading);">
                    <div class="wiz-step active" data-step="1" style="font-size:12px;"><?php _e('1. Hospital', 'caretochina-booking'); ?></div>
                    <div class="wiz-step" data-step="2" style="font-size:12px;"><?php _e('2. Specialty & Timing', 'caretochina-booking'); ?></div>
                    <?php if ($is_logged_in) : ?>
                        <div class="wiz-step" data-step="3" style="font-size:12px;"><?php _e('3. Pricing Plan', 'caretochina-booking'); ?></div>
                        <div class="wiz-step" data-step="4" style="font-size:12px;"><?php _e('4. Patient Details & Confirm', 'caretochina-booking'); ?></div>
                    <?php else : ?>
                        <div class="wiz-step" data-step="3" style="font-size:12px;"><?php _e('3. Patient Details & Submit', 'caretochina-booking'); ?></div>
                    <?php endif; ?>
                </div>

                <form id="ctc-booking-wizard-form">
                    <!-- HIDDEN FIELD FOR CURRENT SCREEN MODE AND SELECTIONS -->
                    <input type="hidden" name="hospital_id" id="wiz_hospital_id" value="0">
                    <input type="hidden" name="hospital_name" id="wiz_hospital_name" value="">
                    <input type="hidden" name="selected_treatment_id" id="wiz_selected_treatment_id" value="0">
                    <input type="hidden" name="pricing_plan_id" id="wiz_pricing_plan_id" value="0">
                    <input type="hidden" name="pricing_plan_name" id="wiz_pricing_plan_name" value="">
                    <input type="hidden" name="pricing_plan_price" id="wiz_pricing_plan_price" value="0">
                    
                    <!-- STEP 1: SELECT HOSPITAL -->
                    <div class="wiz-page active" id="wiz-step-1">
                        <div class="wiz-step-filters" style="display:flex; gap:12px; margin-bottom:20px;">
                            <input type="text" id="wiz-hospital-search" class="form-input" placeholder="<?php _e('Search hospitals...', 'caretochina-booking'); ?>" style="flex:2;">
                            <select id="wiz-hospital-city-filter" class="form-select" style="flex:1;">
                                <option value=""><?php _e('All Cities', 'caretochina-booking'); ?></option>
                            </select>
                        </div>
                        
                        <div id="wiz-hospital-list-grid" class="wiz-hospital-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; max-height:280px; overflow-y:auto; padding-right:6px; margin-bottom:20px;">
                            <!-- Populated Dynamically via JS -->
                        </div>
                        
                        <div class="wiz-action-footer">
                            <button type="button" class="ctc-solid-btn btn-wiz-secondary" onclick="appWizard.skipHospital()"><?php _e('Skip Hospital & Continue', 'caretochina-booking'); ?></button>
                            <button type="button" class="ctc-solid-btn btn-teal-primary btn-wiz-primary" onclick="appWizard.nextStep(2)"><?php _e('Next Step', 'caretochina-booking'); ?> <i class="fa-solid fa-arrow-right"></i></button>
                        </div>
                    </div>

                    <!-- STEP 2: TREATMENT TIMING & SPECIALTY -->
                    <div class="wiz-page" id="wiz-step-2" style="display:none;">
                        <div class="form-group">
                            <label class="form-label" style="font-weight:700;"><?php _e('When do you need treatment? *', 'caretochina-booking'); ?></label>
                            <div class="timing-tags-grid" style="display:grid; grid-template-columns:repeat(4, 1fr); gap:10px; margin-top:8px; margin-bottom:20px;">
                                <button type="button" class="timing-tag-btn" data-value="As soon as possible" onclick="appWizard.selectTiming(this)"><?php _e('As soon as possible', 'caretochina-booking'); ?></button>
                                <button type="button" class="timing-tag-btn" data-value="Within 1 month" onclick="appWizard.selectTiming(this)"><?php _e('Within 1 month', 'caretochina-booking'); ?></button>
                                <button type="button" class="timing-tag-btn" data-value="1–3 months" onclick="appWizard.selectTiming(this)"><?php _e('1–3 months', 'caretochina-booking'); ?></button>
                                <button type="button" class="timing-tag-btn" data-value="3–6+ months" onclick="appWizard.selectTiming(this)"><?php _e('3–6+ months', 'caretochina-booking'); ?></button>
                            </div>
                            <input type="hidden" name="treatment_timing" id="wiz_treatment_timing" value="">
                        </div>

                        <div class="form-group">
                            <label class="form-label" style="font-weight:700; margin-bottom:8px;"><?php _e('Select Required Specialty / Treatment *', 'caretochina-booking'); ?></label>
                            <div id="wiz-specialty-checkbox-list" class="specialty-checkbox-list" style="display:grid; grid-template-columns:1fr 1fr; gap:10px; max-height:160px; overflow-y:auto; padding-right:4px;">
                                <!-- Dynamic check list loaded by JS -->
                            </div>
                        </div>

                        <div class="wiz-action-footer">
                            <button type="button" class="ctc-solid-btn btn-wiz-secondary" id="wiz-back-btn-step-2" onclick="appWizard.nextStep(1)"><i class="fa-solid fa-arrow-left"></i> <?php _e('Back', 'caretochina-booking'); ?></button>
                            <button type="button" class="ctc-solid-btn btn-teal-primary btn-wiz-primary" onclick="appWizard.nextStep(<?php echo $is_logged_in ? '3' : '4'; ?>)"><?php _e('Next Step', 'caretochina-booking'); ?> <i class="fa-solid fa-arrow-right"></i></button>
                        </div>
                    </div>

                    <?php if ($is_logged_in) : ?>
                        <!-- STEP 3: SELECT PRICING PLAN (LOGGED-IN ONLY) -->
                        <div class="wiz-page" id="wiz-step-3" style="display:none;">
                            <div class="form-group mb-16">
                                <label class="form-label wiz-plan-title" style="font-weight:700; font-size:15px; margin-bottom:4px;"><?php _e('Select Your Treatment Tier / Package *', 'caretochina-booking'); ?></label>
                                <p class="wiz-plan-subtext" style="font-size:13px; margin-top:0; margin-bottom:16px;"><?php _e('Choose an authorized treatment package. All prices are guaranteed and locked onto your booking.', 'caretochina-booking'); ?></p>
                                <div id="wiz-pricing-plans-grid" class="wiz-pricing-plans-grid" style="display:grid; grid-template-columns:1fr; gap:12px; max-height:280px; overflow-y:auto; padding-right:4px; margin-bottom:10px;">
                                    <!-- Loaded dynamically via AJAX ctc_get_treatment_plans -->
                                </div>
                                <div id="wiz-pricing-plans-empty" class="wiz-pricing-plans-empty-box" style="display:none;">
                                    <i class="fa-solid fa-tags wiz-pricing-plans-empty-icon"></i>
                                    <span class="wiz-pricing-plans-empty-text"><?php _e('Standard Consultation Package ($500.00 Deposit) will be applied for this inquiry.', 'caretochina-booking'); ?></span>
                                </div>
                            </div>

                            <div class="wiz-action-footer">
                                <button type="button" class="ctc-solid-btn btn-wiz-secondary" onclick="appWizard.nextStep(2)"><i class="fa-solid fa-arrow-left"></i> <?php _e('Back', 'caretochina-booking'); ?></button>
                                <button type="button" class="ctc-solid-btn btn-teal-primary btn-wiz-primary" onclick="appWizard.nextStep(4)"><?php _e('Next Step', 'caretochina-booking'); ?> <i class="fa-solid fa-arrow-right"></i></button>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- STEP 4: PATIENT DETAILS & DIRECT CONFIRMATION -->
                    <div class="wiz-page" id="wiz-step-4" style="display:none;">
                        <div class="form-group mb-12">
                            <label class="form-label" style="font-weight:700;"><?php _e('Briefly describe your condition or medical needs *', 'caretochina-booking'); ?></label>
                            <textarea name="quote_details" id="wiz_quote_details" class="form-input" rows="2" required placeholder="<?php _e('Enter any details about your symptoms, medical history, or questions...', 'caretochina-booking'); ?>"></textarea>
                        </div>
                        
                        <div class="ctc-form-grid-2">
                            <div class="form-group">
                                <label class="form-label"><?php _e('Full Name *', 'caretochina-booking'); ?></label>
                                <input type="text" name="full_name" id="wiz_full_name" class="form-input" value="<?php echo esc_attr($profile_name); ?>" required placeholder="<?php _e('Sarah Jenkins', 'caretochina-booking'); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?php _e('Country *', 'caretochina-booking'); ?></label>
                                <input type="text" name="country" id="wiz_country" class="form-input" required placeholder="<?php _e('United States', 'caretochina-booking'); ?>">
                            </div>
                        </div>

                        <div class="ctc-form-grid-2">
                            <div class="form-group">
                                <label class="form-label"><?php _e('Age', 'caretochina-booking'); ?></label>
                                <input type="number" name="age" id="wiz_age" class="form-input" value="<?php echo esc_attr($profile_age); ?>" placeholder="<?php _e('e.g. 35', 'caretochina-booking'); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?php _e('Gender *', 'caretochina-booking'); ?></label>
                                <select name="gender" id="wiz_gender" class="form-select" required>
                                    <option value=""><?php _e('Select Gender', 'caretochina-booking'); ?></option>
                                    <option value="Male" <?php selected($profile_gender, 'Male'); ?>><?php _e('Male', 'caretochina-booking'); ?></option>
                                    <option value="Female" <?php selected($profile_gender, 'Female'); ?>><?php _e('Female', 'caretochina-booking'); ?></option>
                                </select>
                            </div>
                        </div>

                        <?php if (!$is_logged_in) : ?>
                            <div class="ctc-form-grid-2">
                                <div class="form-group">
                                    <label class="form-label"><?php _e('Email Address *', 'caretochina-booking'); ?></label>
                                    <input type="email" name="email" id="wiz_email" class="form-input" required placeholder="name@example.com">
                                </div>
                                <div class="form-group">
                                    <label class="form-label"><?php _e('Phone Number *', 'caretochina-booking'); ?></label>
                                    <?php echo class_exists('CareToChina_Country_Helper') ? CareToChina_Country_Helper::render_phone_input_group('phone', '', true, '+1 (800) 555-0199', 'wiz_phone') : '<input type="tel" name="phone" id="wiz_phone" class="form-input" required placeholder="+1 (800) 555-0199">'; ?>
                                </div>
                            </div>

                            <div class="ctc-form-grid-2">
                                <div class="form-group">
                                    <label class="form-label"><?php _e('WhatsApp Number (Optional)', 'caretochina-booking'); ?></label>
                                    <?php echo class_exists('CareToChina_Country_Helper') ? CareToChina_Country_Helper::render_phone_input_group('whatsapp', '', false, '+1 (800) 555-0199', 'wiz_whatsapp') : '<input type="tel" name="whatsapp" id="wiz_whatsapp" class="form-input" placeholder="+1 (800) 555-0199">'; ?>
                                </div>
                                <div class="form-group">
                                    <label class="form-label"><?php _e('WeChat / Messenger ID (Optional)', 'caretochina-booking'); ?></label>
                                    <input type="text" name="wechat" id="wiz_wechat" class="form-input" placeholder="WeChat ID or Messenger">
                                </div>
                            </div>
                            <input type="hidden" name="messenger" id="wiz_messenger" value="">
                            <input type="hidden" name="linkedin" id="wiz_linkedin" value="">
                        <?php else : ?>
                            <input type="hidden" name="email" id="wiz_email" value="<?php echo esc_attr($profile_email); ?>">
                            <input type="hidden" name="phone" id="wiz_phone" value="<?php echo esc_attr($profile_phone); ?>">
                            <input type="hidden" name="whatsapp" id="wiz_whatsapp" value="<?php echo esc_attr(get_user_meta($user_id, 'patient_whatsapp', true)); ?>">
                            <input type="hidden" name="wechat" id="wiz_wechat" value="<?php echo esc_attr(get_user_meta($user_id, 'patient_wechat', true)); ?>">
                            <input type="hidden" name="messenger" id="wiz_messenger" value="<?php echo esc_attr(get_user_meta($user_id, 'patient_messenger', true)); ?>">
                            <input type="hidden" name="linkedin" id="wiz_linkedin" value="<?php echo esc_attr(get_user_meta($user_id, 'patient_linkedin', true)); ?>">
                        <?php endif; ?>

                        <?php if (class_exists('CareToChina_Recaptcha')) echo CareToChina_Recaptcha::render_field($is_logged_in ? 'booking' : 'guest_booking'); ?>

                        <div class="wiz-action-footer" style="margin-top:20px;">
                            <button type="button" class="ctc-solid-btn btn-wiz-secondary" onclick="appWizard.nextStep(<?php echo $is_logged_in ? '3' : '2'; ?>)"><i class="fa-solid fa-arrow-left"></i> <?php _e('Back', 'caretochina-booking'); ?></button>
                            <button type="submit" id="ctc-wizard-submit-btn" class="ctc-solid-btn btn-teal-primary btn-wiz-primary"><i class="fa-solid fa-check-circle"></i> <?php echo $is_logged_in ? __('Confirm & Submit Booking', 'caretochina-booking') : __('Complete Booking', 'caretochina-booking'); ?></button>
                        </div>
                    </div>
                </form>
                <div id="ctc-wizard-status" style="display:none; margin-top:20px; text-align:center;"></div>
            </div>
        </div>
        <?php
    }

    public function handle_booking_submission() {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_booking_nonce') && !wp_verify_nonce($nonce, 'careyou_booking_nonce')) {
            wp_send_json_error(['message' => __('Security verification failed.', 'caretochina-booking')]);
        }

        // Rate limiting for guest submissions
        if (!is_user_logged_in()) {
            $ip = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
            $rate_key = 'ctc_rate_limit_guest_booking_' . md5($ip);
            $count = intval(get_transient($rate_key) ?: 0);
            if ($count >= 6) {
                wp_send_json_error(['message' => __('Too many booking attempts. Please wait 10 minutes and try again.', 'caretochina-booking')]);
            }
            set_transient($rate_key, $count + 1, 600);
        }

        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';

        // Retrieve and sanitize fields
        $hospital_id       = intval($_POST['hospital_id'] ?? 0);
        $hospital_name     = sanitize_text_field($_POST['hospital_name'] ?? '');
        $specialties       = isset($_POST['specialty']) ? (array)$_POST['specialty'] : [];
        $specialties       = array_map('sanitize_text_field', $specialties);
        $specialty_str     = implode(', ', $specialties);
        
        $pricing_plan_id   = intval($_POST['pricing_plan_id'] ?? 0);
        $treatment_timing  = sanitize_text_field($_POST['treatment_timing'] ?? '');
        $quote_details     = sanitize_textarea_field($_POST['quote_details'] ?? '');
        $country           = sanitize_text_field($_POST['country'] ?? '');
        
        $full_name         = sanitize_text_field($_POST['full_name'] ?? '');
        $age               = isset($_POST['age']) && $_POST['age'] !== '' ? intval($_POST['age']) : null;
        $gender            = sanitize_text_field($_POST['gender'] ?? '');
        
        $email             = sanitize_email($_POST['email'] ?? '');
        $phone             = class_exists('CareToChina_Country_Helper') ? CareToChina_Country_Helper::extract_submitted_phone($_POST, 'phone') : sanitize_text_field($_POST['phone'] ?? '');
        
        $whatsapp          = class_exists('CareToChina_Country_Helper') ? CareToChina_Country_Helper::extract_submitted_phone($_POST, 'whatsapp') : sanitize_text_field($_POST['whatsapp'] ?? '');
        $wechat            = sanitize_text_field($_POST['wechat'] ?? '');
        $messenger         = sanitize_text_field($_POST['messenger'] ?? '');
        $linkedin          = sanitize_text_field($_POST['linkedin'] ?? '');

        // Validation checks
        if (empty($full_name) || empty($email) || empty($phone) || empty($gender) || empty($specialty_str) || empty($treatment_timing) || empty($quote_details)) {
            wp_send_json_error(['message' => __('Please fill in all required fields (Specialty, Timing, Quote Details, Full Name, Gender, Email, Phone).', 'caretochina-booking')]);
        }

        // Google reCAPTCHA Verification
        if (class_exists('CareToChina_Recaptcha')) {
            $recaptcha_token = $_POST['g-recaptcha-response'] ?? '';
            $rc_loc = is_user_logged_in() ? 'booking' : 'guest_booking';
            $rc_check = CareToChina_Recaptcha::verify_submission($recaptcha_token, $rc_loc);
            if (is_wp_error($rc_check)) {
                wp_send_json_error(['message' => $rc_check->get_error_message()]);
            }
        }

        $booking_code = 'CTC-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
        
        $patient_id = 0;
        $is_guest   = 1;
        $raw_guest_token = '';
        $guest_token_hash = '';
        $snapshotted_price = 0.00;
        $currency = CareToChina_Pricing_Plans::get_store_currency();
        $invoice_status = 'Pending Quote / Assessment';

        if (is_user_logged_in()) {
            $patient_id = get_current_user_id();
            $is_guest   = 0;
            $invoice_status = 'Pending Deposit';
            $snapshotted_price = 500.00; // Standard fallback consultation & deposit fee

            if ($pricing_plan_id > 0 && class_exists('CareToChina_Pricing_Plans')) {
                $plan = CareToChina_Pricing_Plans::instance()->get_plan($pricing_plan_id);
                if ($plan && $plan->is_active) {
                    $snapshotted_price = floatval($plan->price);
                    $currency = $plan->currency ?: $currency;
                    $quote_details .= ' [Selected Plan: ' . $plan->name . ']';
                }
            } elseif ($hospital_id > 0) {
                $hosp_price = get_post_meta($hospital_id, '_hospital_package_price', true);
                if ($hosp_price && is_numeric($hosp_price)) {
                    $snapshotted_price = floatval($hosp_price);
                }
            }
        } else {
            // Guest booking: No upfront pricing plan; staff will review specialty & quote via coordinator desk
            $pricing_plan_id = 0;
            $snapshotted_price = 0.00;
            $invoice_status = 'Pending Quote / Assessment';
            $raw_guest_token = bin2hex(random_bytes(32));
            $guest_token_hash = hash('sha256', $raw_guest_token);
        }

        $inserted = $wpdb->insert($table_bookings, [
            'booking_code'     => $booking_code,
            'patient_id'       => $patient_id,
            'is_guest'         => $is_guest,
            'guest_token_hash' => $guest_token_hash,
            'hospital_id'      => $hospital_id,
            'hospital_name'    => $hospital_name ?: __('General Enquiry (No Hospital Selected)', 'caretochina-booking'),
            'specialty'        => $specialty_str,
            'pricing_plan_id'  => $pricing_plan_id,
            'treatment_timing' => $treatment_timing,
            'quote_details'    => $quote_details,
            'country'          => $country,
            'full_name'        => $full_name,
            'age'              => $age,
            'gender'           => $gender,
            'email'            => $email,
            'phone'            => $phone,
            'whatsapp'         => $whatsapp,
            'wechat'           => $wechat,
            'messenger'        => $messenger,
            'linkedin'         => $linkedin,
            'status'           => 'pending',
            'timeline_stage'   => 1,
            'invoice_status'   => $invoice_status,
            'amount'           => $snapshotted_price,
            'currency'         => $currency,
        ]);

        if ($inserted) {
            $booking_id = $wpdb->insert_id;

            // Set secure cookie for guest continuity
            if ($is_guest && !headers_sent()) {
                $expiry_days = intval(get_option('ctc_guest_token_expiry_days', 90));
                $expiry_seconds = max(1, $expiry_days) * 86400;
                setcookie('ctc_guest_token', $raw_guest_token, time() + $expiry_seconds, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
                setcookie('ctc_active_guest_token', $raw_guest_token, time() + $expiry_seconds, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
            }

            $dash_url = class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_url('patient_dashboard') : home_url('/patient-dashboard/');
            $guest_chat_url = $is_guest ? add_query_arg([
                'booking_code' => $booking_code,
                'token'        => $raw_guest_token,
            ], $dash_url) : $dash_url;

            // Send Emails
            $this->send_notifications($booking_code, $full_name, $email, $hospital_name, $specialty_str, $treatment_timing, $guest_chat_url);

            wp_send_json_success([
                'booking_id'      => $booking_id,
                'booking_code'    => $booking_code,
                'is_guest'        => (bool) $is_guest,
                'guest_token'     => $raw_guest_token,
                'chat_url'        => $guest_chat_url,
                'amount'          => $snapshotted_price,
                'currency'        => $currency,
                'specialty'       => $specialty_str,
                'message'         => sprintf(__('Booking request submitted! Your Case Code is %s. A confirmation email with live chat access has been sent to %s.', 'caretochina-booking'), $booking_code, $email)
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to record request into database. Please try again.', 'caretochina-booking')]);
        }
    }

    private function send_notifications($booking_code, $name, $email, $hospital, $specialty, $timing, $chat_url = '') {
        $dashboard_url = !empty($chat_url) ? $chat_url : (class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_url('patient_dashboard') : home_url('/patient-dashboard/'));
        $site_name = get_bloginfo('name') ?: 'CareToChina Medical';
        $admin_email = get_option('admin_email');

        $email_data = [
            'patient_name'    => $name,
            'full_name'       => $name,
            'patient_email'   => $email,
            'booking_code'    => $booking_code,
            'hospital_name'   => $hospital ?: __('Best Matched Medical Center', 'caretochina-booking'),
            'specialty'       => $specialty ?: __('General Medical Consultation', 'caretochina-booking'),
            'timing'          => $timing ?: __('Flexible / As soon as possible', 'caretochina-booking'),
            'status'          => 'Pending Coordinator Review',
            'chat_url'        => $dashboard_url,
            'dashboard_url'   => $dashboard_url,
            'staff_portal_url'=> admin_url('admin.php?page=caretochina-staff-desk'),
        ];

        // 1. Send confirmation to Patient / Guest via Template Engine
        $patient_event = !empty($chat_url) ? 'guest_booking' : 'patient_booking';
        if (class_exists('CareToChina_Email_Templates')) {
            CareToChina_Email_Templates::send_notification($patient_event, $email, $email_data);
        }

        // 2. Send alert to Admin & Staff via Template Engine
        $staff_emails = [$admin_email];
        $staff_users = get_users(['role__in' => ['administrator', 'editor', 'medical_staff'], 'fields' => ['user_email']]);
        foreach ($staff_users as $user) {
            if (!empty($user->user_email)) {
                $staff_emails[] = $user->user_email;
            }
        }
        $staff_emails = array_unique($staff_emails);

        if (class_exists('CareToChina_Email_Templates')) {
            foreach ($staff_emails as $staff_to) {
                CareToChina_Email_Templates::send_notification('admin_booking', $staff_to, $email_data);
            }
        }
    }
}