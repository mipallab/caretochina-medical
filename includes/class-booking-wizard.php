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
        
        $profile_name = $is_logged_in ? $current_user->display_name : '';
        $profile_email = $is_logged_in ? $current_user->user_email : '';
        $profile_phone = $is_logged_in ? get_user_meta($current_user->ID, 'patient_phone', true) : '';
        $profile_gender = $is_logged_in ? get_user_meta($current_user->ID, 'patient_gender', true) : '';
        $profile_age = $is_logged_in ? get_user_meta($current_user->ID, 'patient_age', true) : '';
        
        ?>
        <!-- CARETOCHINA BOOKING MODAL POPUP -->
        <div id="ctc-booking-modal" class="ctc-booking-modal-overlay caretochina-booking-wizard-container" style="display:none;">
            <div class="ctc-booking-modal-container">
                <button type="button" class="ctc-booking-modal-close" onclick="appWizard.closeModal()">&times;</button>
                
                <div class="wiz-header text-center" style="text-align:center; margin-bottom: 24px;">
                    <span class="badge-pill" style="background:#CCFBF1; color:#0F766E; padding:6px 14px; font-weight:700; font-size:12px; border-radius:999px;"><i class="fa-solid fa-wand-magic-sparkles"></i> <?php _e('CareToChina Consultation Wizard', 'caretochina-booking'); ?></span>
                    <h2 class="section-title" style="font-family:var(--cymb-font-heading); color:var(--cymb-text-dark); margin: 34px 0 20px 0; font-size:26px; font-weight:800;"><?php _e('Instant Medical Travel Booking', 'caretochina-booking'); ?></h2>
                    <p class="section-subtitle text-muted" style="margin-bottom:40px; font-size:16px; color:#64748B;"><?php _e('Lock in guaranteed treatment packages in 4 easy steps.', 'caretochina-booking'); ?></p>
                </div>

                <!-- STEP INDICATORS -->
                <div class="wizard-steps-indicator" style="display:flex; justify-content:space-between; margin-bottom:30px; border: 1px solid transparent; border-bottom:1px solid var(--cymb-border-color); padding-bottom:12px; font-family:var(--cymb-font-heading);">
                    <div class="wiz-step active" data-step="1" style="font-size:12px;"><?php _e('1. Hospital', 'caretochina-booking'); ?></div>
                    <div class="wiz-step" data-step="2" style="font-size:12px;"><?php _e('2. Specialty', 'caretochina-booking'); ?></div>
                    <div class="wiz-step" data-step="3" style="font-size:12px;"><?php _e('3. Pricing Plan', 'caretochina-booking'); ?></div>
                    <div class="wiz-step" data-step="4" style="font-size:12px;"><?php _e('4. Patient Details', 'caretochina-booking'); ?></div>
                    <div class="wiz-step" data-step="5" style="font-size:12px;"><?php _e('5. Review & Submit', 'caretochina-booking'); ?></div>
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
                            <button type="button" class="ctc-solid-btn btn-teal-primary btn-wiz-primary" onclick="appWizard.nextStep(3)"><?php _e('Next Step', 'caretochina-booking'); ?> <i class="fa-solid fa-arrow-right"></i></button>
                        </div>
                    </div>

                    <!-- STEP 3: SELECT PRICING PLAN (NEW) -->
                    <div class="wiz-page" id="wiz-step-3" style="display:none;">
                        <div class="form-group mb-16">
                            <label class="form-label" style="font-weight:700; font-size:15px; margin-bottom:4px;"><?php _e('Select Your Treatment Tier / Package *', 'caretochina-booking'); ?></label>
                            <p style="font-size:13px; color:#64748B; margin-top:0; margin-bottom:16px;"><?php _e('Choose an authorized treatment package. All prices are guaranteed and locked onto your booking.', 'caretochina-booking'); ?></p>
                            <div id="wiz-pricing-plans-grid" style="display:grid; grid-template-columns:1fr; gap:12px; max-height:280px; overflow-y:auto; padding-right:4px; margin-bottom:10px;">
                                <!-- Loaded dynamically via AJAX ctc_get_treatment_plans -->
                            </div>
                            <div id="wiz-pricing-plans-empty" style="display:none; text-align:center; padding:24px; background:#F8FAFC; border:1px dashed #CBD5E1; border-radius:12px; color:#64748B; font-size:13px;">
                                <i class="fa-solid fa-tags" style="font-size:24px; margin-bottom:6px; color:#94A3B8; display:block;"></i>
                                <?php _e('Standard Consultation Package ($500.00 Deposit) will be applied for this inquiry.', 'caretochina-booking'); ?>
                            </div>
                        </div>

                        <div class="wiz-action-footer">
                            <button type="button" class="ctc-solid-btn btn-wiz-secondary" onclick="appWizard.nextStep(2)"><i class="fa-solid fa-arrow-left"></i> <?php _e('Back', 'caretochina-booking'); ?></button>
                            <button type="button" class="ctc-solid-btn btn-teal-primary btn-wiz-primary" onclick="appWizard.nextStep(4)"><?php _e('Next Step', 'caretochina-booking'); ?> <i class="fa-solid fa-arrow-right"></i></button>
                        </div>
                    </div>

                    <!-- STEP 4: PATIENT INFO & QUOTE REQUEST -->
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

                        <div class="wiz-action-footer">
                            <button type="button" class="ctc-solid-btn btn-wiz-secondary" onclick="appWizard.nextStep(3)"><i class="fa-solid fa-arrow-left"></i> <?php _e('Back', 'caretochina-booking'); ?></button>
                            <button type="button" class="ctc-solid-btn btn-teal-primary btn-wiz-primary" onclick="appWizard.nextStep(5)"><?php _e('Review & Book', 'caretochina-booking'); ?> <i class="fa-solid fa-arrow-right"></i></button>
                        </div>
                    </div>

                    <!-- STEP 5: CONTACT INFO, SUMMARY & LOGIN-GATED SUBMISSION -->
                    <div class="wiz-page" id="wiz-step-5" style="display:none;">
                        <?php if (!$is_logged_in) : ?>
                            <div class="auth-header" style="text-align:left; margin-bottom:14px;">
                                <h4 style="margin: 0 0 6px 0; font-family:var(--cymb-font-heading); font-size:15px; border-bottom: 1px solid var(--cymb-border-color); padding-bottom: 4px; color: var(--cymb-text-dark);"><?php _e('Enter Contact Information', 'caretochina-booking'); ?></h4>
                            </div>
                            
                            <div class="ctc-form-grid-2">
                                <div class="form-group">
                                    <label class="form-label"><?php _e('Email Address *', 'caretochina-booking'); ?></label>
                                    <input type="email" name="email" id="wiz_email" class="form-input" required placeholder="name@example.com">
                                </div>
                                <div class="form-group">
                                    <label class="form-label"><?php _e('Phone Number *', 'caretochina-booking'); ?></label>
                                    <input type="tel" name="phone" id="wiz_phone" class="form-input" required placeholder="+1 (800) 555-0199">
                                </div>
                            </div>

                            <div class="ctc-form-grid-2">
                                <div class="form-group">
                                    <label class="form-label"><?php _e('WhatsApp Number', 'caretochina-booking'); ?></label>
                                    <input type="text" name="whatsapp" id="wiz_whatsapp" class="form-input" placeholder="+1 (800) 555-0199">
                                </div>
                                <div class="form-group">
                                    <label class="form-label"><?php _e('WeChat ID', 'caretochina-booking'); ?></label>
                                    <input type="text" name="wechat" id="wiz_wechat" class="form-input" placeholder="WeChat ID">
                                </div>
                            </div>

                            <div class="ctc-form-grid-2">
                                <div class="form-group">
                                    <label class="form-label"><?php _e('Messenger ID', 'caretochina-booking'); ?></label>
                                    <input type="text" name="messenger" id="wiz_messenger" class="form-input" placeholder="Messenger Handle / ID">
                                </div>
                                <div class="form-group">
                                    <label class="form-label"><?php _e('LinkedIn URL', 'caretochina-booking'); ?></label>
                                    <input type="text" name="linkedin" id="wiz_linkedin" class="form-input" placeholder="LinkedIn Profile URL">
                                </div>
                            </div>
                        <?php else : ?>
                            <input type="hidden" name="email" id="wiz_email" value="<?php echo esc_attr($profile_email); ?>">
                            <input type="hidden" name="phone" id="wiz_phone" value="<?php echo esc_attr($profile_phone); ?>">
                            
                            <div class="ctc-summary-logged-in-box" style="background:#F0FDF4; border:1px solid #BBF7D0; padding:18px; border-radius:12px; margin-bottom:20px; color:#166534; font-size:14px;">
                                <i class="fa-solid fa-circle-check"></i> <?php printf(__('You are logged in as %s. Your verified profile will be linked to this booking.', 'caretochina-booking'), esc_html($profile_name)); ?>
                            </div>
                        <?php endif; ?>

                        <div class="wiz-summary-card" style="padding:20px; border-radius:14px; margin-bottom:20px; background:#F8FAFC; border:1px solid #E2E8F0;">
                            <h4 style="margin:0 0 12px 0; font-size:14px; font-weight:700; color:var(--cymb-text-dark);"><?php _e('Summary of Booking Request:', 'caretochina-booking'); ?></h4>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; font-size:13px; color:var(--cymb-text-dark);">
                                <div><strong><?php _e('Selected Hospital:', 'caretochina-booking'); ?></strong> <span id="wiz-sum-hospital">Hospital</span></div>
                                <div><strong><?php _e('Treatment / Specialty:', 'caretochina-booking'); ?></strong> <span id="wiz-sum-specialties">Specialties</span></div>
                                <div><strong><?php _e('Pricing Plan:', 'caretochina-booking'); ?></strong> <span id="wiz-sum-plan" style="color:#0F766E; font-weight:700;">Standard Plan</span></div>
                                <div><strong><?php _e('Total Package Cost:', 'caretochina-booking'); ?></strong> <span id="wiz-sum-cost" style="color:#0F766E; font-weight:800; font-size:15px;">$500.00 USD</span></div>
                                <div><strong><?php _e('Treatment Timing:', 'caretochina-booking'); ?></strong> <span id="wiz-sum-timing">Timing</span></div>
                                <div><strong><?php _e('Patient Name:', 'caretochina-booking'); ?></strong> <span id="wiz-sum-patient">Patient</span></div>
                            </div>
                        </div>

                        <?php if (class_exists('CareToChina_Recaptcha')) echo CareToChina_Recaptcha::render_field('booking'); ?>

                        <div class="wiz-action-footer">
                            <button type="button" class="ctc-solid-btn btn-wiz-secondary" onclick="appWizard.nextStep(4)"><i class="fa-solid fa-arrow-left"></i> <?php _e('Back', 'caretochina-booking'); ?></button>
                            <button type="submit" id="ctc-wizard-submit-btn" class="ctc-solid-btn btn-teal-primary btn-wiz-primary"><i class="fa-solid fa-check-circle"></i> <?php echo $is_logged_in ? __('Confirm & Proceed to Payment', 'caretochina-booking') : __('Sign In & Confirm Booking', 'caretochina-booking'); ?></button>
                        </div>
                    </div>
                </form>
                <div id="ctc-wizard-status" style="display:none; margin-top:20px; text-align:center;"></div>
            </div>
        </div>

        <!-- AUTH GATE MODAL (TRIGGERED AT FINAL STEP IF GUEST) -->
        <div id="wiz-auth-gate-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.75); z-index:9999999; align-items:center; justify-content:center; padding:20px; box-sizing:border-box;">
            <div style="background:#FFF; border-radius:20px; max-width:460px; width:100%; padding:28px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); font-family:'Manrope', sans-serif; position:relative; max-height:90vh; overflow-y:auto;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid #E2E8F0; padding-bottom:12px;">
                    <h3 style="margin:0; font-size:17px; font-weight:800; color:#0F172A; display:flex; align-items:center; gap:8px;">
                        <i class="fa-solid fa-shield-halved" style="color:#0F766E;"></i> <?php _e('Patient Authentication', 'caretochina-booking'); ?>
                    </h3>
                    <button type="button" onclick="jQuery('#wiz-auth-gate-modal').hide()" style="background:none; border:none; font-size:18px; color:#94A3B8; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
                </div>

                <div style="background:#F0FDFA; border:1px solid #CCFBF1; border-radius:10px; padding:12px; margin-bottom:18px; font-size:12px; color:#0F766E; line-height:1.4;">
                    <i class="fa-solid fa-circle-check"></i> <?php _e('Your medical itinerary & pricing selections are safely saved. Sign in or register below to finalize your booking.', 'caretochina-booking'); ?>
                </div>

                <div id="wiz-auth-modal-notice" style="display:none; padding:10px; border-radius:8px; margin-bottom:14px; font-size:13px;"></div>

                <?php if (class_exists('CareToChina_Google_Login') && CareToChina_Google_Login::is_enabled()) : ?>
                    <div style="margin-bottom:18px; text-align:center;">
                        <a href="<?php echo esc_url(CareToChina_Google_Login::get_auth_url()); ?>" class="ctc-google-btn" style="display:flex; align-items:center; justify-content:center; gap:10px; width:100%; background:#FFFFFF; color:#1F2937; border:1.5px solid #E5E7EB; padding:11px 16px; border-radius:10px; font-weight:700; font-size:13px; text-decoration:none; box-shadow:0 2px 4px rgba(0,0,0,0.05);">
                            <svg width="18" height="18" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
                            <?php _e('Continue with Google', 'caretochina-booking'); ?>
                        </a>
                        <div style="display:flex; align-items:center; gap:8px; margin:14px 0; color:#94A3B8; font-size:12px;">
                            <div style="flex:1; height:1px; background:#E2E8F0;"></div>
                            <span><?php _e('OR', 'caretochina-booking'); ?></span>
                            <div style="flex:1; height:1px; background:#E2E8F0;"></div>
                        </div>
                    </div>
                <?php endif; ?>

                <div style="display:flex; gap:8px; margin-bottom:16px;">
                    <button type="button" id="wiz-auth-tab-login" onclick="appWizard.switchAuthModalTab('login')" style="flex:1; padding:8px; border-radius:8px; font-weight:700; font-size:13px; cursor:pointer; background:#0F766E; color:#FFF; border:none;"><?php _e('Sign In', 'caretochina-booking'); ?></button>
                    <button type="button" id="wiz-auth-tab-reg" onclick="appWizard.switchAuthModalTab('reg')" style="flex:1; padding:8px; border-radius:8px; font-weight:700; font-size:13px; cursor:pointer; background:#F1F5F9; color:#475569; border:none;"><?php _e('Register', 'caretochina-booking'); ?></button>
                </div>

                <!-- AJAX LOGIN FORM -->
                <form id="wiz-ajax-login-form">
                    <div style="margin-bottom:12px;">
                        <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;"><?php _e('Email Address *', 'caretochina-booking'); ?></label>
                        <input type="email" name="log_email" id="wiz_auth_log_email" class="form-input" style="width:100%; padding:9px; border-radius:8px; border:1px solid #CBD5E1; font-size:13px;" required>
                    </div>
                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;"><?php _e('Password *', 'caretochina-booking'); ?></label>
                        <input type="password" name="log_pass" id="wiz_auth_log_pass" class="form-input" style="width:100%; padding:9px; border-radius:8px; border:1px solid #CBD5E1; font-size:13px;" required>
                    </div>
                    <button type="submit" id="btn-wiz-ajax-login" class="ctc-solid-btn btn-teal-primary" style="width:100%; padding:11px; border-radius:10px; font-weight:700; font-size:14px; cursor:pointer;">
                        <i class="fa-solid fa-right-to-bracket"></i> <?php _e('Sign In & Complete Booking', 'caretochina-booking'); ?>
                    </button>
                </form>

                <!-- AJAX REGISTER FORM -->
                <form id="wiz-ajax-reg-form" style="display:none;">
                    <div style="margin-bottom:12px;">
                        <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;"><?php _e('Full Name *', 'caretochina-booking'); ?></label>
                        <input type="text" name="reg_name" id="wiz_auth_reg_name" class="form-input" style="width:100%; padding:9px; border-radius:8px; border:1px solid #CBD5E1; font-size:13px;" required>
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;"><?php _e('Email Address *', 'caretochina-booking'); ?></label>
                        <input type="email" name="reg_email" id="wiz_auth_reg_email" class="form-input" style="width:100%; padding:9px; border-radius:8px; border:1px solid #CBD5E1; font-size:13px;" required>
                    </div>
                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;"><?php _e('Choose Password *', 'caretochina-booking'); ?></label>
                        <input type="password" name="reg_pass" id="wiz_auth_reg_pass" class="form-input" style="width:100%; padding:9px; border-radius:8px; border:1px solid #CBD5E1; font-size:13px;" required>
                    </div>
                    <button type="submit" id="btn-wiz-ajax-reg" class="ctc-solid-btn btn-teal-primary" style="width:100%; padding:11px; border-radius:10px; font-weight:700; font-size:14px; cursor:pointer;">
                        <i class="fa-solid fa-user-plus"></i> <?php _e('Register & Complete Booking', 'caretochina-booking'); ?>
                    </button>
                </form>
            </div>
        </div>
        <?php
    }

    public function handle_booking_submission() {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_booking_nonce') && !wp_verify_nonce($nonce, 'careyou_booking_nonce')) {
            wp_send_json_error(['message' => __('Security verification failed.', 'caretochina-booking')]);
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
        $phone             = sanitize_text_field($_POST['phone'] ?? '');
        
        $whatsapp          = sanitize_text_field($_POST['whatsapp'] ?? '');
        $wechat            = sanitize_text_field($_POST['wechat'] ?? '');
        $messenger         = sanitize_text_field($_POST['messenger'] ?? '');
        $linkedin          = sanitize_text_field($_POST['linkedin'] ?? '');

        // Validation checks
        if (empty($full_name) || empty($email) || empty($phone) || empty($gender) || empty($specialty_str) || empty($treatment_timing) || empty($quote_details)) {
            wp_send_json_error(['message' => __('Please fill in all required fields (Specialty, Timing, Quote Details, Full Name, Gender, Email, Phone).', 'caretochina-booking')]);
        }

        // Google reCAPTCHA Verification (if enabled for booking submission)
        if (class_exists('CareToChina_Recaptcha')) {
            $recaptcha_token = $_POST['g-recaptcha-response'] ?? '';
            $rc_check = CareToChina_Recaptcha::verify_submission($recaptcha_token, 'booking');
            if (is_wp_error($rc_check)) {
                wp_send_json_error(['message' => $rc_check->get_error_message()]);
            }
        }

        $booking_code = 'CTC-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
        
        $patient_id = 0;
        if (is_user_logged_in()) {
            $patient_id = get_current_user_id();
        } else {
            $user = get_user_by('email', $email);
            if ($user) {
                $patient_id = $user->ID;
            }
        }

        // Authoritative price snapshotting at booking creation time
        $snapshotted_price = 500.00; // Standard fallback consultation & deposit fee
        $currency = CareToChina_Pricing_Plans::get_store_currency();

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

        $inserted = $wpdb->insert($table_bookings, [
            'booking_code'     => $booking_code,
            'patient_id'       => $patient_id,
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
            'invoice_status'   => 'Pending Deposit',
            'amount'           => $snapshotted_price,
            'currency'         => $currency,
        ]);

        if ($inserted) {
            $booking_id = $wpdb->insert_id;

            // Send Emails
            $this->send_notifications($booking_code, $full_name, $email, $hospital_name, $specialty_str, $treatment_timing);

            wp_send_json_success([
                'booking_id'      => $booking_id,
                'booking_code'    => $booking_code,
                'amount'          => $snapshotted_price,
                'currency'        => $currency,
                'specialty'       => $specialty_str,
                'message'         => sprintf(__('Booking request submitted! Your Case Code is %s. A confirmation email has been sent to %s.', 'caretochina-booking'), $booking_code, $email)
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to record request into database. Please try again.', 'caretochina-booking')]);
        }
    }

    private function send_notifications($booking_code, $name, $email, $hospital, $specialty, $timing) {
        $dashboard_url = class_exists('CareToChina_Page_Manager') ? CareToChina_Page_Manager::get_page_url('patient_dashboard') : home_url('/patient-dashboard/');
        $subject_patient = sprintf(__('CareToChina Medical Quote Confirmation - Case #%s', 'caretochina-booking'), $booking_code);
        $message_patient = sprintf(
            __("Dear %s,\n\nYour medical consultation and quote request has been received!\n\nBooking Details:\n- Care Case Code: %s\n- Hospital Preferred: %s\n- Required Specialties: %s\n- Treatment Timing: %s\n\nOur Care Coordinator will review your medical information and match you with our expert surgeons within 24 hours. You can track your treatment roadmap and access the live patient portal at:\n%s\n\nBest regards,\nCareToChina International Concierge Team", 'caretochina-booking'),
            $name, $booking_code, $hospital, $specialty, $timing, $dashboard_url
        );

        $headers = ['Content-Type: text/plain; charset=UTF-8', 'From: CareToChina <care@caretochina.com>'];
        
        // 1. Send confirmation to Patient
        wp_mail($email, $subject_patient, $message_patient, $headers);

        // 2. Send alert to Admin & Staff
        $admin_email = get_option('admin_email');
        
        // Fetch all staff users (Administrator, Editor, custom Medical Staff roles)
        $staff_emails = [$admin_email];
        $staff_users = get_users(['role__in' => ['administrator', 'editor', 'medical_staff']]);
        foreach ($staff_users as $user) {
            $staff_emails[] = $user->user_email;
        }
        $staff_emails = array_unique($staff_emails);

        $subject_staff = __('Someone booked a request, please check.', 'caretochina-booking');
        $message_staff = sprintf(
            __("Hello,\n\nA new medical travel request has been submitted on the CareToChina Portal.\n\nCase Details:\n- Code: %s\n- Patient Name: %s\n- Patient Email: %s\n- Hospital: %s\n- Specialty: %s\n\nPlease log in to your Staff Dashboard to check details and reply to the patient:\n%s\n", 'caretochina-booking'),
            $booking_code, $name, $email, $hospital, $specialty, admin_url('admin.php?page=caretochina-staff-desk')
        );

        foreach ($staff_emails as $staff_email) {
            wp_mail($staff_email, $subject_staff, $message_staff, $headers);
        }
    }
}