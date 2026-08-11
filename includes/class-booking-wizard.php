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
                    <div class="wiz-step active" data-step="1" style="font-size:13px;"><?php _e('1. Select Hospital', 'caretochina-booking'); ?></div>
                    <div class="wiz-step" data-step="2" style="font-size:13px;"><?php _e('2. Timing & Specialty', 'caretochina-booking'); ?></div>
                    <div class="wiz-step" data-step="3" style="font-size:13px;"><?php _e('3. Patient Details', 'caretochina-booking'); ?></div>
                    <div class="wiz-step" data-step="4" style="font-size:13px;"><?php _e('4. Submit', 'caretochina-booking'); ?></div>
                </div>

                <form id="ctc-booking-wizard-form">
                    <!-- HIDDEN FIELD FOR CURRENT SCREEN MODE AND SELECTIONS -->
                    <input type="hidden" name="hospital_id" id="wiz_hospital_id" value="0">
                    <input type="hidden" name="hospital_name" id="wiz_hospital_name" value="">
                    
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
                            <label class="form-label" style="font-weight:700; margin-bottom:8px;"><?php _e('Select Required Specialty *', 'caretochina-booking'); ?></label>
                            <div id="wiz-specialty-checkbox-list" class="specialty-checkbox-list" style="display:grid; grid-template-columns:1fr 1fr; gap:10px; max-height:160px; overflow-y:auto; padding-right:4px;">
                                <!-- Dynamic check list loaded by JS -->
                            </div>
                        </div>

                        <div class="wiz-action-footer">
                            <button type="button" class="ctc-solid-btn btn-wiz-secondary" id="wiz-back-btn-step-2" onclick="appWizard.nextStep(1)"><i class="fa-solid fa-arrow-left"></i> <?php _e('Back', 'caretochina-booking'); ?></button>
                            <button type="button" class="ctc-solid-btn btn-teal-primary btn-wiz-primary" onclick="appWizard.nextStep(3)"><?php _e('Next Step', 'caretochina-booking'); ?> <i class="fa-solid fa-arrow-right"></i></button>
                        </div>
                    </div>

                    <!-- STEP 3: PATIENT INFO & QUOTE REQUEST -->
                    <div class="wiz-page" id="wiz-step-3" style="display:none;">
                        <div class="form-group mb-12">
                            <label class="form-label" style="font-weight:700;"><?php _e('Briefly describe your treatment / quote request *', 'caretochina-booking'); ?></label>
                            <textarea name="quote_details" id="wiz_quote_details" class="form-input" rows="2" required placeholder="<?php _e('Enter any details about your condition, medical documents, or request...', 'caretochina-booking'); ?>"></textarea>
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
                            <button type="button" class="ctc-solid-btn btn-wiz-secondary" onclick="appWizard.nextStep(2)"><i class="fa-solid fa-arrow-left"></i> <?php _e('Back', 'caretochina-booking'); ?></button>
                            <button type="button" class="ctc-solid-btn btn-teal-primary btn-wiz-primary" onclick="appWizard.nextStep(4)"><?php _e('Review & Submit', 'caretochina-booking'); ?> <i class="fa-solid fa-arrow-right"></i></button>
                        </div>
                    </div>

                    <!-- STEP 4: CONTACT INFO & REVIEW -->
                    <div class="wiz-page" id="wiz-step-4" style="display:none;">
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
                                <i class="fa-solid fa-circle-check"></i> <?php printf(__('You are logged in as %s. Your profile details will be submitted with this request.', 'caretochina-booking'), esc_html($profile_name)); ?>
                            </div>
                        <?php endif; ?>

                        <div class="wiz-summary-card" style="padding:20px; border-radius:14px; margin-bottom:20px;">
                            <h4 style="margin:0 0 10px 0; font-size:14px; font-weight:700; color:var(--cymb-text-dark);"><?php _e('Summary of Booking Request:', 'caretochina-booking'); ?></h4>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; font-size:13px; color:var(--cymb-text-dark);">
                                <div><strong><?php _e('Selected Hospital:', 'caretochina-booking'); ?></strong> <span id="wiz-sum-hospital">Hospital</span></div>
                                <div><strong><?php _e('Required Specialties:', 'caretochina-booking'); ?></strong> <span id="wiz-sum-specialties">Specialties</span></div>
                                <div><strong><?php _e('Treatment Timing:', 'caretochina-booking'); ?></strong> <span id="wiz-sum-timing">Timing</span></div>
                                <div><strong><?php _e('Patient:', 'caretochina-booking'); ?></strong> <span id="wiz-sum-patient">Patient</span></div>
                            </div>
                        </div>

                        <div class="wiz-action-footer">
                            <button type="button" class="ctc-solid-btn btn-wiz-secondary" onclick="appWizard.nextStep(3)"><i class="fa-solid fa-arrow-left"></i> <?php _e('Back', 'caretochina-booking'); ?></button>
                            <button type="submit" id="ctc-wizard-submit-btn" class="ctc-solid-btn btn-teal-primary btn-wiz-primary"><i class="fa-solid fa-check-circle"></i> <?php _e('Confirm & Send Request', 'caretochina-booking'); ?></button>
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

        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';

        // Retrieve and sanitize fields
        $hospital_id       = intval($_POST['hospital_id'] ?? 0);
        $hospital_name     = sanitize_text_field($_POST['hospital_name'] ?? '');
        $specialties       = isset($_POST['specialty']) ? (array)$_POST['specialty'] : [];
        $specialties       = array_map('sanitize_text_field', $specialties);
        $specialty_str     = implode(', ', $specialties);
        
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

        $inserted = $wpdb->insert($table_bookings, [
            'booking_code'     => $booking_code,
            'patient_id'       => $patient_id,
            'hospital_id'      => $hospital_id,
            'hospital_name'    => $hospital_name ?: __('General Enquiry (No Hospital Selected)', 'caretochina-booking'),
            'specialty'        => $specialty_str,
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
        ]);

        if ($inserted) {
            // Send Emails
            $this->send_notifications($booking_code, $full_name, $email, $hospital_name, $specialty_str, $treatment_timing);

            wp_send_json_success([
                'booking_code' => $booking_code,
                'message'      => sprintf(__('Booking request submitted! Your Case Code is %s. A confirmation email has been sent to %s.', 'caretochina-booking'), $booking_code, $email)
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to record request into database. Please try again.', 'caretochina-booking')]);
        }
    }

    private function send_notifications($booking_code, $name, $email, $hospital, $specialty, $timing) {
        $subject_patient = sprintf(__('CareToChina Medical Quote Confirmation - Case #%s', 'caretochina-booking'), $booking_code);
        $message_patient = sprintf(
            __("Dear %s,\n\nYour medical consultation and quote request has been received!\n\nBooking Details:\n- Care Case Code: %s\n- Hospital Preferred: %s\n- Required Specialties: %s\n- Treatment Timing: %s\n\nOur Care Coordinator will review your medical information and match you with our expert surgeons within 24 hours. You can track your treatment roadmap and access the live patient portal at:\n%s\n\nBest regards,\nCareToChina International Concierge Team", 'caretochina-booking'),
            $name, $booking_code, $hospital, $specialty, $timing, home_url('/patient-dashboard/')
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