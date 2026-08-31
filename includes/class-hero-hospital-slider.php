<?php
/**
 * Hero Hospitals Slider Custom Post Type & Shortcode Controller
 *
 * Provides:
 * 1. Dedicated 'hero_hospital_slider' CPT supporting ONLY featured image (thumbnail).
 * 2. Admin settings panel to control Left/Right navigation arrows & Bottom dot navigator visibility.
 * 3. Responsive carousel slider shortcode: [hero_hospital_slider]
 *
 * @package CareToChina_Medical
 */

if (!defined('ABSPATH')) {
    exit;
}

class CareToChina_Hero_Hospital_Slider {

    private static $instance = null;
    public const OPTION_KEY = 'caretochina_hero_slider_settings';
    public const POST_TYPE  = 'hero_hospital_slider';

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Register CPT
        add_action('init', [$this, 'register_cpt']);

        // Auto title for posts without title
        add_filter('wp_insert_post_data', [$this, 'auto_generate_slide_title'], 10, 2);

        // Admin list table custom columns
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'register_admin_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'render_admin_columns'], 10, 2);

        // Admin Settings Page
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this, 'handle_save_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Front-end Shortcode
        add_shortcode('hero_hospital_slider', [$this, 'render_slider_shortcode']);
        add_shortcode('hero_hospitals_slider', [$this, 'render_slider_shortcode']);

