<?php
if (!defined('ABSPATH')) {
    exit;
}

class CareToChina_Packages {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Register Post Type
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'maybe_auto_seed_packages'], 20);

        // Admin Meta Boxes & Save
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_ctc_package', [$this, 'save_package_meta']);
        add_action('admin_notices', [$this, 'display_package_admin_notices']);

        // Custom Admin List Columns
        add_filter('manage_ctc_package_posts_columns', [$this, 'register_admin_columns']);
        add_action('manage_ctc_package_posts_custom_column', [$this, 'render_admin_columns'], 10, 2);
        add_filter('manage_edit-ctc_package_sortable_columns', [$this, 'register_sortable_columns']);

        // Delete Protection Hook
        add_action('before_delete_post', [$this, 'check_delete_protection']);
        add_action('wp_trash_post', [$this, 'check_delete_protection']);

        // Legacy URL Redirects
        add_action('admin_init', [$this, 'handle_legacy_page_redirects']);

        // AJAX Endpoints: Public (Logged-in & Anonymous)
        add_action('wp_ajax_ctc_get_packages', [$this, 'handle_get_packages']);
        add_action('wp_ajax_nopriv_ctc_get_packages', [$this, 'handle_get_packages']);
    }

    /**
     * Gracefully redirect legacy pricing plans admin URLs to Concierge Packages
     */
    public function handle_legacy_page_redirects() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['page']) && in_array($_GET['page'], ['caretochina-pricing-plans', 'careyou-pricing-plans'], true)) {
            wp_safe_redirect(admin_url('edit.php?post_type=ctc_package'));
            exit;
        }
    }

    /**
     * Store Currency helper - locked server-side
     */
    public static function get_store_currency() {
        if (function_exists('get_woocommerce_currency')) {
            return get_woocommerce_currency();
        }
        return get_option('ctc_payment_currency', 'USD');
    }

    /**
     * Currency Symbol helper
     */
    public static function get_currency_symbol($currency = '') {
        if (empty($currency)) {
            $currency = self::get_store_currency();
        }
        if (function_exists('get_woocommerce_currency_symbol')) {
            $symbol = get_woocommerce_currency_symbol($currency);
            if (!empty($symbol)) {
                return $symbol;
            }
        }
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'CNY' => '¥',
            'RMB' => '¥',
            'JPY' => '¥',
            'AUD' => 'A$',
            'CAD' => 'C$',
            'SGD' => 'S$',
            'HKD' => 'HK$',
            'AED' => 'AED',
            'SAR' => 'SAR',
            'BDT' => '৳',
            'INR' => '₹',
            'MYR' => 'RM',
            'THB' => '฿',
            'PHP' => '₱',
            'IDR' => 'Rp',
            'VND' => '₫',
            'KRW' => '₩',
            'TRY' => '₺',
            'RUB' => '₽',
            'BRL' => 'R$',
            'ZAR' => 'R',
            'NZD' => 'NZ$',
            'CHF' => 'CHF',
        ];
        return $symbols[strtoupper($currency)] ?? strtoupper($currency);
    }

    /**
     * Global Service Notes Text Block
     */
    public static function get_global_service_notes() {
        $default = "1. Smart Language Support: A smart translation device is provided throughout (returnable or to keep), enabling seamless communication during treatment.\n"
                 . "2. International Connectivity: High-speed network access within China is provided, ensuring real-time contact with family members overseas.\n"
                 . "3. Convenience Services for Overseas Visitors: Dedicated staff assist with RMB currency exchange and full arrival coordination.\n"
                 . "4. Exclusive Amenities: A custom premium gift is included; accompanying-guest policy: the patient plus 1 family member receive dedicated service benefits, and 1 child under 1.2m may accompany free of charge (per official venue standards).\n"
                 . "5. Official Terms & Policies: If package contents, pricing, or service details are updated, the latest version published through CaretoChina's official channels shall prevail. Prepared by Hong Kong Medical Butler International Limited, holding final interpretation rights.";

        return get_option('caretochina_global_service_notes', $default);
    }

    /**
     * Register Custom Post Type for Concierge Packages
     */
    public function register_post_type() {
        $labels = [
            'name'               => __('Concierge Packages', 'caretochina-medical'),
            'singular_name'      => __('Concierge Package', 'caretochina-medical'),
            'menu_name'          => __('Concierge Packages', 'caretochina-medical'),
            'add_new'            => __('Add New Package', 'caretochina-medical'),
            'add_new_item'       => __('Add New Concierge Package', 'caretochina-medical'),
            'edit_item'          => __('Edit Concierge Package', 'caretochina-medical'),
            'new_item'           => __('New Package', 'caretochina-medical'),
            'view_item'          => __('View Package', 'caretochina-medical'),
            'search_items'       => __('Search Packages', 'caretochina-medical'),
            'not_found'          => __('No packages found', 'caretochina-medical'),
            'not_found_in_trash' => __('No packages found in Trash', 'caretochina-medical'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'edit.php?post_type=hospital',
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'map_meta_cap'       => true,
            'hierarchical'       => false,
            'supports'           => ['title', 'thumbnail', 'page-attributes'],
            'show_in_rest'       => true,
        ];

        register_post_type('ctc_package', $args);
    }

    /**
     * Auto-Seed default Plan A, B, C, D packages if none exist
     * CURRENCY SAFETY RULE: Seeded with price = 0 and is_active = 0 (inactive).
     */
    public function maybe_auto_seed_packages() {
        if (get_option('caretochina_packages_seeded_v2')) {
            return;
        }

        $existing = get_posts([
            'post_type'      => 'ctc_package',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ]);

        if (!empty($existing)) {
            update_option('caretochina_packages_seeded_v2', 1);
            return;
        }

        $defaults = [
            [
                'title'       => 'Plan A: Ultimate Exclusive Package',
                'badge'       => 'Ultimate VIP',
                'price'       => 0.00,
                'is_active'   => 0,
                'order'       => 1,
                'coordination'=> "• Medical Report Assessment & Treatment Planning: Professional assessment of medical reports, hospital and specialist recommendations, and development of treatment plans and cost estimates\n• Pre-Departure Video Consultation: Scheduling a pre-departure video consultation with the doctor to align on treatment direction and expectations in advance\n• Medical Document & Itinerary Assistance: Assistance with medical documents and visa materials, and follow-up on flight itinerary arrangements\n• Priority In-Person Consultation Scheduling: Priority scheduling of in-person doctor consultations upon arrival, minimizing wait times\n• Professional Medical Escort Interpretation: Professional medical interpreters and portable translation devices provided, accompanying the patient throughout for communication support\n• Full Hospitalization Coordination: Admission procedures, coordination during hospitalization, and post-operative follow-up arrangements\n• VIP Fast-Track Access: VIP hospital coordination with priority access to renowned specialists\n• Medication & Return-Home Coordination: Post-operative medication guidance, assistance with taking medication abroad, and preparation of bilingual (Chinese-English) medical summaries for follow-up care after returning home\n• Round-the-Clock Service Support: Dedicated customer service available 24/7, with immediate response for urgent matters",
                'vehicle'     => 'Luxury business vehicle',
                'interpreter' => 'Driver + bilingual interpreter for airport transfers',
                'accommodation' => 'Near the hospital campus, an international luxury-brand hotel premium suite, 2 consecutive nights, executive custom room, for an exclusive stay experience',
                'dining'      => 'Standard dining for 2 (premium custom menu) - Cantonese private-kitchen cuisine / nourishing hot pot / Hunan cuisine / Sichuan cuisine / Shunde cuisine (please inform us of any dietary restrictions in advance)',
                'companion'   => '1 companion for health check + TCM pulse diagnosis for 2 + 15-day TCM herbal regimen for 2 + Male (TCM therapy) + Female (beauty & wellness)',
                'travel'      => 'Full Guangzhou landmark experience: Canton Tower summit tour + luxury Pearl River night cruise + in-depth Beijing Road night tour + Shamian Island + Five Rams Statue photo stop + Chimelong Circus + Chimelong Safari Park + Chimelong Ocean Kingdom',
                'positioning' => 'The highest-tier, one-stop premium medical escort experience, with dedicated vehicle, accommodation, and travel/leisure services all included, with English interpretation throughout. Ideal for high-net-worth overseas patients and premium families seeking a fully customized medical journey.',
            ],
            [
                'title'       => 'Plan B: Premium Exclusive Package',
                'badge'       => 'Premium Choice',
                'price'       => 0.00,
                'is_active'   => 0,
                'order'       => 2,
                'coordination'=> "• Medical report assessment, hospital and specialist recommendations, treatment plan and cost estimate\n• Assistance with medical documents and visa materials, and flight itinerary follow-up\n• Priority scheduling of in-person doctor consultations\n• Professional medical interpreter and translation device provided\n• Hospitalization coordination and post-operative follow-up arrangements\n• Customer service response during business hours, with priority handling of urgent matters",
                'vehicle'     => 'Premium business vehicle',
                'interpreter' => 'Driver + bilingual (English + Chinese) interpreter for airport transfers',
                'accommodation' => 'Near the hospital campus, a quality brand hotel, select room/suite, 2 consecutive nights, premium custom room',
                'dining'      => 'Dining for 2, exclusive premium custom menu, 2 meals arranged. Choice of nourishing hot pot, Hunan cuisine, Sichuan cuisine, or Shunde cuisine (please inform us of any dietary restrictions in advance)',
                'companion'   => '1 companion for health check + TCM pulse diagnosis for 2 + 7-day TCM herbal regimen for 2',
                'travel'      => 'Full Guangzhou landmark experience: Canton Tower summit tour + Pearl River night cruise + Beijing Road night tour + Chimelong Safari Park + Shamian Island + Five Rams Statue photo stop',
                'positioning' => 'A comprehensive upgrade to dining, accommodation, and leisure for a premium medical escort experience — ideal for families seeking comfort and a high quality of life during treatment.',
            ],
            [
                'title'       => 'Plan C: Essential Select Package',
                'badge'       => 'Best Value',
                'price'       => 0.00,
                'is_active'   => 0,
                'order'       => 3,
                'coordination'=> "• Medical report assessment, hospital and specialist recommendations\n• Assistance with medical documents and visa materials\n• Scheduling of doctor consultations, with translation device provided\n• Hospitalization coordination",
                'vehicle'     => 'Business vehicle with driver',
                'interpreter' => 'Driver + bilingual interpreter for airport transfers + translation device',
                'accommodation' => 'Near the hospital campus, a select hotel, standard room, 2 consecutive nights, standard comfort room',
                'dining'      => 'Standard dining for 2, 2 meals arranged. Choice of hot pot, Hunan cuisine, Sichuan cuisine, or Shunde cuisine (please inform us of any dietary restrictions in advance)',
                'companion'   => '',
                'travel'      => 'Optional: Pearl River night cruise, Beijing Road night tour (interpreter provided)',
                'positioning' => 'A great-value option combining reliable medical escort service with a light cultural city experience — ideal for overseas patients on shorter visits who prefer simple, comfortable service.',
            ],
            [
                'title'       => 'Plan D: Convenient Select Package',
                'badge'       => 'Essential Escort',
                'price'       => 0.00,
                'is_active'   => 0,
                'order'       => 4,
                'coordination'=> "• Medical report assessment, hospital and specialist recommendations (basic)\n• Basic guidance on medical documents and visa materials\n• Assistance booking in-person consultations",
                'vehicle'     => 'Business vehicle with driver',
                'interpreter' => 'Driver + bilingual translation device for airport transfers',
                'accommodation' => 'No accommodation service',
                'dining'      => 'No dining service',
                'companion'   => '',
                'travel'      => 'No travel & leisure add-ons',
                'positioning' => 'Streamlined, efficient, and great value — focused on core medical escort needs, ideal for overseas patients who require specific treatment only, without additional travel or leisure activities.',
            ],
        ];

        foreach ($defaults as $data) {
            $post_id = wp_insert_post([
                'post_type'   => 'ctc_package',
                'post_title'  => $data['title'],
                'post_status' => 'publish',
                'menu_order'  => $data['order'],
            ]);

            if ($post_id && !is_wp_error($post_id)) {
                update_post_meta($post_id, '_ctc_pkg_price', 0.00);
                update_post_meta($post_id, '_ctc_pkg_currency', self::get_store_currency());
                update_post_meta($post_id, '_ctc_pkg_badge', $data['badge']);
                update_post_meta($post_id, '_ctc_pkg_medical_coordination', $data['coordination']);
                update_post_meta($post_id, '_ctc_pkg_vehicle', $data['vehicle']);
                update_post_meta($post_id, '_ctc_pkg_interpreter', $data['interpreter']);
                update_post_meta($post_id, '_ctc_pkg_accommodation', $data['accommodation']);
                update_post_meta($post_id, '_ctc_pkg_dining', $data['dining']);
                update_post_meta($post_id, '_ctc_pkg_companion', $data['companion']);
                update_post_meta($post_id, '_ctc_pkg_travel', $data['travel']);
                update_post_meta($post_id, '_ctc_pkg_positioning', $data['positioning']);
                update_post_meta($post_id, '_ctc_pkg_is_active', 0);
            }
        }

        update_option('caretochina_packages_seeded_v2', 1);
    }

    /**
     * Add Meta Boxes on ctc_package Edit Screen
     */
    public function add_meta_boxes() {
        add_meta_box(
            'ctc_package_details_mb',
            __('Concierge Package Inclusions & Configuration', 'caretochina-medical'),
            [$this, 'render_meta_box'],
            'ctc_package',
            'normal',
            'high'
        );
    }

    /**
     * Render Meta Box UI
     */
    public function render_meta_box($post) {
        wp_nonce_field('save_ctc_package_meta', 'ctc_package_nonce');

        $price         = get_post_meta($post->ID, '_ctc_pkg_price', true);
        $badge         = get_post_meta($post->ID, '_ctc_pkg_badge', true);
        $coordination  = get_post_meta($post->ID, '_ctc_pkg_medical_coordination', true);
        $vehicle       = get_post_meta($post->ID, '_ctc_pkg_vehicle', true);
        $interpreter   = get_post_meta($post->ID, '_ctc_pkg_interpreter', true);
        $accommodation = get_post_meta($post->ID, '_ctc_pkg_accommodation', true);
        $dining        = get_post_meta($post->ID, '_ctc_pkg_dining', true);
        $companion     = get_post_meta($post->ID, '_ctc_pkg_companion', true);
        $travel        = get_post_meta($post->ID, '_ctc_pkg_travel', true);
        $positioning   = get_post_meta($post->ID, '_ctc_pkg_positioning', true);
        $is_active     = get_post_meta($post->ID, '_ctc_pkg_is_active', true);
        if ($is_active === '') {
            $is_active = (floatval($price) > 0) ? '1' : '0';
        }

        $currency        = self::get_store_currency();
        $currency_symbol = self::get_currency_symbol($currency);
        $ref_count       = $this->get_package_reference_count($post->ID);
        ?>
        <style>
            .ctc-pkg-admin-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
            .ctc-pkg-admin-field { display: flex; flex-direction: column; gap: 5px; margin-bottom: 14px; }
            .ctc-pkg-admin-field.full { grid-column: 1 / -1; }
            .ctc-pkg-admin-field label { font-weight: 700; font-size: 13px; color: #1E293B; }
            .ctc-pkg-admin-field input[type="text"],
            .ctc-pkg-admin-field input[type="number"],
            .ctc-pkg-admin-field textarea,
            .ctc-pkg-admin-field select { padding: 9px 12px; border-radius: 8px; border: 1px solid #CBD5E1; font-size: 13px; width: 100%; box-sizing: border-box; }
            .ctc-pkg-admin-hint { font-size: 11.5px; color: #64748B; margin-top: 2px; }
            .ctc-pkg-price-row { display: flex; align-items: center; gap: 10px; }
            .ctc-pkg-curr-badge { background: #F1F5F9; border: 1px solid #CBD5E1; padding: 9px 14px; border-radius: 8px; font-weight: 700; color: #334155; font-size: 13px; white-space: nowrap; }
        </style>

        <?php if (floatval($price) <= 0) : ?>
            <div style="background:#FEF3C7; border:1.5px solid #F59E0B; border-radius:10px; padding:12px 16px; margin-bottom:16px; color:#B45309; font-size:13px; font-weight:600;">
                <i class="dashicons dashicons-warning" style="vertical-align:middle; margin-right:4px;"></i>
                <?php esc_html_e('Price Configuration Required: Set an authoritative price in your store currency before activating this package.', 'caretochina-medical'); ?>
            </div>
        <?php endif; ?>

        <?php if ($ref_count > 0) : ?>
            <div style="background:#EFF6FF; border:1.5px solid #60A5FA; border-radius:10px; padding:12px 16px; margin-bottom:16px; color:#1E40AF; font-size:13px; font-weight:600;">
                <i class="dashicons dashicons-lock" style="vertical-align:middle; margin-right:4px;"></i>
                <?php
                /* translators: %d: Reference count */
                printf(esc_html__('Locked Deletion: This package is currently referenced by %d booking(s) and/or payment request(s). It cannot be permanently deleted (deactivate to hide from new bookings).', 'caretochina-medical'), $ref_count);
                ?>
            </div>
        <?php endif; ?>

        <div class="ctc-pkg-admin-grid">
            <!-- Price & Currency -->
            <div class="ctc-pkg-admin-field">
                <label for="ctc_pkg_price"><?php esc_html_e('Package Price *', 'caretochina-medical'); ?></label>
                <div class="ctc-pkg-price-row">
                    <span class="ctc-pkg-curr-badge"><?php echo esc_html($currency_symbol); ?></span>
                    <input type="number" step="0.01" min="0" name="ctc_pkg_price" id="ctc_pkg_price" value="<?php echo esc_attr($price !== '' ? $price : '0.00'); ?>" required>
                    <span class="ctc-pkg-curr-badge"><?php echo esc_html($currency); ?></span>
                </div>
                <span class="ctc-pkg-hint"><?php esc_html_e('Locked to store currency. Must be greater than 0 for active booking.', 'caretochina-medical'); ?></span>
            </div>

            <!-- Active Status -->
            <div class="ctc-pkg-admin-field">
                <label for="ctc_pkg_is_active"><?php esc_html_e('Active / Bookable Status *', 'caretochina-medical'); ?></label>
                <select name="ctc_pkg_is_active" id="ctc_pkg_is_active">
                    <option value="1" <?php selected($is_active, '1'); ?>><?php esc_html_e('Active (Visible in Booking Wizard & Single Hospital)', 'caretochina-medical'); ?></option>
                    <option value="0" <?php selected($is_active, '0'); ?>><?php esc_html_e('Inactive / Hidden (Cannot be booked)', 'caretochina-medical'); ?></option>
                </select>
                <span class="ctc-pkg-hint"><?php esc_html_e('Cannot be activated if price is 0.00.', 'caretochina-medical'); ?></span>
            </div>

            <!-- Tier Badge -->
            <div class="ctc-pkg-admin-field">
                <label for="ctc_pkg_badge"><?php esc_html_e('Tier Badge / Highlight Tag', 'caretochina-medical'); ?></label>
                <input type="text" name="ctc_pkg_badge" id="ctc_pkg_badge" value="<?php echo esc_attr($badge); ?>" placeholder="e.g. Ultimate VIP, Most Popular, Best Value">
            </div>

            <!-- Dedicated Vehicle -->
            <div class="ctc-pkg-admin-field">
                <label for="ctc_pkg_vehicle"><?php esc_html_e('Dedicated Vehicle Inclusions', 'caretochina-medical'); ?></label>
                <input type="text" name="ctc_pkg_vehicle" id="ctc_pkg_vehicle" value="<?php echo esc_attr($vehicle); ?>" placeholder="e.g. Luxury business vehicle / Premium business vehicle">
            </div>

            <!-- Dedicated Interpreter -->
            <div class="ctc-pkg-admin-field">
                <label for="ctc_pkg_interpreter"><?php esc_html_e('Dedicated Interpreter / Escort', 'caretochina-medical'); ?></label>
                <input type="text" name="ctc_pkg_interpreter" id="ctc_pkg_interpreter" value="<?php echo esc_attr($interpreter); ?>" placeholder="e.g. Driver + bilingual interpreter for airport transfers">
            </div>

            <!-- Accommodation -->
            <div class="ctc-pkg-admin-field">
                <label for="ctc_pkg_accommodation"><?php esc_html_e('Accommodation Details', 'caretochina-medical'); ?></label>
                <input type="text" name="ctc_pkg_accommodation" id="ctc_pkg_accommodation" value="<?php echo esc_attr($accommodation); ?>" placeholder="e.g. Luxury-brand hotel premium suite, 2 nights / No accommodation">
            </div>

            <!-- Dining -->
            <div class="ctc-pkg-admin-field">
                <label for="ctc_pkg_dining"><?php esc_html_e('Dining Arrangements', 'caretochina-medical'); ?></label>
                <input type="text" name="ctc_pkg_dining" id="ctc_pkg_dining" value="<?php echo esc_attr($dining); ?>" placeholder="e.g. Standard dining for 2 (premium custom menu) / No dining">
            </div>

            <!-- Patient Companion -->
            <div class="ctc-pkg-admin-field">
                <label for="ctc_pkg_companion"><?php esc_html_e('Patient Companion & Wellness Perks (Optional)', 'caretochina-medical'); ?></label>
                <input type="text" name="ctc_pkg_companion" id="ctc_pkg_companion" value="<?php echo esc_attr($companion); ?>" placeholder="e.g. 1 companion health check + TCM herbal regimen / Leave empty if none">
            </div>

            <!-- Travel & Leisure -->
            <div class="ctc-pkg-admin-field full">
                <label for="ctc_pkg_travel"><?php esc_html_e('Exclusive Travel & Leisure Experience', 'caretochina-medical'); ?></label>
                <textarea name="ctc_pkg_travel" id="ctc_pkg_travel" rows="2" placeholder="e.g. Canton Tower summit tour + Pearl River night cruise + Chimelong Safari"><?php echo esc_textarea($travel); ?></textarea>
            </div>

            <!-- Package Positioning -->
            <div class="ctc-pkg-admin-field full">
                <label for="ctc_pkg_positioning"><?php esc_html_e('Package Positioning / Summary Description', 'caretochina-medical'); ?></label>
                <textarea name="ctc_pkg_positioning" id="ctc_pkg_positioning" rows="2" placeholder="e.g. The highest-tier, one-stop premium medical escort experience..."><?php echo esc_textarea($positioning); ?></textarea>
            </div>

            <!-- Medical Coordination Inclusions -->
            <div class="ctc-pkg-admin-field full">
                <label for="ctc_pkg_medical_coordination"><?php esc_html_e('Medical Coordination Breakdown (Bullet Points / Scope)', 'caretochina-medical'); ?></label>
                <textarea name="ctc_pkg_medical_coordination" id="ctc_pkg_medical_coordination" rows="6" placeholder="• Medical Report Assessment&#10;• Video Consultation&#10;• VIP Fast-Track Access"><?php echo esc_textarea($coordination); ?></textarea>
                <span class="ctc-pkg-hint"><?php esc_html_e('Enter bullet points or lines describing medical coordination procedures and doctor consultation benefits.', 'caretochina-medical'); ?></span>
            </div>
        </div>
        <?php
    }

    /**
     * Save Meta Box Data
     */
    public function save_package_meta($post_id) {
        $nonce = isset($_POST['ctc_package_nonce']) ? sanitize_text_field(wp_unslash($_POST['ctc_package_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'save_ctc_package_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $price = isset($_POST['ctc_pkg_price']) ? floatval(wp_unslash($_POST['ctc_pkg_price'])) : 0.00;
        if ($price < 0) {
            $price = 0.00;
        }

        $is_active = isset($_POST['ctc_pkg_is_active']) ? (intval(wp_unslash($_POST['ctc_pkg_is_active'])) ? 1 : 0) : 0;
        // Enforce: price must be > 0 to be active
        if ($price <= 0) {
            $is_active = 0;
        }

        $badge         = isset($_POST['ctc_pkg_badge']) ? sanitize_text_field(wp_unslash($_POST['ctc_pkg_badge'])) : '';
        $vehicle       = isset($_POST['ctc_pkg_vehicle']) ? sanitize_text_field(wp_unslash($_POST['ctc_pkg_vehicle'])) : '';
        $interpreter   = isset($_POST['ctc_pkg_interpreter']) ? sanitize_text_field(wp_unslash($_POST['ctc_pkg_interpreter'])) : '';
        $accommodation = isset($_POST['ctc_pkg_accommodation']) ? sanitize_text_field(wp_unslash($_POST['ctc_pkg_accommodation'])) : '';
        $dining        = isset($_POST['ctc_pkg_dining']) ? sanitize_text_field(wp_unslash($_POST['ctc_pkg_dining'])) : '';
        $companion     = isset($_POST['ctc_pkg_companion']) ? sanitize_text_field(wp_unslash($_POST['ctc_pkg_companion'])) : '';
        $travel        = isset($_POST['ctc_pkg_travel']) ? sanitize_textarea_field(wp_unslash($_POST['ctc_pkg_travel'])) : '';
        $positioning   = isset($_POST['ctc_pkg_positioning']) ? sanitize_textarea_field(wp_unslash($_POST['ctc_pkg_positioning'])) : '';
        $coordination  = isset($_POST['ctc_pkg_medical_coordination']) ? sanitize_textarea_field(wp_unslash($_POST['ctc_pkg_medical_coordination'])) : '';

        update_post_meta($post_id, '_ctc_pkg_price', $price);
        update_post_meta($post_id, '_ctc_pkg_currency', self::get_store_currency());
        update_post_meta($post_id, '_ctc_pkg_badge', $badge);
        update_post_meta($post_id, '_ctc_pkg_vehicle', $vehicle);
        update_post_meta($post_id, '_ctc_pkg_interpreter', $interpreter);
        update_post_meta($post_id, '_ctc_pkg_accommodation', $accommodation);
        update_post_meta($post_id, '_ctc_pkg_dining', $dining);
        update_post_meta($post_id, '_ctc_pkg_companion', $companion);
        update_post_meta($post_id, '_ctc_pkg_travel', $travel);
        update_post_meta($post_id, '_ctc_pkg_positioning', $positioning);
        update_post_meta($post_id, '_ctc_pkg_medical_coordination', $coordination);
        update_post_meta($post_id, '_ctc_pkg_is_active', $is_active);
    }

    /**
     * Delete Protection Check (covers both caretochina_bookings AND caretochina_payment_requests)
     */
    public function check_delete_protection($post_id) {
        if (get_post_type($post_id) !== 'ctc_package') {
            return;
        }

        $ref_count = $this->get_package_reference_count($post_id);
        if ($ref_count > 0) {
            /* translators: %d: Reference count */
            $msg = sprintf(__('This Concierge Package is referenced by %d existing booking(s) and/or payment request(s) and cannot be permanently deleted. Please deactivate it instead to preserve financial and audit history.', 'caretochina-medical'), $ref_count);
            wp_die(
                '<h1>' . esc_html__('Action Prohibited', 'caretochina-medical') . '</h1>'
                . '<p>' . esc_html($msg) . '</p>'
                . '<p><a href="' . esc_url(admin_url('edit.php?post_type=ctc_package')) . '" class="button button-primary">' . esc_html__('Return to Packages List', 'caretochina-medical') . '</a></p>',
                esc_html__('Package Deletion Prohibited', 'caretochina-medical'),
                ['back_link' => true]
            );
        }
    }

    /**
     * Get Total Reference Count in Bookings and Payment Requests
     */
    public function get_package_reference_count($package_id) {
        global $wpdb;
        $package_id = intval($package_id);
        if ($package_id <= 0) {
            return 0;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $cnt_bookings = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}caretochina_bookings WHERE package_id = %d", $package_id));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $cnt_requests = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}caretochina_payment_requests WHERE package_id = %d", $package_id));

        return intval($cnt_bookings) + intval($cnt_requests);
    }

    /**
     * Retrieve all active packages with price > 0
     */
    public function get_active_packages() {
        $posts = get_posts([
            'post_type'      => 'ctc_package',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'menu_order title',
            'order'          => 'ASC',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => '_ctc_pkg_is_active',
                    'value'   => '1',
                    'compare' => '=',
                ],
                [
                    'key'     => '_ctc_pkg_price',
                    'value'   => 0,
                    'compare' => '>',
                    'type'    => 'DECIMAL(10,2)',
                ],
            ],
        ]);

        $packages = [];
        foreach ($posts as $p) {
            $packages[] = $this->format_package_object($p);
        }
        return $packages;
    }

    /**
     * Retrieve all packages (active or inactive)
     */
    public function get_all_packages() {
        $posts = get_posts([
            'post_type'      => 'ctc_package',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'menu_order title',
            'order'          => 'ASC',
        ]);

        $packages = [];
        foreach ($posts as $p) {
            $packages[] = $this->format_package_object($p);
        }
        return $packages;
    }

    /**
     * Retrieve single package by ID
     */
    public function get_package($package_id) {
        $package_id = intval($package_id);
        if ($package_id <= 0) {
            return null;
        }

        $post = get_post($package_id);
        if (!$post || $post->post_type !== 'ctc_package') {
            return null;
        }

        return $this->format_package_object($post);
    }

    /**
     * Format post into a clean Package object
     */
    private function format_package_object($post) {
        $id       = $post->ID;
        $price    = floatval(get_post_meta($id, '_ctc_pkg_price', true));
        $currency = self::get_store_currency();
        $symbol   = self::get_currency_symbol($currency);

        return (object) [
            'id'            => $id,
            'title'         => $post->post_title,
            'name'          => $post->post_title,
            'price'         => $price,
            'currency'      => $currency,
            'currency_symbol'=> $symbol,
            'price_formatted'=> $symbol . number_format($price, 2) . ' ' . $currency,
            'badge'         => get_post_meta($id, '_ctc_pkg_badge', true) ?: '',
            'vehicle'       => get_post_meta($id, '_ctc_pkg_vehicle', true) ?: '',
            'interpreter'   => get_post_meta($id, '_ctc_pkg_interpreter', true) ?: '',
            'accommodation' => get_post_meta($id, '_ctc_pkg_accommodation', true) ?: '',
            'dining'        => get_post_meta($id, '_ctc_pkg_dining', true) ?: '',
            'companion'     => get_post_meta($id, '_ctc_pkg_companion', true) ?: '',
            'travel'        => get_post_meta($id, '_ctc_pkg_travel', true) ?: '',
            'positioning'   => get_post_meta($id, '_ctc_pkg_positioning', true) ?: '',
            'coordination'  => get_post_meta($id, '_ctc_pkg_medical_coordination', true) ?: '',
            'is_active'     => intval(get_post_meta($id, '_ctc_pkg_is_active', true)),
            'order'         => $post->menu_order,
            'image'         => get_the_post_thumbnail_url($id, 'large') ?: '',
        ];
    }

    /**
     * Admin Notice: Prompt to set prices on zero-price seeded packages
     */
    public function display_package_admin_notices() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== 'ctc_package') {
            return;
        }

        $all = $this->get_all_packages();
        $zero_price_pkgs = [];
        foreach ($all as $pkg) {
            if ($pkg->price <= 0) {
                $zero_price_pkgs[] = $pkg->name;
            }
        }

        if (!empty($zero_price_pkgs)) {
            $currency = self::get_store_currency();
            ?>
            <div class="notice notice-warning is-dismissible" style="border-left-color:#F59E0B; padding:12px 16px; border-radius:8px; margin:16px 0;">
                <p style="margin:0; font-weight:600; color:#B45309; font-size:13px;">
                    <span class="dashicons dashicons-warning" style="vertical-align:middle; margin-right:4px;"></span>
                    <strong><?php esc_html_e('Action Required:', 'caretochina-medical'); ?></strong>
                    <?php
                    /* translators: 1: Currency code, 2: Packages list */
                    printf(
                        esc_html__('The following package(s) currently have a price of 0.00 and are inactive: %2$s. Please edit each package, configure its price in your store currency (%1$s), and activate it for patient booking.', 'caretochina-medical'),
                        '<strong>' . esc_html($currency) . '</strong>',
                        '<strong>' . esc_html(implode(', ', $zero_price_pkgs)) . '</strong>'
                    );
                    ?>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Custom Columns for ctc_package Admin List
     */
    public function register_admin_columns($columns) {
        $new_cols = [];
        $new_cols['cb']         = $columns['cb'];
        $new_cols['title']      = __('Package Title', 'caretochina-medical');
        $new_cols['price']      = __('Price (Store Currency)', 'caretochina-medical');
        $new_cols['badge']      = __('Badge', 'caretochina-medical');
        $new_cols['status']     = __('Status', 'caretochina-medical');
        $new_cols['usage']      = __('Bookings / Requests', 'caretochina-medical');
        $new_cols['menu_order'] = __('Order', 'caretochina-medical');
        return $new_cols;
    }

    public function render_admin_columns($column, $post_id) {
        $pkg = $this->get_package($post_id);
        if (!$pkg) return;

        switch ($column) {
            case 'price':
                if ($pkg->price > 0) {
                    echo '<strong style="color:#0F766E; font-size:14px;">' . esc_html($pkg->price_formatted) . '</strong>';
                } else {
                    echo '<span style="color:#EF4444; font-weight:700;">' . esc_html__('Not Set (0.00)', 'caretochina-medical') . '</span>';
                }
                break;
            case 'badge':
                echo !empty($pkg->badge) ? '<span style="background:#CCFBF1; color:#0F766E; padding:3px 8px; border-radius:6px; font-weight:700; font-size:11px;">' . esc_html($pkg->badge) . '</span>' : '—';
                break;
            case 'status':
                if ($pkg->is_active && $pkg->price > 0) {
                    echo '<span style="background:#D1FAE5; color:#065F46; padding:3px 8px; border-radius:6px; font-weight:700; font-size:11px;">' . esc_html__('Active', 'caretochina-medical') . '</span>';
                } else {
                    echo '<span style="background:#FEE2E2; color:#991B1B; padding:3px 8px; border-radius:6px; font-weight:700; font-size:11px;">' . esc_html__('Inactive', 'caretochina-medical') . '</span>';
                }
                break;
            case 'usage':
                $ref = $this->get_package_reference_count($post_id);
                echo '<span style="background:#F1F5F9; color:#475569; padding:3px 8px; border-radius:6px; font-weight:700; font-size:11px;">' . esc_html($ref) . ' ' . esc_html__('refs', 'caretochina-medical') . '</span>';
                break;
            case 'menu_order':
                $post = get_post($post_id);
                echo esc_html($post->menu_order);
                break;
        }
    }

    public function register_sortable_columns($columns) {
        $columns['menu_order'] = 'menu_order';
        return $columns;
    }

    /**
     * AJAX Endpoint: Return active packages for public wizard
     */
    public function handle_get_packages() {
        $packages = $this->get_active_packages();
        wp_send_json_success([
            'currency'      => self::get_store_currency(),
            'currency_symbol'=> self::get_currency_symbol(),
            'service_notes' => self::get_global_service_notes(),
            'packages'      => $packages ?: [],
        ]);
    }
}
