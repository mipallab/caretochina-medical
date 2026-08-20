<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('CareToChina_Hospitals_Plugin')) {

class CareToChina_Hospitals_Plugin {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('init', [$this, 'register_cpt_and_taxonomies']);
        add_action('init', [$this, 'register_polylang_strings']);
        add_action('init', [$this, 'clean_legacy_hospital_contact_meta']);
        add_action('add_meta_boxes', [$this, 'add_hospital_metaboxes']);
        add_action('save_post_hospital', [$this, 'save_hospital_metaboxes']);
        add_filter('single_template', [$this, 'hospital_single_template']);
        add_action('transition_post_status', [$this, 'restrict_hospital_publishing'], 10, 3);
        add_action('admin_notices', [$this, 'display_hospital_publish_notices']);

        // AJAX handlers with security checks
        add_action('wp_ajax_caretochina_filter_hospitals', [$this, 'ajax_filter_hospitals']);
        add_action('wp_ajax_nopriv_caretochina_filter_hospitals', [$this, 'ajax_filter_hospitals']);

        // Register Elementor Widgets
        add_action('elementor/widgets/register', [$this, 'register_elementor_widgets']);
        add_action('elementor/widgets/widgets_registered', [$this, 'register_elementor_widgets']);

        // Enqueue Assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function clean_legacy_hospital_contact_meta() {
        if (!get_option('caretochina_hospital_contact_meta_cleaned_v2')) {
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_hospital_address', '_hospital_phone_main', '_hospital_phone_appointment', '_hospital_phone_dept', '_hospital_phone_emergency', '_hospital_website')");
            update_option('caretochina_hospital_contact_meta_cleaned_v2', 1);
        }
    }

    
    public function load_textdomain() {
        load_plugin_textdomain('caretochina-hospitals', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function register_polylang_strings() {
        if (function_exists('pll_register_string')) {
            pll_register_string('Hospital Type', 'Hospital Type', 'CareToChina Hospitals');
            pll_register_string('Location Badge', 'Location Badge', 'CareToChina Hospitals');
            pll_register_string('Accreditation Badge', 'Accreditation Badge', 'CareToChina Hospitals');
            pll_register_string('View Profile', 'View Profile', 'CareToChina Hospitals');
            pll_register_string('No hospitals matching your search criteria.', 'No hospitals matching your search criteria.', 'CareToChina Hospitals');
        }
    }

    public function register_cpt_and_taxonomies() {
        $labels = [
            'name'               => __('Hospitals', 'caretochina-hospitals'),
            'singular_name'      => __('Hospital', 'caretochina-hospitals'),
            'menu_name'          => __('Hospitals', 'caretochina-hospitals'),
            'add_new'            => __('Add Hospital', 'caretochina-hospitals'),
            'add_new_item'       => __('Add New Hospital', 'caretochina-hospitals'),
            'edit_item'          => __('Edit Hospital', 'caretochina-hospitals'),
            'new_item'           => __('New Hospital', 'caretochina-hospitals'),
            'view_item'          => __('View Hospital', 'caretochina-hospitals'),
            'search_items'       => __('Search Hospitals', 'caretochina-hospitals'),
            'not_found'          => __('No hospitals found', 'caretochina-hospitals'),
            'not_found_in_trash' => __('No hospitals found in Trash', 'caretochina-hospitals'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'hospital', 'with_front' => false],
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 5,
            'menu_icon'          => 'dashicons-building',
            'supports'           => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'elementor'],
            'show_in_rest'       => true,
        ];

        register_post_type('hospital', $args);

        register_taxonomy('hospital_city', ['hospital'], [
            'hierarchical'      => true,
            'labels'            => [
                'name'          => __('Cities / Locations', 'caretochina-hospitals'),
                'singular_name' => __('City', 'caretochina-hospitals'),
                'menu_name'     => __('Cities (Categories)', 'caretochina-hospitals'),
            ],
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'hospital-city'],
            'show_in_rest'      => true,
        ]);

        register_taxonomy('hospital_specialty', ['hospital'], [
            'hierarchical'      => true,
            'labels'            => [
                'name'          => __('Specialities', 'caretochina-hospitals'),
                'singular_name' => __('Specialty', 'caretochina-hospitals'),
                'menu_name'     => __('Specialities', 'caretochina-hospitals'),
            ],
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'hospital-specialty'],
            'show_in_rest'      => true,
        ]);

        register_taxonomy('hospital_department', ['hospital'], [
            'hierarchical'      => false,
            'labels'            => [
                'name'          => __('Departments', 'caretochina-hospitals'),
                'singular_name' => __('Department', 'caretochina-hospitals'),
                'menu_name'     => __('Departments (Tags)', 'caretochina-hospitals'),
            ],
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'hospital-department'],
            'show_in_rest'      => true,
        ]);
    }

    public function add_hospital_metaboxes() {
        add_meta_box(
            'hospital_details_mb',
            __('Hospital Information & Contact Details', 'caretochina-hospitals'),
            [$this, 'render_hospital_metabox'],
            'hospital',
            'normal',
            'high'
        );
    }

    public function render_hospital_metabox($post) {
        wp_nonce_field('save_hospital_meta', 'hospital_meta_nonce');

        $type               = get_post_meta($post->ID, '_hospital_type', true);
        $location           = get_post_meta($post->ID, '_hospital_location', true);
        $rating             = get_post_meta($post->ID, '_hospital_rating', true);
        $certification      = get_post_meta($post->ID, '_hospital_certification', true);
        $quote_url          = get_post_meta($post->ID, '_hospital_quote_url', true);
        ?>
        <style>
            .ctc-mb-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 10px; }
            .ctc-mb-field { display: flex; flex-direction: column; gap: 4px; }
            .ctc-mb-field.full { grid-column: span 2; }
            .ctc-mb-field label { font-weight: 600; font-size: 13px; color: #1e293b; }
            .ctc-mb-field input { padding: 8px 12px; border-radius: 6px; border: 1px solid #cbd5e1; width: 100%; }
        </style>
        <div class="ctc-mb-grid">
            <div class="ctc-mb-field">
                <label><?php _e('Hospital Type', 'caretochina-hospitals'); ?></label>
                <input type="text" name="hospital_type" value="<?php echo esc_attr($type); ?>" placeholder="e.g. JCI Accredited Multi-Specialty Hospital Center">
            </div>
            <div class="ctc-mb-field">
                <label><?php _e('Location Badge', 'caretochina-hospitals'); ?></label>
                <input type="text" name="hospital_location" value="<?php echo esc_attr($location); ?>" placeholder="e.g. Shanghai, China">
            </div>
            <div class="ctc-mb-field">
                <label><?php _e('Rating & Reviews', 'caretochina-hospitals'); ?></label>
                <input type="text" name="hospital_rating" value="<?php echo esc_attr($rating ? $rating : '4.9 (1,240 Reviews)'); ?>">
            </div>
            <div class="ctc-mb-field">
                <label><?php _e('Accreditation Badge', 'caretochina-hospitals'); ?></label>
                <input type="text" name="hospital_certification" value="<?php echo esc_attr($certification ? $certification : 'JCI Certified'); ?>">
            </div>
            <div class="ctc-mb-field full">
                <label><?php _e('Free Quote Button Link', 'caretochina-hospitals'); ?></label>
                <input type="text" name="hospital_quote_url" value="<?php echo esc_attr($quote_url ? $quote_url : '#booking'); ?>">
            </div>
        </div>
        <?php
    }

    public function save_hospital_metaboxes($post_id) {
        if (!isset($_POST['hospital_meta_nonce']) || !wp_verify_nonce($_POST['hospital_meta_nonce'], 'save_hospital_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $fields = [
            'hospital_type'          => '_hospital_type',
            'hospital_location'      => '_hospital_location',
            'hospital_rating'        => '_hospital_rating',
            'hospital_certification' => '_hospital_certification',
            'hospital_quote_url'     => '_hospital_quote_url',
        ];

        foreach ($fields as $input_key => $meta_key) {
            if (isset($_POST[$input_key])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$input_key]));
            }
        }

        // Clean up legacy direct contact fields from database for this hospital
        delete_post_meta($post_id, '_hospital_address');
        delete_post_meta($post_id, '_hospital_phone_main');
        delete_post_meta($post_id, '_hospital_phone_appointment');
        delete_post_meta($post_id, '_hospital_phone_dept');
        delete_post_meta($post_id, '_hospital_phone_emergency');
        delete_post_meta($post_id, '_hospital_website');
    }

    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        
        // Always enqueue Swiper JS and CSS
        if (defined('ELEMENTOR_ASSETS_URL')) {
            wp_enqueue_script('swiper', ELEMENTOR_ASSETS_URL . 'lib/swiper/swiper.min.js', ['jquery'], '5.3.6', false);
            wp_enqueue_style('swiper', ELEMENTOR_ASSETS_URL . 'lib/swiper/css/swiper.min.css', [], '5.3.6');
        } else {
            wp_enqueue_script('swiper', 'https://cdn.jsdelivr.net/npm/swiper@8/swiper-bundle.min.js', ['jquery'], '8.0.0', false);
            wp_enqueue_style('swiper', 'https://cdn.jsdelivr.net/npm/swiper@8/swiper-bundle.min.css', [], '8.0.0');
        }
    }

    public function hospital_single_template($template) {
        if (is_singular('hospital')) {
            $custom_single = __DIR__ . '/templates/single-hospital.php';
            if (file_exists($custom_single)) {
                return $custom_single;
            }
        }
        return $template;
    }

    public function ajax_filter_hospitals() {
        $city = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : 'all';
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $paged = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $posts_per_page = isset($_POST['posts_per_page']) ? intval($_POST['posts_per_page']) : 6;

        if ($posts_per_page < 1) $posts_per_page = 6;

                $lang = isset($_POST['lang']) ? sanitize_text_field($_POST['lang']) : (function_exists('pll_current_language') ? pll_current_language() : '');

        $args = [
            'post_type'      => 'hospital',
            'post_status'    => 'publish',
            'posts_per_page' => $posts_per_page,
            'paged'          => $paged,
            's'              => $search,
        ];

        if (!empty($lang) && function_exists('pll_current_language')) {
            $args['lang'] = $lang;
        }

        if ($city !== 'all' && !empty($city)) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'hospital_city',
                    'field'    => 'slug',
                    'terms'    => $city,
                ]
            ];
        }

        $query = new \WP_Query($args);

        ob_start();
        if ($query->have_posts()) :
            while ($query->have_posts()) : $query->the_post();
                self::render_hospital_card(get_the_ID());
            endwhile;
            wp_reset_postdata();
        else :
            echo '<div class="ctc-no-hospitals" style="grid-column: 1 / -1; text-align: center; padding: 50px 20px; color: #64748b; font-size: 1.1rem; width: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px;">';
            echo '<i class="fas fa-hospital-symbol" style="font-size: 2.5rem; color: #cbd5e1; margin-bottom: 8px;"></i>';
            echo '<span>' . __('No hospitals matching your search criteria.', 'caretochina-hospitals') . '</span>';
            echo '</div>';
        endif;
        $grid_html = ob_get_clean();

        // Render Single Pagination Block Separately!
        ob_start();
        if ($query->max_num_pages > 1) :
            for ($i = 1; $i <= $query->max_num_pages; $i++) {
                $active_cls = ($i === $paged) ? 'active' : '';
                echo '<button type="button" class="ctc-hosp-page-btn ' . esc_attr($active_cls) . '" data-page="' . $i . '">' . $i . '</button>';
            }
        endif;
        $pagination_html = ob_get_clean();

        wp_send_json_success([
            'html'            => $grid_html,
            'pagination_html' => $pagination_html,
            'count'           => $query->found_posts,
            'max_pages'       => $query->max_num_pages
        ]);
    }

    public static function render_hospital_card($post_id) {
        $post = get_post($post_id);
        if (!$post) return;

        $permalink = get_permalink($post_id);
        $title = get_the_title($post_id);

        $location      = get_post_meta($post_id, '_hospital_location', true);
        $rating        = get_post_meta($post_id, '_hospital_rating', true);
        $certification = get_post_meta($post_id, '_hospital_certification', true);

        if (!$location) $location = 'Shanghai, China';
        if (!$rating) $rating = '4.9 (1,240 Reviews)';
        if (!$certification) $certification = 'JCI Certified';

        // Truncate Accreditation Badge if > 20 characters
        $full_cert = $certification;
        $display_cert = $certification;
        if (mb_strlen($certification) > 20) {
            $display_cert = mb_substr($certification, 0, 20) . '...';
        }

        $specialties = get_the_terms($post_id, 'hospital_specialty');
        $spec_names = [];
        if (!empty($specialties) && !is_wp_error($specialties)) {
            foreach ($specialties as $s) {
                $spec_names[] = $s->name;
            }
        }
        $spec_string = !empty($spec_names) ? implode(' • ', array_slice($spec_names, 0, 4)) : 'Cardiology • Oncology • Neurology • Surgery';
        ?>
        <!-- SEO Friendly <article> Tag -->
        <article class="cy-bg-muted-child-dark ctc-hospital-card post-<?php echo esc_attr($post_id); ?>">
            
            <!-- Image & Location Badge -->
            <div class="ctc-hosp-img-box">
                <a href="<?php echo esc_url($permalink); ?>" class="ctc-hosp-img-link">
                    <?php if (has_post_thumbnail($post_id)) : ?>
                        <?php echo get_the_post_thumbnail($post_id, 'medium_large', ['class' => 'ctc-hosp-img', 'alt' => esc_attr($title)]); ?>
                    <?php else : ?>
                        <img src="https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?auto=format&fit=crop&w=600&q=80" class="ctc-hosp-img" alt="<?php echo esc_attr($title); ?>">
                    <?php endif; ?>
                </a>

                <!-- Location Badge OVERLAY -->
                <a href="<?php echo esc_url($permalink); ?>" class="ctc-hosp-badge-loc">
                    <i class="fas fa-map-marker-alt"></i> <?php echo esc_html($location); ?>
                </a>
            </div>

            <!-- Card Body Content -->
            <div class="ctc-hosp-body">
                
                <!-- Rating -->
                <div class="ctc-hosp-rating">
                    <i class="fa fa-star"></i> <?php echo esc_html($rating); ?>
                </div>

                <!-- SEO Friendly <h3> Title Tag -->
                <h3 class="cy-heading ctc-hosp-title">
                    <a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($title); ?></a>
                </h3>

                <!-- SEO Friendly <p> Specialties Tag -->
                <p class="cy-paragraph ctc-hosp-specs">
                    <?php echo esc_html($spec_string); ?>
                </p>

                <!-- Footer Row -->
                <div class="ctc-hosp-footer">
                    <span class="ctc-hosp-jci" title="<?php echo esc_attr($full_cert); ?>" data-tooltip="<?php echo esc_attr($full_cert); ?>">
                        <i class="fas fa-certificate"></i> <?php echo esc_html($display_cert); ?>
                    </span>
                    <a href="<?php echo esc_url($permalink); ?>" class="cy-btn cy-btn-outline ctc-hosp-btn">
                        <?php _e('View Profile', 'caretochina-hospitals'); ?>
                    </a>
                </div>

            </div>
        </article>

        <style>
            .ctc-hospital-card {
                background-color: #ffffff;
                border: 1px solid #0f766e69;
                border-radius: 24px;
                box-shadow: 0px 10px 25px -5px rgba(15, 118, 110, 0.08);
                overflow: hidden;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                width: 100%;
                box-sizing: border-box;
                transition: transform 0.35s ease, border-color 0.35s ease, box-shadow 0.35s ease;
            }
            .ctc-hospital-card:hover {
                transform: translateY(-6px);
                border-color: #0f766e !important;
                box-shadow: 0px 16px 32px -8px rgba(15, 118, 110, 0.18) !important;
            }
            .ctc-hosp-img-box {
                position: relative;
                width: 100%;
                height: 220px;
                overflow: hidden;
            }
            .ctc-hosp-img-link {
                display: block;
                width: 100%;
                height: 100%;
            }
            .ctc-hosp-img {
                width: 100%;
                height: 220px;
                object-fit: cover;
                border-radius: 24px 24px 0 0;
                display: block;
                transition: transform 0.4s ease;
            }
            .ctc-hospital-card:hover .ctc-hosp-img {
                transform: scale(1.05);
            }

            .ctc-hosp-badge-loc {
                position: absolute !important;
                bottom: 15px !important;
                left: 15px !important;
                display: inline-flex !important;
                width: auto !important;
                max-width: max-content !important;
                background-color: #0f172abf !important;
                color: #ffffff !important;
                font-family: 'Inter', sans-serif !important;
                font-size: 0.75rem !important;
                font-weight: 500 !important;
                padding: 8px 14px !important;
                border-radius: 48px !important;
                text-decoration: none !important;
                backdrop-filter: blur(4px);
                align-items: center !important;
                gap: 6px !important;
                z-index: 2 !important;
                white-space: nowrap !important;
            }
            .ctc-hosp-badge-loc i {
                color: #ffffff !important;
            }

            .ctc-hosp-body {
                padding: 15px 25px 28px 25px;
                display: flex;
                flex-direction: column;
                flex-grow: 1;
            }
            .ctc-hosp-rating {
                color: #f59e0b !important;
                font-family: 'Inter', sans-serif;
                font-weight: 700;
                font-size: 16px;
                margin-top: 10px;
                margin-bottom: 6px;
                display: flex;
                align-items: center;
                gap: 6px;
            }
            .ctc-hosp-rating i {
                color: #f59e0b !important;
                font-size: 16px;
            }
            .ctc-hosp-title {
                font-family: 'Manrope', sans-serif;
                font-weight: 700;
                font-size: 1.2rem;
                line-height: 1.4;
                margin: 4px 0 8px 0;
            }
            .ctc-hosp-title a {
                color: #0f172a;
                text-decoration: none;
                transition: color 0.2s ease;
            }
            .ctc-hosp-title a:hover {
                color: #0f766e;
            }
            .ctc-hosp-specs {
                font-family: 'Inter', sans-serif;
                color: #64748b !important;
                font-size: 13.5px;
                margin: 10px 0 18px 0;
                line-height: 1.5;
            }
            .ctc-hosp-footer {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-top: auto;
                width: 100%;
                padding-top: 10px;
            }

            /* Tooltip Style for Accreditation Badge */
            .ctc-hosp-jci {
                color: #10b981 !important;
                font-family: 'Inter', sans-serif;
                font-weight: 600;
                font-size: 0.8rem;
                display: inline-flex;
                align-items: center;
                gap: 5px;
                position: relative;
                cursor: pointer;
            }
            .ctc-hosp-jci i {
                color: #10b981 !important;
            }
            .ctc-hosp-jci:hover::after {
                content: attr(data-tooltip);
                position: absolute;
                bottom: 125%;
                left: 50%;
                transform: translateX(-50%);
                background: #0f172a;
                color: #ffffff;
                padding: 6px 12px;
                border-radius: 6px;
                font-size: 0.75rem;
                white-space: nowrap;
                z-index: 100;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                pointer-events: none;
            }

            .ctc-hosp-btn {
                font-family: 'Manrope', sans-serif;
                font-weight: 600;
                font-size: 0.85rem;
                color: #0c0707 !important;
                background-color: #c7f8cef2 !important;
                border: 1.5px solid transparent;
                border-radius: 80px;
                padding: 10px 18px;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                transition: all 0.3s ease;
                box-sizing: border-box;
            }
            .ctc-hosp-btn:hover {
                background-color: #0f766e !important;
                color: #ffffff !important;
                border-color: #0f766e !important;
                box-shadow: 0 4px 12px rgba(15, 118, 110, 0.25);
            }

            /* Override theme reset.css #c36 red focus/hover color */
            .ctc-hosp-btn:focus, .ctc-hosp-btn:focus-visible, .ctc-hosp-btn:active {
                background-color: #c7f8cef2 !important;
                color: #0c0707 !important;
                border-color: transparent !important;
                outline: none !important;
                box-shadow: none !important;
            }

            /* MOBILE RESPONSIVE CUSTOMIZATIONS: Hide Accreditation Badge & Make Button Full Width */
            @media (max-width: 640px) {
                .ctc-hosp-jci {
                    display: none !important;
                }
                .ctc-hosp-footer {
                    justify-content: center !important;
                }
                .ctc-hosp-btn {
                    width: 100% !important;
                    text-align: center !important;
                    justify-content: center !important;
                }
            }

            /* Dark Mode Support */
            html.dark-theme .ctc-hospital-card, body.dark-theme .ctc-hospital-card {
                background-color: #1c2541 !important;
                border-color: #2d3748 !important;
            }
            html.dark-theme .ctc-hosp-title a, body.dark-theme .ctc-hosp-title a {
                color: #f8fafc !important;
            }
            html.dark-theme .ctc-hosp-title a:hover, body.dark-theme .ctc-hosp-title a:hover {
                color: #14b8a6 !important;
            }
            html.dark-theme .ctc-hosp-specs, body.dark-theme .ctc-hosp-specs {
                color: #94a3b8 !important;
            }
            html.dark-theme .ctc-hosp-btn, body.dark-theme .ctc-hosp-btn {
                background-color: #2d3748 !important;
                color: #f8fafc !important;
                border-color: #475569 !important;
            }
            html.dark-theme .ctc-hosp-btn:hover, body.dark-theme .ctc-hosp-btn:hover {
                background-color: #14b8a6 !important;
                color: #0f172a !important;
                border-color: #14b8a6 !important;
            }
        </style>
        <?php
    }

    public function register_elementor_widgets($widgets_manager) {
        require_once __DIR__ . '/widgets/class-caretochina-hospitals-grid-widget.php';
        require_once __DIR__ . '/widgets/class-caretochina-single-hospital-widget.php';
        require_once __DIR__ . '/widgets/class-caretochina-hospitals-slider-widget.php';

        if (class_exists('CareToChina_Hospitals_Grid_Widget')) {
            $widgets_manager->register(new \CareToChina_Hospitals_Grid_Widget());
        }
        if (class_exists('CareToChina_Single_Hospital_Widget')) {
            $widgets_manager->register(new \CareToChina_Single_Hospital_Widget());
        }
        if (class_exists('CareToChina_Hospitals_Slider_Widget')) {
            $widgets_manager->register(new \CareToChina_Hospitals_Slider_Widget());
        }
    }

    public function restrict_hospital_publishing($new_status, $old_status, $post) {
        if ($post->post_type !== 'hospital') {
            return;
        }
        if ($new_status !== 'publish') {
            return;
        }

        $has_city = !empty(get_the_terms($post->ID, 'hospital_city'));
        $has_specialty = !empty(get_the_terms($post->ID, 'hospital_specialty'));
        $has_department = !empty(get_the_terms($post->ID, 'hospital_department'));
        $has_thumbnail = has_post_thumbnail($post->ID);

        if (!$has_city || !$has_specialty || !$has_department || !$has_thumbnail) {
            remove_action('transition_post_status', [$this, 'restrict_hospital_publishing'], 10);
            wp_update_post([
                'ID' => $post->ID,
                'post_status' => 'draft',
            ]);
            add_action('transition_post_status', [$this, 'restrict_hospital_publishing'], 10, 3);

            set_transient('ctc_hospital_publish_error_' . $post->ID, [
                'city' => $has_city,
                'specialty' => $has_specialty,
                'department' => $has_department,
                'thumbnail' => $has_thumbnail,
            ], 45);
        }
    }

    public function display_hospital_publish_notices() {
        global $post;
        if (!$post || $post->post_type !== 'hospital') {
            return;
        }

        $errors = get_transient('ctc_hospital_publish_error_' . $post->ID);
        if ($errors) {
            delete_transient('ctc_hospital_publish_error_' . $post->ID);
            $missing = [];
            if (!$errors['city']) {
                $missing[] = __('Cities / Locations', 'caretochina-hospitals');
            }
            if (!$errors['specialty']) {
                $missing[] = __('Specialities', 'caretochina-hospitals');
            }
            if (!$errors['department']) {
                $missing[] = __('Departments (Tags)', 'caretochina-hospitals');
            }
            if (!$errors['thumbnail']) {
                $missing[] = __('Featured Image', 'caretochina-hospitals');
            }

            echo '<div class="notice notice-error is-dismissible">';
            echo '<p><strong>' . __('Cannot publish Hospital:', 'caretochina-hospitals') . '</strong> ' . sprintf(__('The post status has been reverted to draft because the following required elements are missing: %s.', 'caretochina-hospitals'), implode(', ', $missing)) . '</p>';
            echo '</div>';
        }
    }
}

CareToChina_Hospitals_Plugin::instance();

}
