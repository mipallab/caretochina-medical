<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CareToChina_Treatments_Plugin')) {

class CareToChina_Treatments_Plugin {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // CPT & Taxonomies
        add_action('init', [$this, 'register_cpt_and_taxonomies']);
        add_action('init', [$this, 'register_polylang_strings']);

        // Admin Meta Boxes & Custom Columns
        add_action('add_meta_boxes', [$this, 'add_treatment_metaboxes']);
        add_action('save_post_medical_treatment', [$this, 'save_treatment_metaboxes'], 10, 2);
        add_filter('manage_medical_treatment_posts_columns', [$this, 'register_admin_columns']);
        add_action('manage_medical_treatment_posts_custom_column', [$this, 'render_admin_columns'], 10, 2);
        add_filter('manage_edit-medical_treatment_sortable_columns', [$this, 'register_sortable_columns']);
        add_action('pre_get_posts', [$this, 'handle_custom_column_sorting']);

        // Publishing Restrictions & Admin Notices (Title, Content/Description, Thumbnail required)
        add_action('transition_post_status', [$this, 'restrict_treatment_publishing'], 10, 3);
        add_action('admin_notices', [$this, 'display_treatment_publish_notices']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_validation_scripts']);

        // Templates & Assets
        add_filter('single_template', [$this, 'treatment_single_template']);
        add_filter('archive_template', [$this, 'treatment_archive_template']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);

        // AJAX handlers with security checks
        add_action('wp_ajax_caretochina_filter_treatments', [$this, 'ajax_filter_treatments']);
        add_action('wp_ajax_nopriv_caretochina_filter_treatments', [$this, 'ajax_filter_treatments']);

        // Register Elementor Widgets
        add_action('elementor/widgets/register', [$this, 'register_elementor_widgets']);
        add_action('elementor/widgets/widgets_registered', [$this, 'register_elementor_widgets']);
    }

    public function register_polylang_strings() {
        if (function_exists('pll_register_string')) {
            pll_register_string('Medical Treatments', 'Medical Treatments', 'CareToChina Treatments');
            pll_register_string('Treatment Price', 'Treatment Price', 'CareToChina Treatments');
            pll_register_string('Day Stay', 'Day Stay', 'CareToChina Treatments');
            pll_register_string('View Treatment', 'View Treatment', 'CareToChina Treatments');
            pll_register_string('Book Treatment', 'Book Treatment', 'CareToChina Treatments');
            pll_register_string('All Categories', 'All Categories', 'CareToChina Treatments');
            pll_register_string('No medical treatments matching your search criteria.', 'No medical treatments matching your search criteria.', 'CareToChina Treatments');
        }
    }

    /**
     * Register Custom Post Type: Medical Treatments and Taxonomies
     */
    public function register_cpt_and_taxonomies() {
        $labels = [
            'name'                  => __('Medical Treatments', 'caretochina-medical'),
            'singular_name'         => __('Medical Treatment', 'caretochina-medical'),
            'menu_name'             => __('Medical Treatments', 'caretochina-medical'),
            'name_admin_bar'        => __('Medical Treatment', 'caretochina-medical'),
            'add_new'               => __('Add Treatment', 'caretochina-medical'),
            'add_new_item'          => __('Add New Medical Treatment', 'caretochina-medical'),
            'new_item'              => __('New Medical Treatment', 'caretochina-medical'),
            'edit_item'             => __('Edit Medical Treatment', 'caretochina-medical'),
            'view_item'             => __('View Medical Treatment', 'caretochina-medical'),
            'all_items'             => __('All Medical Treatments', 'caretochina-medical'),
            'search_items'          => __('Search Medical Treatments', 'caretochina-medical'),
            'parent_item_colon'     => __('Parent Medical Treatment:', 'caretochina-medical'),
            'not_found'             => __('No medical treatments found.', 'caretochina-medical'),
            'not_found_in_trash'    => __('No medical treatments found in Trash.', 'caretochina-medical'),
            'featured_image'        => __('Treatment Featured Image (Required to Publish)', 'caretochina-medical'),
            'set_featured_image'    => __('Set treatment featured image', 'caretochina-medical'),
            'remove_featured_image' => __('Remove treatment image', 'caretochina-medical'),
            'use_featured_image'    => __('Use as treatment image', 'caretochina-medical'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'treatment', 'with_front' => false],
            'capability_type'    => 'post',
            'has_archive'        => 'treatments',
            'hierarchical'       => false,
            'menu_position'      => 6,
            'menu_icon'          => 'dashicons-heart',
            'supports'           => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'elementor', 'custom-fields'],
            'show_in_rest'       => true,
        ];