        // Register Front-end Assets
        add_action('wp_enqueue_scripts', [$this, 'register_frontend_assets']);
    }

    /**
     * Register 'hero_hospital_slider' Custom Post Type
     * Only supports 'thumbnail' (Featured Image) - No title, editor, description, or taxonomy.
     */
    public function register_cpt() {
        $labels = [
            'name'                  => __('Hero Hospital Sliders', 'caretochina-medical'),
            'singular_name'         => __('Hero Slide', 'caretochina-medical'),
            'menu_name'             => __('Hero Slider', 'caretochina-medical'),
            'all_items'             => __('All Slides', 'caretochina-medical'),
            'add_new'               => __('Add Slide', 'caretochina-medical'),
            'add_new_item'          => __('Add New Slide Image', 'caretochina-medical'),
            'edit_item'             => __('Edit Slide Image', 'caretochina-medical'),
            'new_item'              => __('New Slide Image', 'caretochina-medical'),
            'view_item'             => __('View Slide', 'caretochina-medical'),
            'search_items'          => __('Search Slides', 'caretochina-medical'),
            'not_found'             => __('No slides found. Click "Add Slide" to upload one.', 'caretochina-medical'),
            'not_found_in_trash'    => __('No slides found in Trash', 'caretochina-medical'),
            'featured_image'        => __('Slide Image (Required)', 'caretochina-medical'),
            'set_featured_image'    => __('Set Slide Image', 'caretochina-medical'),
            'remove_featured_image' => __('Remove Slide Image', 'caretochina-medical'),
            'use_featured_image'    => __('Use as Slide Image', 'caretochina-medical'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 21,
            'menu_icon'          => 'dashicons-images-alt2',
            // Strictly only thumbnail (featured image) supported
            'supports'           => ['thumbnail'],
            'show_in_rest'       => true,
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Automatically generate a clean title when saving a slide post
     */
    public function auto_generate_slide_title($data, $postarr) {
        if ($data['post_type'] === self::POST_TYPE) {
            $post_id = !empty($postarr['ID']) ? $postarr['ID'] : 0;
            if (empty($data['post_title']) || $data['post_title'] === __('Auto Draft') || $data['post_title'] === 'Auto Draft') {
                $data['post_title'] = $post_id ? sprintf(__('Hero Slide #%d', 'caretochina-medical'), $post_id) : __('Hero Slide', 'caretochina-medical');
            }
        }
        return $data;
    }

    /**
     * Custom Columns for WP Admin Post List Table
     */
    public function register_admin_columns($columns) {
        $new_columns = [];
        $new_columns['cb']          = $columns['cb'] ?? '<input type="checkbox" />';
        $new_columns['slide_thumb'] = __('Slide Preview', 'caretochina-medical');
        $new_columns['title']       = __('Slide Title / ID', 'caretochina-medical');
        $new_columns['date']        = __('Published Date', 'caretochina-medical');
        return $new_columns;
    }

    /**
     * Render Custom Column Contents
     */
    public function render_admin_columns($column, $post_id) {
        if ($column === 'slide_thumb') {
            if (has_post_thumbnail($post_id)) {
                $img_url = get_the_post_thumbnail_url($post_id, 'medium');
                echo '<div style="display:flex;align-items:center;gap:10px;">';
                echo '<img src="' . esc_url($img_url) . '" alt="Slide" style="width:90px;height:55px;object-fit:cover;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,0.12);border:1px solid #e2e8f0;" />';
                echo '</div>';
            } else {
                echo '<span style="display:inline-block;padding:4px 10px;background:#fee2e2;color:#dc2626;border-radius:4px;font-size:12px;font-weight:600;">' . esc_html__('No Image Set', 'caretochina-medical') . '</span>';
            }
        }
    }

    /**
     * Register Admin Menu Settings Subpage
     */
    public function register_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=' . self::POST_TYPE,
            __('Hero Slider Settings', 'caretochina-medical'),
            __('Slider Settings', 'caretochina-medical'),
            'manage_options',
            'hero-slider-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Enqueue Admin Assets for the settings page & CPT
     */
    public function enqueue_admin_assets($hook) {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (($screen && $screen->post_type === self::POST_TYPE) || (is_string($hook) && strpos($hook, 'hero-slider-settings') !== false)) {
            wp_enqueue_style('font-awesome', CARETOCHINA_MEDICAL_URL . 'assets/vendor/font-awesome/css/all.min.css', [], '6.4.0');
            wp_enqueue_style('caretochina-hero-slider-style', CARETOCHINA_MEDICAL_URL . 'assets/css/hero-slider.css', [], CARETOCHINA_MEDICAL_VERSION);
        }
    }

    /**
     * Default Slider Configuration Settings
     */
    public static function get_default_settings() {
        return [
            'show_arrows'        => 1, // 1 = Show, 0 = Hide
            'show_dots'          => 1, // 1 = Show, 0 = Hide
            'dot_active_color'   => '#ffffff', // Active dot color
            'dot_inactive_color' => 'rgba(255, 255, 255, 0.6)', // Inactive dot color
            'autoplay'           => 1, // 1 = Enabled, 0 = Disabled
            'autoplay_delay'     => 4500, // in milliseconds
            'pause_on_hover'     => 1,
            'loop'               => 1,
            'transition_effect'  => 'slide', // 'slide' or 'fade'
            'speed'              => 600, // transition duration in ms
            'image_fit'          => 'cover', // 'cover', 'contain', 'fill'
            'slider_height'      => '100vh', // e.g. '100vh', '100%', '600px', 'auto'
            'border_radius'      => '16px', // border radius
        ];
    }

    /**
     * Get Merged Settings from DB
     */
    public static function get_settings() {
        $saved = get_option(self::OPTION_KEY, []);
        return wp_parse_args(is_array($saved) ? $saved : [], self::get_default_settings());
    }

    /**
     * Handle Admin Settings Form Submission
     */
    public function handle_save_settings() {
        if (!isset($_POST['caretochina_hero_slider_nonce'])) {
            return;
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['caretochina_hero_slider_nonce'])), 'save_hero_slider_settings')) {
            wp_die(__('Security verification failed. Please try again.', 'caretochina-medical'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to modify these settings.', 'caretochina-medical'));
        }

        $input = isset($_POST['slider_settings']) && is_array($_POST['slider_settings']) ? $_POST['slider_settings'] : [];

        // Determine height from select or custom input
        $slider_height = '100vh';
        if (!empty($input['slider_height_select'])) {
            if ($input['slider_height_select'] === 'custom') {
                $slider_height = !empty($input['slider_height_custom']) ? sanitize_text_field($input['slider_height_custom']) : '100vh';
            } else {
                $slider_height = sanitize_text_field($input['slider_height_select']);
            }
        } elseif (!empty($input['slider_height'])) {
            $slider_height = sanitize_text_field($input['slider_height']);
        }

        $sanitized = [
            'show_arrows'        => !empty($input['show_arrows']) ? 1 : 0,
            'show_dots'          => !empty($input['show_dots']) ? 1 : 0,
            'dot_active_color'   => sanitize_text_field($input['dot_active_color'] ?? '#ffffff'),
            'dot_inactive_color' => sanitize_text_field($input['dot_inactive_color'] ?? 'rgba(255, 255, 255, 0.6)'),
            'autoplay'           => !empty($input['autoplay']) ? 1 : 0,
            'autoplay_delay'     => max(1000, min(20000, intval($input['autoplay_delay'] ?? 4500))),
            'pause_on_hover'     => !empty($input['pause_on_hover']) ? 1 : 0,
            'loop'               => !empty($input['loop']) ? 1 : 0,
            'transition_effect'  => in_array($input['transition_effect'] ?? 'slide', ['slide', 'fade'], true) ? sanitize_text_field($input['transition_effect']) : 'slide',
            'speed'              => max(200, min(3000, intval($input['speed'] ?? 600))),
            'image_fit'          => in_array($input['image_fit'] ?? 'cover', ['cover', 'contain', 'fill'], true) ? sanitize_text_field($input['image_fit']) : 'cover',
            'slider_height'      => $slider_height,
            'border_radius'      => sanitize_text_field($input['border_radius'] ?? '16px'),
        ];

        update_option(self::OPTION_KEY, $sanitized);

        wp_safe_redirect(add_query_arg([
            'post_type' => self::POST_TYPE,
            'page'      => 'hero-slider-settings',
            'updated'   => '1'
        ], admin_url('edit.php')));
        exit;
    }

    /**
     * Render Admin Settings Page
     */
    public function render_settings_page() {
        $settings = self::get_settings();
        $is_updated = isset($_GET['updated']) && $_GET['updated'] === '1';
        ?>
        <div class="wrap ctc-admin-settings-wrap">
            <h1 class="wp-heading-inline" style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
                <i class="dashicons dashicons-images-alt2" style="font-size:30px;width:30px;height:30px;color:#2563eb;"></i>
                <?php esc_html_e('Hero Hospital Slider Settings', 'caretochina-medical'); ?>
            </h1>

            <?php if ($is_updated): ?>
                <div class="notice notice-success is-dismissible" style="margin-bottom:24px;border-left-color:#10b981;">
                    <p><strong><i class="fas fa-check-circle" style="color:#10b981;"></i> <?php esc_html_e('Slider settings updated successfully!', 'caretochina-medical'); ?></strong></p>
                </div>
            <?php endif; ?>

            <div class="ctc-admin-grid">
                <!-- Main Form Column -->
                <div class="ctc-admin-main-card">
                    <form method="POST" action="">
                        <?php wp_nonce_field('save_hero_slider_settings', 'caretochina_hero_slider_nonce'); ?>

                        <!-- Section: Navigator Controls -->
                        <div class="ctc-form-section">
                            <h2 class="ctc-section-title">
                                <i class="fas fa-sliders-h"></i> <?php esc_html_e('Navigation Controls (Admin Hide/Show)', 'caretochina-medical'); ?>
                            </h2>
                            <p class="ctc-section-desc">
                                <?php esc_html_e('Control the visibility of the Left/Right navigation arrows and Bottom pagination dots for the front-end slider.', 'caretochina-medical'); ?>
                            </p>

                            <div class="ctc-toggle-group">
                                <!-- Left / Right Navigation Arrows -->
                                <div class="ctc-toggle-item">
                                    <div class="ctc-toggle-info">
                                        <strong><?php esc_html_e('Left & Right Navigation Arrows', 'caretochina-medical'); ?></strong>
                                        <span class="ctc-desc"><?php esc_html_e('Show or hide the previous and next slide buttons (< & >).', 'caretochina-medical'); ?></span>
                                    </div>
                                    <label class="ctc-switch">
                                        <input type="checkbox" name="slider_settings[show_arrows]" value="1" <?php checked($settings['show_arrows'], 1); ?>>
                                        <span class="ctc-slider"></span>
                                    </label>
                                </div>

                                <!-- Bottom Dot Navigator -->
                                <div class="ctc-toggle-item">
                                    <div class="ctc-toggle-info">
                                        <strong><?php esc_html_e('Bottom Dot Navigator (Pagination)', 'caretochina-medical'); ?></strong>
                                        <span class="ctc-desc"><?php esc_html_e('Show or hide the slide indicator dots at the bottom of the carousel.', 'caretochina-medical'); ?></span>
                                    </div>
                                    <label class="ctc-switch">
                                        <input type="checkbox" name="slider_settings[show_dots]" value="1" <?php checked($settings['show_dots'], 1); ?>>
                                        <span class="ctc-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <!-- Dot Colors Configuration -->
                            <div style="margin-top:20px;padding:16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;">
                                <h3 style="margin:0 0 12px 0;font-size:14px;color:#1e293b;display:flex;align-items:center;gap:6px;">
                                    <i class="fas fa-palette" style="color:#2563eb;"></i> <?php esc_html_e('Dot Navigation Colors', 'caretochina-medical'); ?>
                                </h3>
                                <div class="ctc-field-grid" style="grid-template-columns:1fr 1fr;gap:16px;">
                                    <!-- Active Dot Color -->
                                    <div class="ctc-field-item">
                                        <label for="dot_active_color"><strong><?php esc_html_e('Active Dot Color', 'caretochina-medical'); ?></strong></label>
                                        <div style="display:flex;align-items:center;gap:8px;">
                                            <input type="color" id="dot_active_color_picker" value="<?php echo esc_attr(strpos($settings['dot_active_color'], '#') === 0 && strlen($settings['dot_active_color']) === 7 ? $settings['dot_active_color'] : '#ffffff'); ?>" style="width:42px;height:38px;padding:2px;border-radius:6px;border:1px solid #cbd5e1;cursor:pointer;" oninput="document.getElementById('dot_active_color').value=this.value;">
                                            <input type="text" id="dot_active_color" name="slider_settings[dot_active_color]" value="<?php echo esc_attr($settings['dot_active_color']); ?>" placeholder="#ffffff or rgba(...)" class="regular-text" style="flex:1;height:38px;" oninput="if(this.value.startsWith('#') && this.value.length===7){document.getElementById('dot_active_color_picker').value=this.value;}">
                                        </div>
                                        <small><?php esc_html_e('Active dot / expanded pill color (Default: #ffffff)', 'caretochina-medical'); ?></small>
                                    </div>

                                    <!-- Inactive Dot Color -->
                                    <div class="ctc-field-item">
                                        <label for="dot_inactive_color"><strong><?php esc_html_e('Inactive Dot Color', 'caretochina-medical'); ?></strong></label>
                                        <div style="display:flex;align-items:center;gap:8px;">
                                            <input type="color" id="dot_inactive_color_picker" value="<?php echo esc_attr(strpos($settings['dot_inactive_color'], '#') === 0 && strlen($settings['dot_inactive_color']) === 7 ? $settings['dot_inactive_color'] : '#94a3b8'); ?>" style="width:42px;height:38px;padding:2px;border-radius:6px;border:1px solid #cbd5e1;cursor:pointer;" oninput="document.getElementById('dot_inactive_color').value=this.value;">
                                            <input type="text" id="dot_inactive_color" name="slider_settings[dot_inactive_color]" value="<?php echo esc_attr($settings['dot_inactive_color']); ?>" placeholder="rgba(255,255,255,0.6) or #94a3b8" class="regular-text" style="flex:1;height:38px;" oninput="if(this.value.startsWith('#') && this.value.length===7){document.getElementById('dot_inactive_color_picker').value=this.value;}">
                                        </div>
                                        <small><?php esc_html_e('Inactive dots color (Default: rgba(255, 255, 255, 0.6))', 'caretochina-medical'); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section: Playback & Animation Settings -->
                        <div class="ctc-form-section">
                            <h2 class="ctc-section-title">
                                <i class="fas fa-play-circle"></i> <?php esc_html_e('Carousel Playback & Effects', 'caretochina-medical'); ?>
                            </h2>

                            <div class="ctc-toggle-group">
                                <!-- Autoplay -->
                                <div class="ctc-toggle-item">
                                    <div class="ctc-toggle-info">
                                        <strong><?php esc_html_e('Autoplay Slides', 'caretochina-medical'); ?></strong>
                                        <span class="ctc-desc"><?php esc_html_e('Automatically transition slides without user interaction.', 'caretochina-medical'); ?></span>
                                    </div>
                                    <label class="ctc-switch">
                                        <input type="checkbox" name="slider_settings[autoplay]" value="1" <?php checked($settings['autoplay'], 1); ?>>
                                        <span class="ctc-slider"></span>
                                    </label>
                                </div>

                                <!-- Infinite Loop -->
                                <div class="ctc-toggle-item">
                                    <div class="ctc-toggle-info">
                                        <strong><?php esc_html_e('Continuous Loop', 'caretochina-medical'); ?></strong>
                                        <span class="ctc-desc"><?php esc_html_e('Enable infinite smooth looping of slides.', 'caretochina-medical'); ?></span>
                                    </div>
                                    <label class="ctc-switch">
                                        <input type="checkbox" name="slider_settings[loop]" value="1" <?php checked($settings['loop'], 1); ?>>
                                        <span class="ctc-slider"></span>
                                    </label>
                                </div>

                                <!-- Pause on Hover -->
                                <div class="ctc-toggle-item">
                                    <div class="ctc-toggle-info">
                                        <strong><?php esc_html_e('Pause on Hover', 'caretochina-medical'); ?></strong>
                                        <span class="ctc-desc"><?php esc_html_e('Pause autoplay when user hovers their mouse over the slider.', 'caretochina-medical'); ?></span>
                                    </div>
                                    <label class="ctc-switch">
                                        <input type="checkbox" name="slider_settings[pause_on_hover]" value="1" <?php checked($settings['pause_on_hover'], 1); ?>>
                                        <span class="ctc-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <div class="ctc-field-grid" style="margin-top:18px;">
                                <!-- Autoplay Delay -->
                                <div class="ctc-field-item">
                                    <label for="autoplay_delay"><strong><?php esc_html_e('Autoplay Delay (Milliseconds)', 'caretochina-medical'); ?></strong></label>
                                    <input type="number" id="autoplay_delay" name="slider_settings[autoplay_delay]" value="<?php echo esc_attr($settings['autoplay_delay']); ?>" min="1000" max="20000" step="500" class="regular-text" style="width:100%;">
                                    <small><?php esc_html_e('e.g. 4500ms = 4.5 seconds per slide', 'caretochina-medical'); ?></small>
                                </div>

                                <!-- Transition Effect -->
                                <div class="ctc-field-item">
                                    <label for="transition_effect"><strong><?php esc_html_e('Transition Effect', 'caretochina-medical'); ?></strong></label>
                                    <select id="transition_effect" name="slider_settings[transition_effect]" style="width:100%;height:38px;">
                                        <option value="slide" <?php selected($settings['transition_effect'], 'slide'); ?>><?php esc_html_e('Slide (Horizontal)', 'caretochina-medical'); ?></option>
                                        <option value="fade" <?php selected($settings['transition_effect'], 'fade'); ?>><?php esc_html_e('Fade (Smooth Crossfade)', 'caretochina-medical'); ?></option>
                                    </select>
                                    <small><?php esc_html_e('Choose transition animation style', 'caretochina-medical'); ?></small>
                                </div>

                                <!-- Transition Speed -->
                                <div class="ctc-field-item">
                                    <label for="transition_speed"><strong><?php esc_html_e('Transition Duration (ms)', 'caretochina-medical'); ?></strong></label>
                                    <input type="number" id="transition_speed" name="slider_settings[speed]" value="<?php echo esc_attr($settings['speed']); ?>" min="200" max="3000" step="100" class="regular-text" style="width:100%;">
                                    <small><?php esc_html_e('Animation duration (default: 600ms)', 'caretochina-medical'); ?></small>
                                </div>
                            </div>
                        </div>

                        <!-- Section: Layout & Sizing -->
                        <div class="ctc-form-section">
                            <h2 class="ctc-section-title">
                                <i class="fas fa-expand"></i> <?php esc_html_e('Layout, Fit & Dimensions', 'caretochina-medical'); ?>
                            </h2>

                            <div class="ctc-field-grid">
                                <!-- Image Fit -->
                                <div class="ctc-field-item">
                                    <label for="image_fit"><strong><?php esc_html_e('Image Fit Mode', 'caretochina-medical'); ?></strong></label>
                                    <select id="image_fit" name="slider_settings[image_fit]" style="width:100%;height:38px;">
                                        <option value="cover" <?php selected($settings['image_fit'], 'cover'); ?>><?php esc_html_e('Cover (Fill and Crop seamlessly)', 'caretochina-medical'); ?></option>
                                        <option value="contain" <?php selected($settings['image_fit'], 'contain'); ?>><?php esc_html_e('Contain (Fit full image inside)', 'caretochina-medical'); ?></option>
                                        <option value="fill" <?php selected($settings['image_fit'], 'fill'); ?>><?php esc_html_e('Fill (Stretch)', 'caretochina-medical'); ?></option>
                                    </select>
                                </div>

                                <?php
                                $known_presets = ['100vh', '100%', '85vh', '75vh', '700px', '600px', '500px', '400px', 'auto'];
                                $current_height = $settings['slider_height'] ?? '100vh';
                                $is_custom = !in_array($current_height, $known_presets, true);
                                ?>
                                <!-- Slider Height (Select Dropdown) -->
                                <div class="ctc-field-item">
                                    <label for="slider_height_select"><strong><?php esc_html_e('Slider Height (Select / Preset)', 'caretochina-medical'); ?></strong></label>
                                    <select id="slider_height_select" name="slider_settings[slider_height_select]" style="width:100%;height:38px;" onchange="var customWrap=document.getElementById('slider_height_custom_wrap'); if(this.value==='custom'){ customWrap.style.display='block'; document.getElementById('slider_height_custom').focus(); } else { customWrap.style.display='none'; document.getElementById('slider_height_custom').value=this.value; }">
                                        <option value="100vh" <?php selected(!$is_custom && $current_height === '100vh', true); ?>><?php esc_html_e('100vh (Full Screen Viewport Height)', 'caretochina-medical'); ?></option>
                                        <option value="100%" <?php selected(!$is_custom && $current_height === '100%', true); ?>><?php esc_html_e('100% (Full Parent Container Height)', 'caretochina-medical'); ?></option>
                                        <option value="85vh" <?php selected(!$is_custom && $current_height === '85vh', true); ?>><?php esc_html_e('85vh (Hero Viewport Height)', 'caretochina-medical'); ?></option>
                                        <option value="75vh" <?php selected(!$is_custom && $current_height === '75vh', true); ?>><?php esc_html_e('75vh (Medium Viewport Height)', 'caretochina-medical'); ?></option>
                                        <option value="700px" <?php selected(!$is_custom && $current_height === '700px', true); ?>><?php esc_html_e('700px (Extra Large Height)', 'caretochina-medical'); ?></option>
                                        <option value="600px" <?php selected(!$is_custom && $current_height === '600px', true); ?>><?php esc_html_e('600px (Large Hero Height)', 'caretochina-medical'); ?></option>
                                        <option value="500px" <?php selected(!$is_custom && $current_height === '500px', true); ?>><?php esc_html_e('500px (Standard Medium Height)', 'caretochina-medical'); ?></option>
                                        <option value="400px" <?php selected(!$is_custom && $current_height === '400px', true); ?>><?php esc_html_e('400px (Compact Height)', 'caretochina-medical'); ?></option>
                                        <option value="auto" <?php selected(!$is_custom && $current_height === 'auto', true); ?>><?php esc_html_e('auto (Auto Image Aspect Ratio)', 'caretochina-medical'); ?></option>
                                        <option value="custom" <?php selected($is_custom, true); ?>><?php esc_html_e('Custom Height...', 'caretochina-medical'); ?></option>
                                    </select>
                                    <div id="slider_height_custom_wrap" style="margin-top:8px;<?php echo $is_custom ? '' : 'display:none;'; ?>">
                                        <input type="text" id="slider_height_custom" name="slider_settings[slider_height_custom]" value="<?php echo esc_attr($current_height); ?>" placeholder="e.g. 100vh, 550px, 90vh" class="regular-text" style="width:100%;">
                                        <small><?php esc_html_e('Enter any custom CSS height (e.g. 100vh, 550px, 80vh, 100%).', 'caretochina-medical'); ?></small>
                                    </div>
                                </div>

                                <!-- Border Radius -->
                                <div class="ctc-field-item">
                                    <label for="border_radius"><strong><?php esc_html_e('Container Rounded Corners (Border Radius)', 'caretochina-medical'); ?></strong></label>
                                    <input type="text" id="border_radius" name="slider_settings[border_radius]" value="<?php echo esc_attr($settings['border_radius']); ?>" placeholder="16px" class="regular-text" style="width:100%;">
                                    <small><?php esc_html_e('e.g. 16px, 24px, or 0px for square edges', 'caretochina-medical'); ?></small>
                                </div>
                            </div>
                        </div>

                        <div style="margin-top:28px;padding-top:20px;border-top:1px solid #e2e8f0;">
                            <button type="submit" class="button button-primary button-hero" style="background:#2563eb;border-color:#1d4ed8;box-shadow:0 4px 12px rgba(37,99,235,0.25);">
                                <i class="fas fa-save" style="margin-right:6px;"></i> <?php esc_html_e('Save Slider Settings', 'caretochina-medical'); ?>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Sidebar Guide & Shortcode Card -->
                <div class="ctc-admin-sidebar-card">
                    <div class="ctc-shortcode-card">
                        <h3><i class="fas fa-code" style="color:#2563eb;"></i> <?php esc_html_e('Shortcode Usage', 'caretochina-medical'); ?></h3>
                        <p><?php esc_html_e('Copy and paste this shortcode anywhere in your page, template, or Elementor section to display the carousel:', 'caretochina-medical'); ?></p>
                        
                        <div class="ctc-copy-box">
                            <input type="text" readonly value="[hero_hospital_slider]" id="ctcShortcodeVal">
                            <button type="button" class="button" onclick="navigator.clipboard.writeText(document.getElementById('ctcShortcodeVal').value);this.innerText='Copied!';setTimeout(()=>this.innerText='Copy',2000);">
                                <?php esc_html_e('Copy', 'caretochina-medical'); ?>
                            </button>
                        </div>

                        <hr style="margin:20px 0;border:0;border-top:1px solid #e2e8f0;">

                        <h4><i class="fas fa-info-circle" style="color:#3b82f6;"></i> <?php esc_html_e('How it works:', 'caretochina-medical'); ?></h4>
                        <ol style="margin-left:18px;line-height:1.6;font-size:13px;color:#475569;">
                            <li><?php esc_html_e('Go to "Hero Slider" -> "Add Slide" to upload slide images.', 'caretochina-medical'); ?></li>
                            <li><?php esc_html_e('Only the Featured Image is used — no text or title needed.', 'caretochina-medical'); ?></li>
                            <li><?php esc_html_e('Place the shortcode in any section to display the responsive carousel slider.', 'caretochina-medical'); ?></li>
                            <li><?php esc_html_e('Navigation arrows (< & >) and bottom dots can be toggled on/off here anytime.', 'caretochina-medical'); ?></li>
                        </ol>

                        <div style="margin-top:20px;">
                            <a href="<?php echo esc_url(admin_url('post-new.php?post_type=' . self::POST_TYPE)); ?>" class="button button-secondary" style="width:100%;text-align:center;">
                                <i class="fas fa-plus-circle" style="margin-right:5px;"></i> <?php esc_html_e('Add New Slide Image', 'caretochina-medical'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Register front-end scripts and styles
     */
    public function register_frontend_assets() {
        // Swiper CSS & JS
        if (!wp_style_is('swiper', 'registered')) {
            wp_register_style('swiper', CARETOCHINA_MEDICAL_URL . 'assets/vendor/swiper/css/swiper-bundle.min.css', [], '8.4.5');
        }
        if (!wp_script_is('swiper', 'registered')) {
            wp_register_script('swiper', CARETOCHINA_MEDICAL_URL . 'assets/vendor/swiper/js/swiper-bundle.min.js', [], '8.4.5', true);
        }

        // FontAwesome for Arrow icons
        if (!wp_style_is('font-awesome', 'registered') && !wp_style_is('font-awesome', 'enqueued')) {
            wp_register_style('font-awesome', CARETOCHINA_MEDICAL_URL . 'assets/vendor/font-awesome/css/all.min.css', [], '6.4.0');
        }

        // Hero Slider Custom Style & Script
        wp_register_style(
            'caretochina-hero-slider-style',
            CARETOCHINA_MEDICAL_URL . 'assets/css/hero-slider.css',
            ['swiper', 'font-awesome'],
            CARETOCHINA_MEDICAL_VERSION
        );

        wp_register_script(
            'caretochina-hero-slider-script',
            CARETOCHINA_MEDICAL_URL . 'assets/js/hero-slider.js',
            ['swiper'],
            CARETOCHINA_MEDICAL_VERSION,
            true
        );
    }

    /**
     * Render Slider Shortcode: [hero_hospital_slider]
     */
    public function render_slider_shortcode($atts) {
        $db_settings = self::get_settings();

        // Allow optional shortcode attribute overrides if passed, defaulting to admin settings
        $atts = shortcode_atts([
            'show_arrows'        => $db_settings['show_arrows'],
            'show_dots'          => $db_settings['show_dots'],
            'dot_active_color'   => $db_settings['dot_active_color'],
            'dot_inactive_color' => $db_settings['dot_inactive_color'],
            'autoplay'           => $db_settings['autoplay'],
            'delay'              => $db_settings['autoplay_delay'],
            'loop'               => $db_settings['loop'],
            'effect'             => $db_settings['transition_effect'],
            'speed'              => $db_settings['speed'],
            'height'             => $db_settings['slider_height'],
            'fit'                => $db_settings['image_fit'],
            'radius'             => $db_settings['border_radius'],
            'class'              => '',
            'orderby'            => 'date',
            'order'              => 'DESC',
            'limit'              => -1,
        ], $atts, 'hero_hospital_slider');

        // Enqueue Assets
        wp_enqueue_style('swiper');
        wp_enqueue_style('font-awesome');
        wp_enqueue_style('caretochina-hero-slider-style');
        wp_enqueue_script('swiper');
        wp_enqueue_script('caretochina-hero-slider-script');

        // Query Slide Posts
        $query_args = [
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => intval($atts['limit']),
            'orderby'        => sanitize_text_field($atts['orderby']),
            'order'          => sanitize_text_field($atts['order']),
            'meta_query'     => [
                [
                    'key'     => '_thumbnail_id',
                    'compare' => 'EXISTS',
                ],
            ],
        ];

        $query = new WP_Query($query_args);

        if (!$query->have_posts()) {
            return '<!-- Hero Hospital Slider: No slide images found. Please add slides in Hero Slider -> Add Slide. -->';
        }

        $show_arrows = filter_var($atts['show_arrows'], FILTER_VALIDATE_BOOLEAN);
        $show_dots   = filter_var($atts['show_dots'], FILTER_VALIDATE_BOOLEAN);
        $autoplay    = filter_var($atts['autoplay'], FILTER_VALIDATE_BOOLEAN);
        $loop        = filter_var($atts['loop'], FILTER_VALIDATE_BOOLEAN);

        $slider_id = 'ctc-hero-slider-' . wp_rand(1000, 9999);

        // Build Slider Config JSON for JS initialization
        $slider_config = [
            'autoplay'    => $autoplay ? ['delay' => intval($atts['delay']), 'disableOnInteraction' => false, 'pauseOnMouseEnter' => (bool)$db_settings['pause_on_hover']] : false,
            'loop'        => $loop,
            'effect'      => sanitize_text_field($atts['effect']),
            'speed'       => intval($atts['speed']),
            'show_arrows' => $show_arrows,
            'show_dots'   => $show_dots,
        ];

        $custom_styles = [];
        if (!empty($atts['height']) && $atts['height'] !== 'auto') {
            $custom_styles[] = '--ctc-slider-height: ' . esc_attr($atts['height']) . ';';
        }
        if (!empty($atts['radius'])) {
            $custom_styles[] = '--ctc-slider-radius: ' . esc_attr($atts['radius']) . ';';
        }
        if (!empty($atts['fit'])) {
            $custom_styles[] = '--ctc-slider-fit: ' . esc_attr($atts['fit']) . ';';
        }
        if (!empty($atts['dot_active_color'])) {
            $custom_styles[] = '--ctc-dot-active-color: ' . esc_attr($atts['dot_active_color']) . ';';
        }
        if (!empty($atts['dot_inactive_color'])) {
            $custom_styles[] = '--ctc-dot-inactive-color: ' . esc_attr($atts['dot_inactive_color']) . ';';
        }
        $style_attr = !empty($custom_styles) ? ' style="' . implode(' ', $custom_styles) . '"' : '';

        ob_start();
        ?>
        <div id="<?php echo esc_attr($slider_id); ?>" 
             class="ctc-hero-slider-wrap <?php echo esc_attr($atts['class']); ?>"
             data-slider-config='<?php echo esc_attr(wp_json_encode($slider_config)); ?>'
             <?php echo $style_attr; ?>>
            
            <div class="swiper ctc-hero-slider">
                <div class="swiper-wrapper">
                    <?php
                    while ($query->have_posts()) : $query->the_post();
                        $post_id = get_the_ID();
                        $thumb_id = get_post_thumbnail_id($post_id);
                        $img_src = wp_get_attachment_image_url($thumb_id, 'full');
                        $img_alt = get_post_meta($thumb_id, '_wp_attachment_image_alt', true) ?: get_the_title();
                        $srcset  = wp_get_attachment_image_srcset($thumb_id, 'full');
                        $sizes   = wp_get_attachment_image_sizes($thumb_id, 'full') ?: '100vw';
                        ?>
                        <div class="swiper-slide ctc-hero-slide">
                            <div class="ctc-hero-slide-inner">
                                <img src="<?php echo esc_url($img_src); ?>" 
                                     alt="<?php echo esc_attr($img_alt); ?>" 
                                     <?php if ($srcset): ?>srcset="<?php echo esc_attr($srcset); ?>" sizes="<?php echo esc_attr($sizes); ?>"<?php endif; ?>
                                     class="ctc-hero-slide-img" 
                                     loading="lazy" />
                            </div>
                        </div>
                    <?php endwhile; wp_reset_postdata(); ?>
                </div>

                <?php if ($show_arrows): ?>
                    <!-- Left / Right Navigation Arrows -->
                    <button type="button" class="ctc-slider-nav ctc-slider-prev" aria-label="<?php esc_attr_e('Previous Slide', 'caretochina-medical'); ?>">
                        <i class="fas fa-chevron-left" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="ctc-slider-nav ctc-slider-next" aria-label="<?php esc_attr_e('Next Slide', 'caretochina-medical'); ?>">
                        <i class="fas fa-chevron-right" aria-hidden="true"></i>
                    </button>
                <?php endif; ?>

                <?php if ($show_dots): ?>
                    <!-- Bottom Dot Navigator -->
                    <div class="swiper-pagination ctc-slider-pagination"></div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