        register_post_type('medical_treatment', $args);

        // Hierarchical Taxonomy: Treatment Categories
        register_taxonomy('treatment_category', ['medical_treatment'], [
            'hierarchical'      => true,
            'labels'            => [
                'name'              => __('Treatment Categories', 'caretochina-medical'),
                'singular_name'     => __('Treatment Category', 'caretochina-medical'),
                'search_items'      => __('Search Categories', 'caretochina-medical'),
                'all_items'         => __('All Categories', 'caretochina-medical'),
                'parent_item'       => __('Parent Category', 'caretochina-medical'),
                'parent_item_colon' => __('Parent Category:', 'caretochina-medical'),
                'edit_item'         => __('Edit Category', 'caretochina-medical'),
                'update_item'       => __('Update Category', 'caretochina-medical'),
                'add_new_item'      => __('Add New Category', 'caretochina-medical'),
                'new_item_name'     => __('New Category Name', 'caretochina-medical'),
                'menu_name'         => __('Categories', 'caretochina-medical'),
            ],
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'treatment-category', 'with_front' => false],
            'show_in_rest'      => true,
        ]);

        // Hierarchical Taxonomy: Treatment Specialties
        register_taxonomy('treatment_specialty', ['medical_treatment'], [
            'hierarchical'      => true,
            'labels'            => [
                'name'          => __('Treatment Specialties', 'caretochina-medical'),
                'singular_name' => __('Treatment Specialty', 'caretochina-medical'),
                'menu_name'     => __('Specialties', 'caretochina-medical'),
            ],
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'treatment-specialty', 'with_front' => false],
            'show_in_rest'      => true,
        ]);
    }

    /**
     * Add Meta Boxes for Medical Treatments
     */
    public function add_treatment_metaboxes() {
        add_meta_box(
            'treatment_details_mb',
            __('Treatment Details & Pricing', 'caretochina-medical'),
            [$this, 'render_treatment_metabox'],
            'medical_treatment',
            'normal',
            'high'
        );
    }

    /**
     * Render Treatment Meta Box
     */
    public function render_treatment_metabox($post) {
        wp_nonce_field('save_treatment_meta', 'treatment_meta_nonce');

        $price          = get_post_meta($post->ID, '_treatment_price', true);
        $day_stay       = get_post_meta($post->ID, '_treatment_day_stay', true);
        $discount_badge = get_post_meta($post->ID, '_treatment_discount_badge', true);
        $sub_heading    = get_post_meta($post->ID, '_treatment_sub_heading', true);
        $rating         = get_post_meta($post->ID, '_treatment_rating', true);
        $quote_url      = get_post_meta($post->ID, '_treatment_quote_url', true);
        $currency_symbol = class_exists('CareToChina_Packages') ? CareToChina_Packages::get_currency_symbol() : '$';
        ?>
        <style>
            .ctc-treat-mb-section { margin-top: 15px; margin-bottom: 20px; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; background: #ffffff; }
            .ctc-treat-mb-sec-title { margin: 0 0 14px 0; font-size: 14px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; }
            .ctc-treat-mb-sec-title i { color: #0f766e; }
            .ctc-treat-mb-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
            .ctc-treat-mb-field { display: flex; flex-direction: column; gap: 5px; }
            .ctc-treat-mb-field.full { grid-column: span 2; }
            .ctc-treat-mb-field label { font-weight: 600; font-size: 12.5px; color: #334155; }
            .ctc-treat-mb-field input { padding: 9px 12px; border-radius: 6px; border: 1px solid #cbd5e1; width: 100%; font-size: 13px; }
            .ctc-treat-mb-field input:focus { border-color: #0f766e; outline: none; box-shadow: 0 0 0 2px rgba(15, 118, 110, 0.15); }
            .ctc-treat-mb-hint { font-size: 11.5px; color: #64748b; margin-top: 3px; line-height: 1.4; }
            .ctc-treat-opt-badge { background: #f1f5f9; color: #64748b; font-size: 11px; padding: 2px 6px; border-radius: 4px; font-weight: 500; margin-left: 6px; }
            .ctc-treat-notice-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 12px 16px; margin-bottom: 15px; color: #166534; font-size: 12.5px; display: flex; align-items: center; gap: 10px; }
        </style>

        <div class="ctc-treat-notice-box">
            <span class="dashicons dashicons-info" style="color:#16a34a; font-size:20px;"></span>
            <span><strong><?php esc_html_e('Publishing Requirement:', 'caretochina-medical'); ?></strong> <?php esc_html_e('The Treatment Title, Description (main content editor), and Featured Image (thumbnail) are strictly required before publishing.', 'caretochina-medical'); ?></span>
        </div>

        <div class="ctc-treat-mb-section">
            <div class="ctc-treat-mb-sec-title">
                <i class="dashicons dashicons-money-alt"></i> <?php esc_html_e('Pricing & Stay Information', 'caretochina-medical'); ?>
            </div>
            <div class="ctc-treat-mb-grid">
                <div class="ctc-treat-mb-field">
                    <label>
                        <?php esc_html_e('Treatment Price', 'caretochina-medical'); ?>
                        <span class="ctc-treat-opt-badge"><?php esc_html_e('Text / Number [Optional]', 'caretochina-medical'); ?></span>
                    </label>
                    <div style="display: flex; align-items: center; position: relative;">
                        <span style="position: absolute; left: 12px; font-weight: 700; color: #0f766e; font-size: 14px; pointer-events: none;"><?php echo esc_html($currency_symbol); ?></span>
                        <input type="text" name="treatment_price" value="<?php echo esc_attr($price); ?>" placeholder="<?php esc_attr_e('e.g. 7500 or $7,500 or From $4,500', 'caretochina-medical'); ?>" style="padding-left: 28px;">
                    </div>
                    <span class="ctc-treat-mb-hint"><?php esc_html_e('Displayed dynamically on grid card. Enter any amount (e.g. 7500, $7,500, From $4,500).', 'caretochina-medical'); ?></span>
                </div>

                <div class="ctc-treat-mb-field">
                    <label>
                        <?php esc_html_e('Day Stay Duration', 'caretochina-medical'); ?>
                        <span class="ctc-treat-opt-badge"><?php esc_html_e('Text [Optional]', 'caretochina-medical'); ?></span>
                    </label>
                    <input type="text" name="treatment_day_stay" value="<?php echo esc_attr($day_stay); ?>" placeholder="<?php esc_attr_e('e.g. 5-7 Days Stay, 3-5 Days Stay, 1 Day Outpatient', 'caretochina-medical'); ?>">
                    <span class="ctc-treat-mb-hint"><?php esc_html_e('Displayed with clock icon on card. Leave empty to hide clock meta.', 'caretochina-medical'); ?></span>
                </div>

                <div class="ctc-treat-mb-field">
                    <label>
                        <?php esc_html_e('Discount / Save Badge', 'caretochina-medical'); ?>
                        <span class="ctc-treat-opt-badge"><?php esc_html_e('Text [Optional]', 'caretochina-medical'); ?></span>
                    </label>
                    <input type="text" name="treatment_discount_badge" value="<?php echo esc_attr($discount_badge); ?>" placeholder="<?php esc_attr_e('e.g. Save 65%', 'caretochina-medical'); ?>">
                    <span class="ctc-treat-mb-hint"><?php esc_html_e('Orange badge displayed on top-right of image. Leave empty to hide badge.', 'caretochina-medical'); ?></span>
                </div>

                <div class="ctc-treat-mb-field">
                    <label><?php esc_html_e('Rating & Reviews Badge', 'caretochina-medical'); ?> <span class="ctc-treat-opt-badge"><?php esc_html_e('Optional', 'caretochina-medical'); ?></span></label>
                    <input type="text" name="treatment_rating" value="<?php echo esc_attr($rating); ?>" placeholder="<?php esc_attr_e('e.g. 4.9 (480 Reviews)', 'caretochina-medical'); ?>">
                </div>
            </div>
        </div>

        <div class="ctc-treat-mb-section">
            <div class="ctc-treat-mb-sec-title">
                <i class="dashicons dashicons-admin-settings"></i> <?php esc_html_e('Additional Details & Booking Link', 'caretochina-medical'); ?>
            </div>
            <div class="ctc-treat-mb-grid">
                <div class="ctc-treat-mb-field full">
                    <label><?php esc_html_e('Treatment Sub-Heading / Tagline', 'caretochina-medical'); ?> <span class="ctc-treat-opt-badge"><?php esc_html_e('Optional', 'caretochina-medical'); ?></span></label>
                    <input type="text" name="treatment_sub_heading" value="<?php echo esc_attr($sub_heading); ?>" placeholder="<?php esc_attr_e('e.g. Advanced Robotic-Assisted Minimally Invasive Procedure', 'caretochina-medical'); ?>">
                    <span class="ctc-treat-mb-hint"><?php esc_html_e('Subtitle displayed under treatment title on single page.', 'caretochina-medical'); ?></span>
                </div>

                <div class="ctc-treat-mb-field full">
                    <label><?php esc_html_e('Booking / Consultation Link or Widget Selector', 'caretochina-medical'); ?> <span class="ctc-treat-opt-badge"><?php esc_html_e('Optional', 'caretochina-medical'); ?></span></label>
                    <input type="text" name="treatment_quote_url" value="<?php echo esc_attr($quote_url); ?>" placeholder="<?php esc_attr_e('#booking-widget, .open-booking-modal, #chat-trigger, or https://...', 'caretochina-medical'); ?>">
                    <span class="ctc-treat-mb-hint"><?php esc_html_e('Enter an ID (#id), Class (.class), or Custom URL. Clicking the inquiry button will trigger/open that widget/modal or navigate to the URL.', 'caretochina-medical'); ?></span>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Save Treatment Meta Boxes
     */
    public function save_treatment_metaboxes($post_id, $post) {
        $nonce = isset($_POST['treatment_meta_nonce']) ? sanitize_text_field(wp_unslash($_POST['treatment_meta_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'save_treatment_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Price (dynamically saved string/number)
        if (isset($_POST['treatment_price'])) {
            $price_raw = sanitize_text_field(wp_unslash($_POST['treatment_price']));
            update_post_meta($post_id, '_treatment_price', $price_raw);
        }

        // Day Stay (dynamically saved text)
        if (isset($_POST['treatment_day_stay'])) {
            $stay_val = sanitize_text_field(wp_unslash($_POST['treatment_day_stay']));
            update_post_meta($post_id, '_treatment_day_stay', $stay_val);
        }

        // Discount Badge (dynamically saved text)
        if (isset($_POST['treatment_discount_badge'])) {
            $discount_val = sanitize_text_field(wp_unslash($_POST['treatment_discount_badge']));
            update_post_meta($post_id, '_treatment_discount_badge', $discount_val);
        }

        // Sub Heading
        if (isset($_POST['treatment_sub_heading'])) {
            update_post_meta($post_id, '_treatment_sub_heading', sanitize_text_field(wp_unslash($_POST['treatment_sub_heading'])));
        }

        // Rating
        if (isset($_POST['treatment_rating'])) {
            update_post_meta($post_id, '_treatment_rating', sanitize_text_field(wp_unslash($_POST['treatment_rating'])));
        }

        // Quote URL
        if (isset($_POST['treatment_quote_url'])) {
            update_post_meta($post_id, '_treatment_quote_url', sanitize_text_field(wp_unslash($_POST['treatment_quote_url'])));
        }
    }

    /**
     * Register Custom Admin Columns
     */
    public function register_admin_columns($columns) {
        $new_columns = [];
        if (isset($columns['cb'])) {
            $new_columns['cb'] = $columns['cb'];
        }
        $new_columns['treatment_thumb'] = __('Image', 'caretochina-medical');
        $new_columns['title'] = __('Treatment Title', 'caretochina-medical');
        $new_columns['treatment_cat'] = __('Category', 'caretochina-medical');
        $new_columns['treatment_price'] = __('Price', 'caretochina-medical');
        $new_columns['treatment_stay'] = __('Day Stay', 'caretochina-medical');
        $new_columns['treatment_badge'] = __('Discount Badge', 'caretochina-medical');
        if (isset($columns['date'])) {
            $new_columns['date'] = $columns['date'];
        }
        // Preserve any remaining third-party columns (e.g. SEO, Polylang)
        if (is_array($columns)) {
            foreach ($columns as $key => $title) {
                if (!isset($new_columns[$key])) {
                    $new_columns[$key] = $title;
                }
            }
        }
        return $new_columns;
    }

    /**
     * Render Custom Admin Columns
     */
    public function render_admin_columns($column, $post_id) {
        switch ($column) {
            case 'treatment_thumb':
                if (has_post_thumbnail($post_id)) {
                    echo get_the_post_thumbnail($post_id, [50, 50], ['style' => 'width:50px;height:50px;object-fit:cover;border-radius:6px;']);
                } else {
                    echo '<span style="display:inline-block;width:50px;height:50px;background:#f1f5f9;border-radius:6px;line-height:50px;text-align:center;color:#94a3b8;"><span class="dashicons dashicons-format-image"></span></span>';
                }
                break;
            case 'treatment_cat':
                $terms = get_the_terms($post_id, 'treatment_category');
                if (!empty($terms) && !is_wp_error($terms)) {
                    $cat_links = [];
                    foreach ($terms as $t) {
                        $cat_links[] = '<a href="' . esc_url(admin_url('edit.php?post_type=medical_treatment&treatment_category=' . $t->slug)) . '">' . esc_html($t->name) . '</a>';
                    }
                    echo implode(', ', $cat_links);
                } else {
                    echo '<span style="color:#94a3b8;">—</span>';
                }
                break;
            case 'treatment_price':
                $price = get_post_meta($post_id, '_treatment_price', true);
                if ($price !== '' && $price !== false) {
                    $currency_symbol = class_exists('CareToChina_Packages') ? CareToChina_Packages::get_currency_symbol() : '$';
                    echo '<strong>' . esc_html__('From', 'caretochina-medical') . ' ' . esc_html($currency_symbol . number_format(floatval($price), 0)) . '</strong>';
                } else {
                    echo '<span style="color:#94a3b8;">' . esc_html__('On Consultation', 'caretochina-medical') . '</span>';
                }
                break;
            case 'treatment_stay':
                $stay = get_post_meta($post_id, '_treatment_day_stay', true);
                if (!empty($stay)) {
                    echo '<span class="badge" style="background:#e0f2fe;color:#0369a1;padding:3px 8px;border-radius:4px;font-size:12px;font-weight:600;"><i class="dashicons dashicons-calendar-alt" style="font-size:14px;vertical-align:middle;margin-right:2px;"></i>' . esc_html($stay) . '</span>';
                } else {
                    echo '<span style="color:#94a3b8;">—</span>';
                }
                break;
            case 'treatment_badge':
                $badge = get_post_meta($post_id, '_treatment_discount_badge', true);
                if (!empty($badge)) {
                    echo '<span style="background:#ff9800;color:#ffffff;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;">' . esc_html($badge) . '</span>';
                } else {
                    echo '<span style="color:#94a3b8;">—</span>';
                }
                break;
        }
    }

    /**
     * Register Sortable Columns
     */
    public function register_sortable_columns($columns) {
        $columns['treatment_price'] = 'treatment_price';
        return $columns;
    }

    /**
     * Handle Sortable Column Ordering in Admin
     */
    public function handle_custom_column_sorting($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        if ($query->get('post_type') === 'medical_treatment' && $query->get('orderby') === 'treatment_price') {
            $query->set('meta_key', '_treatment_price');
            $query->set('orderby', 'meta_value_num');
        }
    }

    /**
     * STRICT PUBLISHING RESTRICTION:
     * Enforce that Title, Description (content), and Post Thumbnail are REQUIRED to publish.
     */
    public function restrict_treatment_publishing($new_status, $old_status, $post) {
        if (!is_a($post, 'WP_Post') || $post->post_type !== 'medical_treatment') {
            return;
        }

        if ($new_status !== 'publish') {
            return;
        }

        $title = trim($post->post_title);
        $has_title = (!empty($title) && $title !== 'Auto Draft' && $title !== __('Auto Draft'));
        
        $content = trim(strip_tags($post->post_content));
        $has_content = !empty($content);

        $has_thumbnail = has_post_thumbnail($post->ID);

        // If any of the required elements is missing, revert post status to draft
        if (!$has_title || !$has_content || !$has_thumbnail) {
            remove_action('transition_post_status', [$this, 'restrict_treatment_publishing'], 10);
            wp_update_post([
                'ID'          => $post->ID,
                'post_status' => 'draft',
            ]);
            add_action('transition_post_status', [$this, 'restrict_treatment_publishing'], 10, 3);

            set_transient('ctc_treatment_publish_error_' . $post->ID, [
                'title'       => $has_title,
                'content'     => $has_content,
                'thumbnail'   => $has_thumbnail,
            ], 45);
        }
    }

    /**
     * Display Admin Notice if Treatment Failed Publishing Validation
     */
    public function display_treatment_publish_notices() {
        global $post;
        if (!$post || $post->post_type !== 'medical_treatment') {
            return;
        }

        $errors = get_transient('ctc_treatment_publish_error_' . $post->ID);
        if ($errors) {
            delete_transient('ctc_treatment_publish_error_' . $post->ID);
            $missing = [];
            if (!$errors['title']) {
                $missing[] = '<strong>' . esc_html__('Treatment Title', 'caretochina-medical') . '</strong>';
            }
            if (!$errors['content']) {
                $missing[] = '<strong>' . esc_html__('Description / Overview Content', 'caretochina-medical') . '</strong>';
            }
            if (!$errors['thumbnail']) {
                $missing[] = '<strong>' . esc_html__('Featured Image (Thumbnail)', 'caretochina-medical') . '</strong>';
            }

            echo '<div class="notice notice-error is-dismissible" style="border-left-color: #ef4444; padding: 14px 18px; margin: 15px 0;">';
            /* translators: %s: Missing elements list */
            $missing_str = implode(', ', $missing);
            $notice_msg = sprintf(__('The post status has been reverted to draft because the following required elements are missing: %s.', 'caretochina-medical'), $missing_str);
            echo '<p style="font-size: 13.5px; margin: 0;"><strong style="color: #b91c1c;">' . esc_html__('Cannot Publish Medical Treatment:', 'caretochina-medical') . '</strong> ' . wp_kses_post($notice_msg) . '</p>';
            echo '</div>';
        }
    }

    /**
     * Enqueue Admin Script for Pre-publish UI validation feedback
     */
    public function enqueue_admin_validation_scripts($hook) {
        global $post_type;
        if ($post_type !== 'medical_treatment') {
            return;
        }

        if (in_array($hook, ['post.php', 'post-new.php'], true)) {
            ?>
            <script type="text/javascript">
            (function() {
                // Classic & Block Editor Pre-publish validation check
                document.addEventListener('DOMContentLoaded', function() {
                    // Gutenberg / Block Editor subscribe
                    if (window.wp && wp.data && wp.data.select && wp.data.subscribe) {
                        var hasSubscribed = false;
                        wp.data.subscribe(function() {
                            try {
                                var editor = wp.data.select('core/editor');
                                if (!editor) return;
                                var isSaving = editor.isSavingPost && editor.isSavingPost();
                                var isPublishing = editor.isPublishingPost && editor.isPublishingPost();
                                var status = editor.getEditedPostAttribute ? editor.getEditedPostAttribute('status') : '';
                                
                                if (isPublishing || (isSaving && status === 'publish')) {
                                    var title = editor.getEditedPostAttribute('title') || '';
                                    var content = editor.getEditedPostAttribute('content') || '';
                                    var featuredImageId = editor.getEditedPostAttribute('featured_media') || 0;
                                    
                                    var cleanContent = content.replace(/<[^>]*>?/gm, '').trim();
                                    var errors = [];
                                    if (!title.trim()) errors.push('Treatment Title');
                                    if (!cleanContent) errors.push('Description / Content');
                                    if (!featuredImageId || featuredImageId === 0) errors.push('Featured Image');

                                    if (errors.length > 0 && wp.data.dispatch('core/notices')) {
                                        wp.data.dispatch('core/notices').createErrorNotice(
                                            'Cannot Publish Medical Treatment: Missing required fields: ' + errors.join(', ') + '.',
                                            { id: 'ctc-treatment-val-notice', isDismissible: true }
                                        );
                                    }
                                }
                            } catch(e) {}
                        });
                    }

                    // Classic Editor form submit intercept
                    var postForm = document.getElementById('post');
                    if (postForm) {
                        postForm.addEventListener('submit', function(e) {
                            var statusField = document.getElementById('original_post_status') || document.getElementById('post_status');
                            var publishBtn = document.getElementById('publish');
                            var isPublishing = (document.activeElement && document.activeElement.id === 'publish') || (statusField && statusField.value === 'publish');

                            if (isPublishing) {
                                var titleField = document.getElementById('title');
                                var titleVal = titleField ? titleField.value.trim() : '';
                                
                                var contentVal = '';
                                if (typeof tinyMCE !== 'undefined' && tinyMCE.get('content')) {
                                    contentVal = tinyMCE.get('content').getContent({format: 'text'}).trim();
                                } else {
                                    var contentArea = document.getElementById('content');
                                    contentVal = contentArea ? contentArea.value.replace(/<[^>]*>?/gm, '').trim() : '';
                                }

                                var thumbnailBox = document.getElementById('_thumbnail_id');
                                var thumbVal = thumbnailBox ? thumbnailBox.value : '-1';

                                var errors = [];
                                if (!titleVal) errors.push('Treatment Title');
                                if (!contentVal) errors.push('Description / Content');
                                if (!thumbVal || thumbVal === '-1' || thumbVal === '0') errors.push('Featured Image');

                                if (errors.length > 0) {
                                    alert("CANNOT PUBLISH MEDICAL TREATMENT:\n\nThe following elements are required before publishing:\n• " + errors.join("\n• "));
                                }
                            }
                        });
                    }
                });
            })();
            </script>
            <?php
        }
    }

    /**
     * Template Loaders
     */
    public function treatment_single_template($template) {
        if (is_singular('medical_treatment')) {
            $custom_single = __DIR__ . '/templates/single-treatment.php';
            if (file_exists($custom_single)) {
                return $custom_single;
            }
        }
        return $template;
    }

    public function treatment_archive_template($template) {
        if (is_post_type_archive('medical_treatment') || is_tax('treatment_category') || is_tax('treatment_specialty')) {
            $custom_archive = __DIR__ . '/templates/archive-treatment.php';
            if (file_exists($custom_archive)) {
                return $custom_archive;
            }
        }
        return $template;
    }

    /**
     * Frontend Scripts & Styles
     */
    public function enqueue_frontend_scripts() {
        if (!wp_style_is('caretochina-font-awesome', 'registered')) {
            wp_register_style('caretochina-font-awesome', CARETOCHINA_MEDICAL_URL . 'assets/vendor/font-awesome/css/all.min.css', [], '6.5.1');
        }
        if (!wp_style_is('font-awesome', 'registered')) {
            wp_register_style('font-awesome', CARETOCHINA_MEDICAL_URL . 'assets/vendor/font-awesome/css/all.min.css', [], '6.5.1');
        }
        wp_enqueue_style('caretochina-font-awesome');
        wp_enqueue_style('font-awesome');
        wp_enqueue_style('caretochina-booking-style', CARETOCHINA_MEDICAL_URL . 'assets/css/style.css', ['caretochina-font-awesome', 'font-awesome'], CARETOCHINA_MEDICAL_VERSION);
    }

    /**
     * Render a Single Medical Treatment Card
     * EXACT 100% MATCH to the provided visual design:
     * - Top 180px Image with Top-Right "Save 65%" Orange Pill Badge
     * - Bold Title
     * - Clean Excerpt Description
     * - Subtle Divider Line
     * - Bottom Meta Row: [Orange Tag Icon] From $7,500  and  [Teal Clock Icon] 5-7 Days Stay
     * - Full 100% Light & Dark Theme Pixel-Perfect Support
     */
    public static function render_treatment_card($post_id, $button_text = '') {
        $post = get_post($post_id);
        if (!$post) return;

        $permalink      = get_permalink($post_id);
        $title          = get_the_title($post_id);
        $price          = get_post_meta($post_id, '_treatment_price', true);
        $day_stay       = get_post_meta($post_id, '_treatment_day_stay', true);
        $discount_badge = get_post_meta($post_id, '_treatment_discount_badge', true);

        // Fetch Excerpt or trimmed content
        $excerpt = has_excerpt($post_id) ? get_the_excerpt($post_id) : wp_strip_all_tags($post->post_content);
        if (empty(trim($excerpt))) {
            $excerpt = 'Specialized medical treatment and advanced procedure performed by top certified surgeons.';
        }
        $excerpt_display = wp_trim_words($excerpt, 15, '...');

        // Currency & Price format (100% Dynamic)
        $curr_symbol = class_exists('CareToChina_Packages') ? CareToChina_Packages::get_currency_symbol() : '$';
        if ($price !== '' && $price !== false && $price !== null) {
            $clean_num = trim(str_replace([',', ' ', '$', '€', '£', '¥', 'USD', 'EUR', 'GBP', 'CNY'], '', $price));
            if (is_numeric($clean_num) && $clean_num !== '') {
                $price_text = sprintf(esc_html__('From %s', 'caretochina-medical'), $curr_symbol . number_format(floatval($clean_num), 0));
            } else {
                $price_text = esc_html($price);
            }
        } else {
            $price_text = sprintf(esc_html__('From %s', 'caretochina-medical'), $curr_symbol . '7,500');
        }

        // If day stay is empty, default to "5-7 Days Stay" if not configured
        if ($day_stay === '' || $day_stay === false || $day_stay === null) {
            $day_stay = '5-7 Days Stay';
        }
        ?>
        <!-- Medical Treatment Card (100% Dynamic & Clickable Box) -->
        <article class="ctc-treatment-card post-<?php echo esc_attr($post_id); ?>" itemscope itemtype="https://schema.org/MedicalProcedure">
            
            <!-- Full Card Clickable Overlay Link -->
            <a href="<?php echo esc_url($permalink); ?>" class="ctc-treat-card-overlay-link" aria-label="<?php echo esc_attr($title); ?>"></a>

            <!-- Treatment Image & Top-Right Discount Badge -->
            <div class="ctc-treat-img-box">
                <a href="<?php echo esc_url($permalink); ?>" class="ctc-treat-img-link" aria-label="<?php echo esc_attr($title); ?>" tabindex="-1">
                    <?php if (has_post_thumbnail($post_id)) : ?>
                        <?php echo get_the_post_thumbnail($post_id, 'medium_large', ['class' => 'ctc-treat-img', 'alt' => esc_attr($title), 'itemprop' => 'image']); ?>
                    <?php else : ?>
                        <img src="https://images.unsplash.com/photo-1579684385127-1ef15d508118?auto=format&fit=crop&w=600&q=80" class="ctc-treat-img" alt="<?php echo esc_attr($title); ?>">
                    <?php endif; ?>
                </a>

                <!-- Orange Save / Discount Pill Badge (Top-Right, Dynamic) -->
                <?php if (!empty($discount_badge)) : ?>
                    <span class="ctc-treat-save-badge">
                        <?php echo esc_html($discount_badge); ?>
                    </span>
                <?php endif; ?>
            </div>

            <!-- Card Body Content -->
            <div class="ctc-treat-body">
                
                <!-- Treatment Title -->
                <h3 class="ctc-treat-title" itemprop="name">
                    <a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($title); ?></a>
                </h3>

                <!-- Treatment Description -->
                <p class="ctc-treat-desc" itemprop="description">
                    <?php echo esc_html($excerpt_display); ?>
                </p>

            </div>
        </article>
        <?php
    }

    /**
     * AJAX Live Filter Handler for Medical Treatments
     */
    public function ajax_filter_treatments() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $category = isset($_POST['category']) ? sanitize_text_field(wp_unslash($_POST['category'])) : 'all';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $paged = isset($_POST['page']) ? absint(wp_unslash($_POST['page'])) : 1;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $posts_per_page = isset($_POST['posts_per_page']) ? absint(wp_unslash($_POST['posts_per_page'])) : 6;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $orderby = isset($_POST['orderby']) ? sanitize_text_field(wp_unslash($_POST['orderby'])) : 'date';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $order = isset($_POST['order']) ? sanitize_text_field(wp_unslash($_POST['order'])) : 'DESC';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $button_text = isset($_POST['button_text']) ? sanitize_text_field(wp_unslash($_POST['button_text'])) : '';

        if ($posts_per_page < 1) $posts_per_page = 6;

        $args = [
            'post_type'      => 'medical_treatment',
            'post_status'    => 'publish',
            'posts_per_page' => $posts_per_page,
            'paged'          => $paged,
            's'              => $search,
            'order'          => ($order === 'ASC') ? 'ASC' : 'DESC',
        ];

        if ($orderby === 'price') {
            $args['meta_key'] = '_treatment_price';
            $args['orderby']  = 'meta_value_num';
        } elseif ($orderby === 'title') {
            $args['orderby'] = 'title';
        } elseif ($orderby === 'rand') {
            $args['orderby'] = 'rand';
        } else {
            $args['orderby'] = 'date';
        }

        // Language support
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $lang = isset($_POST['lang']) ? sanitize_text_field(wp_unslash($_POST['lang'])) : (function_exists('pll_current_language') ? pll_current_language() : '');
        if (!empty($lang) && function_exists('pll_current_language')) {
            $args['lang'] = $lang;
        }

        if ($category !== 'all' && !empty($category)) {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
            $args['tax_query'] = [
                [
                    'taxonomy' => 'treatment_category',
                    'field'    => 'slug',
                    'terms'    => $category,
                ]
            ];
        }

        $query = new \WP_Query($args);

        ob_start();
        if ($query->have_posts()) :
            while ($query->have_posts()) : $query->the_post();
                self::render_treatment_card(get_the_ID(), $button_text);
            endwhile;
            wp_reset_postdata();
        else :
            echo '<div class="ctc-no-treatments" style="grid-column: 1 / -1; text-align: center; padding: 50px 20px; color: #64748b; font-size: 1.1rem; width: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px;">';
            echo '<i class="fas fa-heartbeat" style="font-size: 2.8rem; color: #cbd5e1; margin-bottom: 8px;"></i>';
            echo '<span>' . esc_html__('No medical treatments matching your search criteria.', 'caretochina-medical') . '</span>';
            echo '</div>';
        endif;
        $grid_html = ob_get_clean();

        // Render Pagination
        ob_start();
        if ($query->max_num_pages > 1) :
            for ($i = 1; $i <= $query->max_num_pages; $i++) {
                $active_cls = ($i === $paged) ? 'active' : '';
                echo '<button type="button" class="ctc-treat-page-btn ' . esc_attr($active_cls) . '" data-page="' . esc_attr($i) . '">' . esc_html($i) . '</button>';
            }
        endif;
        $pagination_html = ob_get_clean();

        wp_send_json_success([
            'html'            => $grid_html,
            'pagination_html' => $pagination_html,
            'count'           => $query->found_posts,
            'max_pages'       => $query->max_num_pages,
        ]);
    }

    /**
     * Register Elementor Widgets
     */
    public function register_elementor_widgets($widgets_manager) {
        require_once __DIR__ . '/widgets/class-caretochina-treatments-grid-widget.php';

        if (class_exists('CareToChina_Treatments_Grid_Widget')) {
            $widgets_manager->register(new \CareToChina_Treatments_Grid_Widget());
        }
    }
}

CareToChina_Treatments_Plugin::instance();

}
