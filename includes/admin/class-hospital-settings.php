<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class CareToChina_Hospital_Settings
 * Handles admin configuration for Hospital Concierge, Social & Direct Booking Channels,
 * highlights, and extensible custom communication channels.
 */
class CareToChina_Hospital_Settings {

    private static $instance = null;
    public const OPTION_KEY = 'caretochina_hospital_settings';

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this, 'handle_save_settings']);
        add_action('admin_notices', [$this, 'display_admin_notices']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function enqueue_admin_assets($hook) {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (($screen && $screen->post_type === 'hospital') || (is_string($hook) && strpos($hook, 'caretochina-hospital-settings') !== false)) {
            if (!wp_style_is('font-awesome', 'enqueued')) {
                wp_enqueue_style('font-awesome', CARETOCHINA_MEDICAL_URL . 'assets/vendor/font-awesome/css/all.min.css', [], '6.4.0');
            }
            if (function_exists('wp_enqueue_media')) {
                wp_enqueue_media();
            }
        }
    }

    public function register_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=hospital',
            __('Hospital Settings & Channels', 'caretochina-medical'),
            __('Settings & Channels', 'caretochina-medical'),
            'manage_options',
            'caretochina-hospital-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Get default settings merged with saved database options
     */
    public static function get_settings() {
        $defaults = [
            'concierge_title'        => __('CareToChina Concierge', 'caretochina-medical'),
            'concierge_subtitle'     => __('Direct Hospital Booking & Coordination Support', 'caretochina-medical'),
            'concierge_badge'        => __('24/7 Dedicated Support', 'caretochina-medical'),
            'services'               => [
                [
                    'label' => __('CARE COORDINATION:', 'caretochina-medical'),
                    'value' => __('24/7 Dedicated Support', 'caretochina-medical'),
                    'icon'  => 'fas fa-headset'
                ],
                [
                    'label' => __('MEDICAL TRANSLATION:', 'caretochina-medical'),
                    'value' => __('Full Concierge In-Hospital', 'caretochina-medical'),
                    'icon'  => 'fas fa-language'
                ],
                [
                    'label' => __('TRAVEL & VISA HELP:', 'caretochina-medical'),
                    'value' => __('Complete Medical Visa Aid', 'caretochina-medical'),
                    'icon'  => 'fas fa-passport'
                ]
            ],
            // Direct Contact & Booking Channels
            'whatsapp_number'        => '',
            'whatsapp_label'         => __('Chat & Confirm on WhatsApp', 'caretochina-medical'),
            'whatsapp_message'       => __('Hello CareToChina Concierge, I want to inquire and confirm my booking at {hospital_name}.', 'caretochina-medical'),
            'wechat_id'              => '',
            'wechat_label'           => __('Chat & Confirm on WeChat', 'caretochina-medical'),
            'wechat_qr'              => '',
            'wechat_message'         => __('Scan QR code or search WeChat ID to connect with our China Medical Concierge.', 'caretochina-medical'),
            'phone_number'           => '',
            'phone_label'            => __('Hotline Phone Line', 'caretochina-medical'),
            'email'                  => '',
            'email_label'            => __('Direct Email Concierge', 'caretochina-medical'),
            'facebook_url'           => '',
            'facebook_label'         => __('Facebook Page / Messenger', 'caretochina-medical'),
            'instagram_url'          => '',
            'instagram_label'        => __('Instagram Profile', 'caretochina-medical'),
            'youtube_url'            => '',
            'youtube_label'          => __('YouTube Channel', 'caretochina-medical'),
            'x_url'                  => '',
            'x_label'                => __('X (Twitter)', 'caretochina-medical'),
            'booking_url'            => '#booking',
            'booking_label'          => __('Online Booking Solution', 'caretochina-medical'),
            // Extensible Custom Channels & Social Links
            'custom_socials'         => [],
            'custom_channels'        => [],
            // Display Settings
            'show_concierge_card'    => 'yes',
            'show_social_bar'        => 'yes',
            'show_booking_button'    => 'yes'
        ];

        $saved = get_option(self::OPTION_KEY, []);
        if (!is_array($saved)) {
            $saved = [];
        }

        return wp_parse_args($saved, $defaults);
    }

    /**
     * Handle saving settings securely with nonce and sanitization
     */
    public function handle_save_settings() {
        if (!isset($_POST['ctc_hospital_settings_nonce'])) {
            return;
        }

        $nonce = isset($_POST['ctc_hospital_settings_nonce']) ? sanitize_text_field(wp_unslash($_POST['ctc_hospital_settings_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'ctc_save_hospital_settings')) {
            wp_die(esc_html__('Security verification failed. Please try again.', 'caretochina-medical'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized user capability.', 'caretochina-medical'));
        }

        $settings = [];

        $settings['concierge_title']     = isset($_POST['concierge_title']) ? sanitize_text_field(wp_unslash($_POST['concierge_title'])) : '';
        $settings['concierge_subtitle']  = isset($_POST['concierge_subtitle']) ? sanitize_text_field(wp_unslash($_POST['concierge_subtitle'])) : '';
        $settings['concierge_badge']     = isset($_POST['concierge_badge']) ? sanitize_text_field(wp_unslash($_POST['concierge_badge'])) : '';

        // Core Channels
        $settings['whatsapp_number']     = isset($_POST['whatsapp_number']) ? sanitize_text_field(wp_unslash($_POST['whatsapp_number'])) : '';
        $settings['whatsapp_label']      = isset($_POST['whatsapp_label']) ? sanitize_text_field(wp_unslash($_POST['whatsapp_label'])) : '';
        $settings['whatsapp_message']    = isset($_POST['whatsapp_message']) ? sanitize_textarea_field(wp_unslash($_POST['whatsapp_message'])) : '';

        $settings['wechat_id']           = isset($_POST['wechat_id']) ? sanitize_text_field(wp_unslash($_POST['wechat_id'])) : '';
        $settings['wechat_label']        = isset($_POST['wechat_label']) ? sanitize_text_field(wp_unslash($_POST['wechat_label'])) : '';
        $settings['wechat_qr']           = isset($_POST['wechat_qr']) ? esc_url_raw(wp_unslash($_POST['wechat_qr'])) : '';
        $settings['wechat_message']      = isset($_POST['wechat_message']) ? sanitize_textarea_field(wp_unslash($_POST['wechat_message'])) : '';

        $settings['phone_number']        = isset($_POST['phone_number']) ? sanitize_text_field(wp_unslash($_POST['phone_number'])) : '';
        $settings['phone_label']         = isset($_POST['phone_label']) ? sanitize_text_field(wp_unslash($_POST['phone_label'])) : '';

        $settings['email']               = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $settings['email_label']         = isset($_POST['email_label']) ? sanitize_text_field(wp_unslash($_POST['email_label'])) : '';

        $settings['facebook_url']        = isset($_POST['facebook_url']) ? esc_url_raw(wp_unslash($_POST['facebook_url'])) : '';
        $settings['facebook_label']      = isset($_POST['facebook_label']) ? sanitize_text_field(wp_unslash($_POST['facebook_label'])) : '';

        $settings['instagram_url']       = isset($_POST['instagram_url']) ? esc_url_raw(wp_unslash($_POST['instagram_url'])) : '';
        $settings['instagram_label']     = isset($_POST['instagram_label']) ? sanitize_text_field(wp_unslash($_POST['instagram_label'])) : '';

        $settings['youtube_url']         = isset($_POST['youtube_url']) ? esc_url_raw(wp_unslash($_POST['youtube_url'])) : '';
        $settings['youtube_label']       = isset($_POST['youtube_label']) ? sanitize_text_field(wp_unslash($_POST['youtube_label'])) : '';

        $settings['x_url']               = isset($_POST['x_url']) ? esc_url_raw(wp_unslash($_POST['x_url'])) : '';
        $settings['x_label']             = isset($_POST['x_label']) ? sanitize_text_field(wp_unslash($_POST['x_label'])) : 'X (Twitter)';

        $settings['booking_url']         = isset($_POST['booking_url']) ? esc_url_raw(wp_unslash($_POST['booking_url'])) : '';
        $settings['booking_label']       = isset($_POST['booking_label']) ? sanitize_text_field(wp_unslash($_POST['booking_label'])) : '';

        $settings['show_concierge_card'] = isset($_POST['show_concierge_card']) ? 'yes' : 'no';
        $settings['show_social_bar']     = isset($_POST['show_social_bar']) ? 'yes' : 'no';
        $settings['show_booking_button'] = isset($_POST['show_booking_button']) ? 'yes' : 'no';

        // Custom Social Links Repeater
        $custom_socials = [];
        if (!empty($_POST['custom_socials']) && is_array($_POST['custom_socials'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $raw_socials = wp_unslash($_POST['custom_socials']);
            foreach ($raw_socials as $soc) {
                if (empty($soc['name']) && empty($soc['url'])) continue;
                $custom_socials[] = [
                    'name' => sanitize_text_field($soc['name'] ?? ''),
                    'icon' => sanitize_text_field($soc['icon'] ?? 'fas fa-link'),
                    'url'  => esc_url_raw($soc['url'] ?? '')
                ];
            }
        }
        $settings['custom_socials'] = $custom_socials;

        // Concierge Highlights Repeater
        $services = [];
        if (!empty($_POST['services']) && is_array($_POST['services'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $raw_services = wp_unslash($_POST['services']);
            foreach ($raw_services as $srv) {
                $label = sanitize_text_field($srv['label'] ?? '');
                $value = sanitize_text_field($srv['value'] ?? '');
                $icon  = sanitize_text_field($srv['icon'] ?? 'fas fa-check-circle');
                if (!empty($label) || !empty($value)) {
                    $services[] = [
                        'label' => $label,
                        'value' => $value,
                        'icon'  => $icon
                    ];
                }
            }
        }
        $settings['services'] = $services;

        // Custom Channels Repeater
        $custom_channels = [];
        if (!empty($_POST['custom_channels']) && is_array($_POST['custom_channels'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $raw_channels = wp_unslash($_POST['custom_channels']);
            foreach ($raw_channels as $ch) {
                $name  = sanitize_text_field($ch['name'] ?? '');
                $url   = esc_url_raw($ch['url'] ?? '');
                $icon  = sanitize_text_field($ch['icon'] ?? 'fas fa-link');
                $type  = sanitize_text_field($ch['type'] ?? 'link');
                $label = sanitize_text_field($ch['label'] ?? '');
                if (!empty($name) && !empty($url)) {
                    $custom_channels[] = [
                        'name'  => $name,
                        'url'   => $url,
                        'icon'  => $icon,
                        'type'  => $type,
                        'label' => $label
                    ];
                }
            }
        }
        $settings['custom_channels'] = $custom_channels;

        update_option(self::OPTION_KEY, $settings);

        // Global Service Notes Save
        if (isset($_POST['caretochina_global_service_notes'])) {
            update_option('caretochina_global_service_notes', sanitize_textarea_field(wp_unslash($_POST['caretochina_global_service_notes'])));
        }

        set_transient('ctc_hospital_settings_saved', true, 30);

        wp_safe_redirect(admin_url('edit.php?post_type=hospital&page=caretochina-hospital-settings'));
        exit;
    }

    public function display_admin_notices() {
        if (get_transient('ctc_hospital_settings_saved')) {
            delete_transient('ctc_hospital_settings_saved');
            ?>
            <div class="notice notice-success is-dismissible" style="border-left-color: #0f766e;">
                <p><strong><?php esc_html_e('Hospital Settings & Channels updated successfully!', 'caretochina-medical'); ?></strong></p>
            </div>
            <?php
        }
    }

    /**
     * Render the Settings Page HTML
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized access.', 'caretochina-medical'));
        }

        $settings = self::get_settings();
        ?>
        <div class="wrap ctc-settings-wrap">
            <h1 style="display:none;"></h1>
            
            <div class="ctc-header-banner">
                <div class="ctc-header-left">
                    <div class="ctc-header-icon">
                        <i class="fas fa-hospital-user"></i>
                    </div>
                    <div>
                        <h2><?php esc_html_e('Hospital Concierge & Booking Channels Settings', 'caretochina-medical'); ?></h2>
                        <p><?php esc_html_e('Configure global concierge information, social links, direct chat channels (WhatsApp, Facebook, Instagram, YouTube), and booking solutions displayed on single hospital pages.', 'caretochina-medical'); ?></p>
                    </div>
                </div>
                <div class="ctc-header-badge">
                    <span class="badge-pill"><i class="fas fa-shield-halved"></i> <?php esc_html_e('Core Medical Settings', 'caretochina-medical'); ?></span>
                </div>
            </div>

            <form method="post" action="" class="ctc-settings-form">
                <?php wp_nonce_field('ctc_save_hospital_settings', 'ctc_hospital_settings_nonce'); ?>

                <!-- SECTION 1: Concierge Card Identity & Header -->
                <div class="ctc-card">
                    <div class="ctc-card-header">
                        <i class="fas fa-user-nurse"></i>
                        <h3><?php esc_html_e('Concierge Header & Identity', 'caretochina-medical'); ?></h3>
                    </div>
                    <div class="ctc-card-body">
                        <div class="ctc-form-row ctc-grid-2">
                            <div class="ctc-field">
                                <label for="concierge_title"><?php esc_html_e('Concierge Card Title', 'caretochina-medical'); ?></label>
                                <input type="text" id="concierge_title" name="concierge_title" value="<?php echo esc_attr($settings['concierge_title']); ?>" class="regular-text" placeholder="CareToChina Concierge" required>
                                <span class="ctc-hint"><?php esc_html_e('Displayed prominently at the top of the sidebar card.', 'caretochina-medical'); ?></span>
                            </div>
                            <div class="ctc-field">
                                <label for="concierge_badge"><?php esc_html_e('Status / Badge Text', 'caretochina-medical'); ?></label>
                                <input type="text" id="concierge_badge" name="concierge_badge" value="<?php echo esc_attr($settings['concierge_badge']); ?>" class="regular-text" placeholder="24/7 Dedicated Support">
                                <span class="ctc-hint"><?php esc_html_e('Optional badge text for quick identification.', 'caretochina-medical'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 2: Concierge Services & Coordination Highlights -->
                <div class="ctc-card">
                    <div class="ctc-card-header">
                        <i class="fas fa-list-check"></i>
                        <h3><?php esc_html_e('Concierge Highlights & Service Points', 'caretochina-medical'); ?></h3>
                    </div>
                    <div class="ctc-card-body">
                        <p class="ctc-section-desc"><?php esc_html_e('These highlight lines appear in the top section of the concierge card (e.g. Care Coordination, Medical Translation, Visa Help). You can edit them or add new items below.', 'caretochina-medical'); ?></p>
                        
                        <div id="ctc-services-container" class="ctc-repeater-list">
                            <?php 
                            if (!empty($settings['services']) && is_array($settings['services'])) :
                                foreach ($settings['services'] as $idx => $srv) :
                            ?>
                                <div class="ctc-repeater-row" data-index="<?php echo esc_attr($idx); ?>">
                                    <div class="ctc-col-icon">
                                        <label><?php esc_html_e('Icon Class', 'caretochina-medical'); ?></label>
                                        <input type="text" name="services[<?php echo esc_attr($idx); ?>][icon]" value="<?php echo esc_attr($srv['icon']); ?>" placeholder="fas fa-headset">
                                    </div>
                                    <div class="ctc-col-label">
                                        <label><?php esc_html_e('Label', 'caretochina-medical'); ?></label>
                                        <input type="text" name="services[<?php echo esc_attr($idx); ?>][label]" value="<?php echo esc_attr($srv['label']); ?>" placeholder="CARE COORDINATION:">
                                    </div>
                                    <div class="ctc-col-value">
                                        <label><?php esc_html_e('Description / Value', 'caretochina-medical'); ?></label>
                                        <input type="text" name="services[<?php echo esc_attr($idx); ?>][value]" value="<?php echo esc_attr($srv['value']); ?>" placeholder="24/7 Dedicated Support">
                                    </div>
                                    <div class="ctc-col-actions">
                                        <button type="button" class="button ctc-remove-row-btn" title="<?php esc_attr_e('Remove item', 'caretochina-medical'); ?>"><i class="fas fa-trash-alt"></i></button>
                                    </div>
                                </div>
                            <?php 
                                endforeach;
                            endif; 
                            ?>
                        </div>

                        <div style="margin-top: 15px;">
                            <button type="button" id="ctc-add-service-btn" class="button button-secondary">
                                <i class="fas fa-plus-circle"></i> <?php esc_html_e('Add New Highlight Point', 'caretochina-medical'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- SECTION 3: Direct Contact & Booking Confirmation Channels -->
                <div class="ctc-card">
                    <div class="ctc-card-header">
                        <i class="fas fa-comments"></i>
                        <h3><?php esc_html_e('Direct Chat & Booking Channels (WhatsApp, Phone, Email, Facebook, Instagram, YouTube)', 'caretochina-medical'); ?></h3>
                    </div>
                    <div class="ctc-card-body">
                        <p class="ctc-section-desc"><?php esc_html_e('Fields with values will automatically appear as interactive chat, call, or booking action buttons on the single hospital page. Empty fields are hidden automatically.', 'caretochina-medical'); ?></p>

                        <!-- WhatsApp Integration -->
                        <div class="ctc-channel-block">
                            <div class="ctc-channel-header">
                                <span class="ctc-channel-pill ctc-whatsapp"><i class="fab fa-whatsapp"></i> <?php esc_html_e('WhatsApp Chat & Booking Confirmation', 'caretochina-medical'); ?></span>
                            </div>
                            <div class="ctc-grid-2">
                                <div class="ctc-field">
                                    <label for="whatsapp_number"><?php esc_html_e('WhatsApp Phone Number (with Country Code)', 'caretochina-medical'); ?></label>
                                    <input type="text" id="whatsapp_number" name="whatsapp_number" value="<?php echo esc_attr($settings['whatsapp_number']); ?>" class="regular-text" placeholder="+8613800000000">
                                    <span class="ctc-hint"><?php esc_html_e('Include country code without spaces or dashes (e.g. +8613812345678 or +8801700000000).', 'caretochina-medical'); ?></span>
                                </div>
                                <div class="ctc-field">
                                    <label for="whatsapp_label"><?php esc_html_e('WhatsApp Button Label', 'caretochina-medical'); ?></label>
                                    <input type="text" id="whatsapp_label" name="whatsapp_label" value="<?php echo esc_attr($settings['whatsapp_label']); ?>" class="regular-text" placeholder="Chat & Confirm on WhatsApp">
                                </div>
                            </div>
                            <div class="ctc-field" style="margin-top: 10px;">
                                <label for="whatsapp_message"><?php esc_html_e('Pre-filled WhatsApp Booking Message Template', 'caretochina-medical'); ?></label>
                                <textarea id="whatsapp_message" name="whatsapp_message" rows="2" class="large-text" placeholder="Hello CareToChina Concierge, I want to inquire and confirm my booking at {hospital_name}."><?php echo esc_textarea($settings['whatsapp_message']); ?></textarea>
                                <span class="ctc-hint"><?php esc_html_e('Use placeholder {hospital_name} to automatically insert the current hospital name.', 'caretochina-medical'); ?></span>
                            </div>
                        </div>

                        <!-- WeChat Integration -->
                        <div class="ctc-channel-block">
                            <div class="ctc-channel-header">
                                <span class="ctc-channel-pill ctc-wechat"><i class="fab fa-weixin"></i> <?php esc_html_e('WeChat Chat & Booking Confirmation', 'caretochina-medical'); ?></span>
                            </div>
                            <div class="ctc-grid-2">
                                <div class="ctc-field">
                                    <label for="wechat_id"><?php esc_html_e('WeChat ID / Official Account ID', 'caretochina-medical'); ?></label>
                                    <input type="text" id="wechat_id" name="wechat_id" value="<?php echo esc_attr($settings['wechat_id']); ?>" class="regular-text" placeholder="CareToChina_Official">
                                    <span class="ctc-hint"><?php esc_html_e('Enter your official WeChat ID, phone number or account handle.', 'caretochina-medical'); ?></span>
                                </div>
                                <div class="ctc-field">
                                    <label for="wechat_label"><?php esc_html_e('WeChat Button Label', 'caretochina-medical'); ?></label>
                                    <input type="text" id="wechat_label" name="wechat_label" value="<?php echo esc_attr($settings['wechat_label']); ?>" class="regular-text" placeholder="Chat & Confirm on WeChat">
                                </div>
                            </div>
                            <div class="ctc-grid-2" style="margin-top: 10px;">
                                <div class="ctc-field">
                                    <label for="wechat_qr"><?php esc_html_e('WeChat QR Code Image URL', 'caretochina-medical'); ?></label>
                                    <div style="display: flex; gap: 8px;">
                                        <input type="text" id="wechat_qr" name="wechat_qr" value="<?php echo esc_attr($settings['wechat_qr']); ?>" class="regular-text" placeholder="https://example.com/wechat-qr.png" style="flex:1;">
                                        <button type="button" class="button ctc-upload-media-btn" data-target="#wechat_qr"><i class="fas fa-image"></i> <?php esc_html_e('Upload QR', 'caretochina-medical'); ?></button>
                                    </div>
                                    <span class="ctc-hint"><?php esc_html_e('Upload your WeChat personal or corporate QR code for patients to scan on desktop or mobile.', 'caretochina-medical'); ?></span>
                                </div>
                                <div class="ctc-field">
                                    <label for="wechat_message"><?php esc_html_e('WeChat Welcome Note / Instructions', 'caretochina-medical'); ?></label>
                                    <textarea id="wechat_message" name="wechat_message" rows="2" class="large-text" placeholder="Scan QR code or search WeChat ID to connect with our China Medical Concierge."><?php echo esc_textarea($settings['wechat_message']); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Phone & Email Line -->
                        <div class="ctc-channel-block">
                            <div class="ctc-grid-2">
                                <div class="ctc-field">
                                    <label for="phone_number"><i class="fas fa-phone-alt"></i> <?php esc_html_e('Direct Hotline Phone Number', 'caretochina-medical'); ?></label>
                                    <input type="text" id="phone_number" name="phone_number" value="<?php echo esc_attr($settings['phone_number']); ?>" class="regular-text" placeholder="+86 21 5555 6666">
                                </div>
                                <div class="ctc-field">
                                    <label for="phone_label"><?php esc_html_e('Phone Label / Subtitle', 'caretochina-medical'); ?></label>
                                    <input type="text" id="phone_label" name="phone_label" value="<?php echo esc_attr($settings['phone_label']); ?>" class="regular-text" placeholder="Hotline Phone Line">
                                </div>
                            </div>
                            <div class="ctc-grid-2" style="margin-top: 14px;">
                                <div class="ctc-field">
                                    <label for="email"><i class="fas fa-envelope"></i> <?php esc_html_e('Concierge Inquiry Email Address', 'caretochina-medical'); ?></label>
                                    <input type="email" id="email" name="email" value="<?php echo esc_attr($settings['email']); ?>" class="regular-text" placeholder="concierge@caretochina.com">
                                </div>
                                <div class="ctc-field">
                                    <label for="email_label"><?php esc_html_e('Email Label / Subtitle', 'caretochina-medical'); ?></label>
                                    <input type="text" id="email_label" name="email_label" value="<?php echo esc_attr($settings['email_label']); ?>" class="regular-text" placeholder="Direct Email Concierge">
                                </div>
                            </div>
                        </div>

                        <!-- Social Channels: Facebook, Instagram, YouTube, X (Twitter) & Dynamic Socials -->
                        <div class="ctc-channel-block">
                            <div class="ctc-channel-header">
                                <span class="ctc-channel-pill ctc-social"><i class="fas fa-share-nodes"></i> <?php esc_html_e('Social & Media Channels', 'caretochina-medical'); ?></span>
                            </div>
                            
                            <!-- Standard Core Social Platforms -->
                            <div class="ctc-grid-2" style="margin-bottom: 16px;">
                                <div class="ctc-field">
                                    <label for="facebook_url"><i class="fab fa-facebook" style="color:#1877F2;"></i> <?php esc_html_e('Facebook Page / Messenger URL', 'caretochina-medical'); ?></label>
                                    <input type="url" id="facebook_url" name="facebook_url" value="<?php echo esc_attr($settings['facebook_url']); ?>" class="regular-text" placeholder="https://facebook.com/caretochina">
                                </div>
                                <div class="ctc-field">
                                    <label for="instagram_url"><i class="fab fa-instagram" style="color:#E4405F;"></i> <?php esc_html_e('Instagram Profile URL', 'caretochina-medical'); ?></label>
                                    <input type="url" id="instagram_url" name="instagram_url" value="<?php echo esc_attr($settings['instagram_url']); ?>" class="regular-text" placeholder="https://instagram.com/caretochina">
                                </div>
                                <div class="ctc-field">
                                    <label for="youtube_url"><i class="fab fa-youtube" style="color:#FF0000;"></i> <?php esc_html_e('YouTube Channel URL', 'caretochina-medical'); ?></label>
                                    <input type="url" id="youtube_url" name="youtube_url" value="<?php echo esc_attr($settings['youtube_url']); ?>" class="regular-text" placeholder="https://youtube.com/@caretochina">
                                </div>
                                <div class="ctc-field">
                                    <label for="x_url"><i class="fab fa-x-twitter" style="color:#0f172a;"></i> <?php esc_html_e('X (Twitter) Profile URL', 'caretochina-medical'); ?></label>
                                    <input type="url" id="x_url" name="x_url" value="<?php echo esc_attr($settings['x_url'] ?? ''); ?>" class="regular-text" placeholder="https://x.com/caretochina">
                                </div>
                            </div>

                            <!-- Extensible Dynamic Social Profiles Repeater -->
                            <div style="border-top: 1px dashed #cbd5e1; padding-top: 14px; margin-top: 8px;">
                                <label style="font-weight: 700; font-size: 13px; color: #334155; margin-bottom: 6px; display: block;">
                                    <i class="fas fa-plus-circle" style="color:#0f766e;"></i> <?php esc_html_e('Add More Social Media & Video Profiles (e.g. TikTok, LinkedIn, Threads, Pinterest, Discord, Reddit)', 'caretochina-medical'); ?>
                                </label>
                                <p style="font-size: 12px; color: #64748b; margin: 0 0 10px 0;"><?php esc_html_e('Add as many additional social profiles as needed. They will be rendered directly in the social channels icon bar on the single hospital page.', 'caretochina-medical'); ?></p>

                                <div id="ctc-custom-socials-container" class="ctc-repeater-list">
                                    <?php 
                                    if (!empty($settings['custom_socials']) && is_array($settings['custom_socials'])) :
                                        foreach ($settings['custom_socials'] as $sidx => $soc) :
                                    ?>
                                        <div class="ctc-repeater-row ctc-custom-social-row" data-index="<?php echo esc_attr($sidx); ?>">
                                            <div class="ctc-col-name">
                                                <label><?php esc_html_e('Platform Name', 'caretochina-medical'); ?></label>
                                                <input type="text" name="custom_socials[<?php echo esc_attr($sidx); ?>][name]" value="<?php echo esc_attr($soc['name'] ?? ''); ?>" placeholder="e.g. TikTok / LinkedIn">
                                            </div>
                                            <div class="ctc-col-icon">
                                                <label><?php esc_html_e('Icon Class', 'caretochina-medical'); ?></label>
                                                <input type="text" name="custom_socials[<?php echo esc_attr($sidx); ?>][icon]" value="<?php echo esc_attr($soc['icon'] ?? 'fab fa-share-alt'); ?>" placeholder="fab fa-tiktok">
                                            </div>
                                            <div class="ctc-col-url">
                                                <label><?php esc_html_e('Profile URL', 'caretochina-medical'); ?></label>
                                                <input type="text" name="custom_socials[<?php echo esc_attr($sidx); ?>][url]" value="<?php echo esc_attr($soc['url'] ?? ''); ?>" placeholder="https://tiktok.com/@...">
                                            </div>
                                            <div class="ctc-col-actions">
                                                <button type="button" class="button ctc-remove-row-btn" title="<?php esc_attr_e('Remove social link', 'caretochina-medical'); ?>"><i class="fas fa-trash-alt"></i></button>
                                            </div>
                                        </div>
                                    <?php 
                                        endforeach;
                                    endif; 
                                    ?>
                                </div>

                                <div style="margin-top: 10px;">
                                    <button type="button" id="ctc-add-social-btn" class="button button-secondary">
                                        <i class="fas fa-plus"></i> <?php esc_html_e('Add Another Social Profile', 'caretochina-medical'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Booking Solution Integration -->
                        <div class="ctc-channel-block">
                            <div class="ctc-channel-header">
                                <span class="ctc-channel-pill ctc-booking"><i class="fas fa-calendar-check"></i> <?php esc_html_e('Booking Solution CTA', 'caretochina-medical'); ?></span>
                            </div>
                            <div class="ctc-grid-2">
                                <div class="ctc-field">
                                    <label for="booking_url"><?php esc_html_e('Booking Link / URL Target', 'caretochina-medical'); ?></label>
                                    <input type="text" id="booking_url" name="booking_url" value="<?php echo esc_attr($settings['booking_url']); ?>" class="regular-text" placeholder="#booking or /booking/">
                                    <span class="ctc-hint"><?php esc_html_e('Can be an anchor (e.g. #booking) or a custom booking page URL.', 'caretochina-medical'); ?></span>
                                </div>
                                <div class="ctc-field">
                                    <label for="booking_label"><?php esc_html_e('Booking Button Label', 'caretochina-medical'); ?></label>
                                    <input type="text" id="booking_label" name="booking_label" value="<?php echo esc_attr($settings['booking_label']); ?>" class="regular-text" placeholder="Online Booking Solution">
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- SECTION 4: Extensible Custom Channels (Unlimited Repeater) -->
                <div class="ctc-card">
                    <div class="ctc-card-header">
                        <i class="fas fa-network-wired"></i>
                        <h3><?php esc_html_e('Extensible Custom Channels (Add Any New Platform or Link)', 'caretochina-medical'); ?></h3>
                    </div>
                    <div class="ctc-card-body">
                        <p class="ctc-section-desc"><?php esc_html_e('Need to add Telegram, WeChat, Line, Viber, or a custom live chat link? Add unlimited custom channels below. Each added channel will automatically render on the single hospital page.', 'caretochina-medical'); ?></p>

                        <div id="ctc-custom-channels-container" class="ctc-repeater-list">
                            <?php 
                            if (!empty($settings['custom_channels']) && is_array($settings['custom_channels'])) :
                                foreach ($settings['custom_channels'] as $idx => $ch) :
                            ?>
                                <div class="ctc-repeater-row ctc-custom-channel-row" data-index="<?php echo esc_attr($idx); ?>">
                                    <div class="ctc-col-name">
                                        <label><?php esc_html_e('Platform Name', 'caretochina-medical'); ?></label>
                                        <input type="text" name="custom_channels[<?php echo esc_attr($idx); ?>][name]" value="<?php echo esc_attr($ch['name']); ?>" placeholder="e.g. Telegram / WeChat">
                                    </div>
                                    <div class="ctc-col-icon">
                                        <label><?php esc_html_e('Icon Class', 'caretochina-medical'); ?></label>
                                        <input type="text" name="custom_channels[<?php echo esc_attr($idx); ?>][icon]" value="<?php echo esc_attr($ch['icon']); ?>" placeholder="fab fa-telegram">
                                    </div>
                                    <div class="ctc-col-url">
                                        <label><?php esc_html_e('Action URL / Link', 'caretochina-medical'); ?></label>
                                        <input type="text" name="custom_channels[<?php echo esc_attr($idx); ?>][url]" value="<?php echo esc_attr($ch['url']); ?>" placeholder="https://t.me/username">
                                    </div>
                                    <div class="ctc-col-type">
                                        <label><?php esc_html_e('Type / Badge', 'caretochina-medical'); ?></label>
                                        <input type="text" name="custom_channels[<?php echo esc_attr($idx); ?>][label]" value="<?php echo esc_attr($ch['label'] ?? ''); ?>" placeholder="e.g. Direct Chat">
                                    </div>
                                    <div class="ctc-col-actions">
                                        <button type="button" class="button ctc-remove-row-btn" title="<?php esc_attr_e('Remove channel', 'caretochina-medical'); ?>"><i class="fas fa-trash-alt"></i></button>
                                    </div>
                                </div>
                            <?php 
                                endforeach;
                            endif; 
                            ?>
                        </div>

                        <div style="margin-top: 15px;">
                            <button type="button" id="ctc-add-custom-channel-btn" class="button button-secondary">
                                <i class="fas fa-plus-circle"></i> <?php esc_html_e('Add Another Custom Channel', 'caretochina-medical'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- SECTION 5: Display Controls & Save -->
                <div class="ctc-card">
                    <div class="ctc-card-header">
                        <i class="fas fa-sliders-h"></i>
                        <h3><?php esc_html_e('Display & Visibility Controls', 'caretochina-medical'); ?></h3>
                    </div>
                    <div class="ctc-card-body">
                        <div class="ctc-toggle-row">
                            <label class="ctc-switch">
                                <input type="checkbox" name="show_concierge_card" value="yes" <?php checked($settings['show_concierge_card'], 'yes'); ?>>
                                <span class="ctc-slider"></span>
                            </label>
                            <span class="ctc-toggle-text"><?php esc_html_e('Show Concierge & Channels Sidebar Box on Single Hospital Page', 'caretochina-medical'); ?></span>
                        </div>
                        <div class="ctc-toggle-row" style="margin-top: 14px;">
                            <label class="ctc-switch">
                                <input type="checkbox" name="show_social_bar" value="yes" <?php checked($settings['show_social_bar'], 'yes'); ?>>
                                <span class="ctc-slider"></span>
                            </label>
                            <span class="ctc-toggle-text"><?php esc_html_e('Show Social Media Buttons (Facebook, Instagram, YouTube) in Concierge Card', 'caretochina-medical'); ?></span>
                        </div>
                        <div class="ctc-toggle-row" style="margin-top: 14px;">
                            <label class="ctc-switch">
                                <input type="checkbox" name="show_booking_button" value="yes" <?php checked($settings['show_booking_button'], 'yes'); ?>>
                                <span class="ctc-slider"></span>
                            </label>
                            <span class="ctc-toggle-text"><?php esc_html_e('Show Direct Booking Solution Action Button in Concierge Card', 'caretochina-medical'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- SECTION 6: Global Concierge Service Notes -->
                <div class="ctc-card">
                    <div class="ctc-card-header">
                        <i class="fas fa-file-shield"></i>
                        <h3><?php esc_html_e('Global Concierge Service Notes & Inclusions', 'caretochina-medical'); ?></h3>
                    </div>
                    <div class="ctc-card-body">
                        <p class="ctc-section-desc"><?php esc_html_e('These standard service notes (smart translator device, international wifi, RMB exchange assistance, accompanying guest rules, legal policy) are rendered across all concierge packages, single hospital comparison grids, and the booking wizard.', 'caretochina-medical'); ?></p>
                        <div class="ctc-field full" style="margin-top:14px;">
                            <label for="caretochina_global_service_notes" style="font-weight:700; display:block; margin-bottom:6px;"><?php esc_html_e('Global Service Notes Text Block', 'caretochina-medical'); ?></label>
                            <textarea id="caretochina_global_service_notes" name="caretochina_global_service_notes" rows="8" class="large-text" style="font-family:monospace; font-size:12.5px; line-height:1.6; border-radius:8px; padding:10px 14px; border:1px solid #CBD5E1;"><?php echo esc_textarea(CareToChina_Packages::get_global_service_notes()); ?></textarea>
                            <span class="ctc-hint" style="display:block; margin-top:4px; font-size:12px; color:#64748B;"><?php esc_html_e('Changes here will automatically update the service notes accordion in the booking wizard and single hospital pages.', 'caretochina-medical'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="ctc-submit-bar">
                    <button type="submit" class="button button-primary button-hero ctc-save-btn">
                        <i class="fas fa-floppy-disk"></i> <?php esc_html_e('Save Hospital Settings & Channels', 'caretochina-medical'); ?>
                    </button>
                </div>

            </form>
        </div>

        <style>
            .ctc-settings-wrap {
                max-width: 1050px;
                margin: 20px 20px 40px 0;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            }
            .ctc-header-banner {
                background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
                color: #ffffff;
                padding: 24px 30px;
                border-radius: 16px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 24px;
                box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.15);
            }
            .ctc-header-left {
                display: flex;
                align-items: center;
                gap: 20px;
            }
            .ctc-header-icon {
                width: 54px;
                height: 54px;
                background: #0f766e;
                color: #ffffff;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 24px;
            }
            .ctc-header-left h2 {
                color: #ffffff;
                margin: 0 0 6px 0;
                font-size: 20px;
                font-weight: 700;
            }
            .ctc-header-left p {
                color: #94a3b8;
                margin: 0;
                font-size: 13px;
                max-width: 650px;
            }
            .badge-pill {
                background: rgba(15, 118, 110, 0.25);
                border: 1px solid #14b8a6;
                color: #5eead4;
                padding: 6px 14px;
                border-radius: 999px;
                font-size: 12px;
                font-weight: 600;
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }
            .ctc-card {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 14px;
                margin-bottom: 20px;
                overflow: hidden;
                box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            }
            .ctc-card-header {
                background: #f8fafc;
                border-bottom: 1px solid #e2e8f0;
                padding: 16px 22px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .ctc-card-header i {
                color: #0f766e;
                font-size: 16px;
            }
            .ctc-card-header h3 {
                margin: 0;
                font-size: 15px;
                font-weight: 700;
                color: #0f172a;
            }
            .ctc-card-body {
                padding: 22px;
            }
            .ctc-section-desc {
                color: #64748b;
                font-size: 13px;
                margin: 0 0 18px 0;
            }
            .ctc-grid-2 {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 18px;
            }
            .ctc-grid-3 {
                display: grid;
                grid-template-columns: 1fr 1fr 1fr;
                gap: 16px;
            }
            .ctc-field {
                display: flex;
                flex-direction: column;
                gap: 6px;
            }
            .ctc-field label {
                font-weight: 600;
                font-size: 13px;
                color: #334155;
            }
            .ctc-field input, .ctc-field textarea {
                width: 100%;
                border-radius: 8px;
                border: 1px solid #cbd5e1;
                padding: 8px 12px;
                font-size: 13px;
                transition: border-color 0.2s ease;
            }
            .ctc-field input:focus, .ctc-field textarea:focus {
                border-color: #0f766e;
                box-shadow: 0 0 0 1px #0f766e;
                outline: none;
            }
            .ctc-hint {
                color: #94a3b8;
                font-size: 11.5px;
                line-height: 1.4;
            }
            .ctc-channel-block {
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 10px;
                padding: 16px;
                margin-bottom: 16px;
            }
            .ctc-channel-header {
                margin-bottom: 12px;
            }
            .ctc-channel-pill {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 4px 12px;
                border-radius: 999px;
                font-size: 12px;
                font-weight: 700;
            }
            .ctc-whatsapp { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
            .ctc-wechat { background: #e6f9ed; color: #07c160; border: 1px solid #a3e9be; }
            .ctc-social { background: #ede9fe; color: #6d28d9; border: 1px solid #c4b5fd; }
            .ctc-booking { background: #ccfbf1; color: #0f766e; border: 1px solid #99f6e4; }
            
            /* Repeater Styling */
            .ctc-repeater-list {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            .ctc-repeater-row {
                display: flex;
                align-items: flex-end;
                gap: 12px;
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                padding: 12px 16px;
                border-radius: 8px;
            }
            .ctc-col-icon { width: 140px; }
            .ctc-col-label { width: 220px; }
            .ctc-col-value { flex: 1; }
            .ctc-col-name { width: 180px; }
            .ctc-col-url { flex: 1; }
            .ctc-col-type { width: 160px; }
            .ctc-col-actions { width: 44px; }
            .ctc-repeater-row label {
                display: block;
                font-size: 11px;
                font-weight: 600;
                color: #64748b;
                margin-bottom: 4px;
                text-transform: uppercase;
            }
            .ctc-repeater-row input {
                width: 100%;
                border-radius: 6px;
                border: 1px solid #cbd5e1;
                padding: 6px 10px;
                font-size: 12.5px;
            }
            .ctc-remove-row-btn {
                background: #fee2e2 !important;
                border-color: #fca5a5 !important;
                color: #dc2626 !important;
                border-radius: 6px !important;
                height: 32px !important;
                padding: 0 10px !important;
            }
            .ctc-remove-row-btn:hover {
                background: #fecaca !important;
                color: #b91c1c !important;
            }

            /* Switches */
            .ctc-toggle-row {
                display: flex;
                align-items: center;
                gap: 14px;
            }
            .ctc-switch {
                position: relative;
                display: inline-block;
                width: 44px;
                height: 24px;
            }
            .ctc-switch input { opacity: 0; width: 0; height: 0; }
            .ctc-slider {
                position: absolute;
                cursor: pointer;
                top: 0; left: 0; right: 0; bottom: 0;
                background-color: #cbd5e1;
                transition: .3s;
                border-radius: 24px;
            }
            .ctc-slider:before {
                position: absolute;
                content: "";
                height: 18px;
                width: 18px;
                left: 3px;
                bottom: 3px;
                background-color: white;
                transition: .3s;
                border-radius: 50%;
            }
            .ctc-switch input:checked + .ctc-slider { background-color: #0f766e; }
            .ctc-switch input:checked + .ctc-slider:before { transform: translateX(20px); }
            .ctc-toggle-text {
                font-size: 13.5px;
                font-weight: 600;
                color: #1e293b;
            }

            /* Submit Bar */
            .ctc-submit-bar {
                margin-top: 24px;
            }
            .ctc-save-btn {
                background: #0f766e !important;
                border-color: #0d645c !important;
                color: #ffffff !important;
                font-weight: 700 !important;
                font-size: 14px !important;
                padding: 10px 28px !important;
                border-radius: 999px !important;
                box-shadow: 0 4px 12px rgba(15, 118, 110, 0.25) !important;
                display: inline-flex !important;
                align-items: center !important;
                gap: 8px !important;
                transition: transform 0.2s ease, background 0.2s ease !important;
            }
            .ctc-save-btn:hover {
                background: #0d645c !important;
                transform: translateY(-2px);
            }
        </style>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Remove Row Handler
            $(document).on('click', '.ctc-remove-row-btn', function() {
                var row = $(this).closest('.ctc-repeater-row');
                var container = row.parent();
                if (container.children('.ctc-repeater-row').length <= 1) {
                    row.find('input').val('');
                } else {
                    row.fadeOut(200, function() { $(this).remove(); });
                }
            });

            // Add Service Highlight Row
            $('#ctc-add-service-btn').on('click', function() {
                var container = $('#ctc-services-container');
                var newIndex = new Date().getTime();
                var html = '<div class="ctc-repeater-row" data-index="' + newIndex + '">' +
                    '<div class="ctc-col-icon"><label><?php echo esc_js(__('Icon Class', 'caretochina-medical')); ?></label><input type="text" name="services[' + newIndex + '][icon]" value="fas fa-check-circle" placeholder="fas fa-headset"></div>' +
                    '<div class="ctc-col-label"><label><?php echo esc_js(__('Label', 'caretochina-medical')); ?></label><input type="text" name="services[' + newIndex + '][label]" value="" placeholder="e.g. VIP SERVICE:"></div>' +
                    '<div class="ctc-col-value"><label><?php echo esc_js(__('Description / Value', 'caretochina-medical')); ?></label><input type="text" name="services[' + newIndex + '][value]" value="" placeholder="e.g. Dedicated Medical Officer"></div>' +
                    '<div class="ctc-col-actions"><button type="button" class="button ctc-remove-row-btn" title="<?php echo esc_js(__('Remove item', 'caretochina-medical')); ?>"><i class="fas fa-trash-alt"></i></button></div>' +
                '</div>';
                container.append(html);
            });

            // Add Custom Channel Row
            $('#ctc-add-custom-channel-btn').on('click', function() {
                var container = $('#ctc-custom-channels-container');
                var newIndex = new Date().getTime();
                var html = '<div class="ctc-repeater-row ctc-custom-channel-row" data-index="' + newIndex + '">' +
                    '<div class="ctc-col-name"><label><?php echo esc_js(__('Platform Name', 'caretochina-medical')); ?></label><input type="text" name="custom_channels[' + newIndex + '][name]" value="" placeholder="e.g. WeChat / Telegram"></div>' +
                    '<div class="ctc-col-icon"><label><?php echo esc_js(__('Icon Class', 'caretochina-medical')); ?></label><input type="text" name="custom_channels[' + newIndex + '][icon]" value="fab fa-telegram" placeholder="fab fa-telegram"></div>' +
                    '<div class="ctc-col-url"><label><?php echo esc_js(__('Action URL / Link', 'caretochina-medical')); ?></label><input type="text" name="custom_channels[' + newIndex + '][url]" value="" placeholder="https://t.me/..."></div>' +
                    '<div class="ctc-col-type"><label><?php echo esc_js(__('Type / Badge', 'caretochina-medical')); ?></label><input type="text" name="custom_channels[' + newIndex + '][label]" value="Direct Chat" placeholder="Direct Chat"></div>' +
                    '<div class="ctc-col-actions"><button type="button" class="button ctc-remove-row-btn" title="<?php echo esc_js(__('Remove channel', 'caretochina-medical')); ?>"><i class="fas fa-trash-alt"></i></button></div>' +
                '</div>';
                container.append(html);
            });

            // Add Custom Social Media Row
            $('#ctc-add-social-btn').on('click', function() {
                var container = $('#ctc-custom-socials-container');
                var newIndex = new Date().getTime();
                var html = '<div class="ctc-repeater-row ctc-custom-social-row" data-index="' + newIndex + '">' +
                    '<div class="ctc-col-name"><label><?php echo esc_js(__('Platform Name', 'caretochina-medical')); ?></label><input type="text" name="custom_socials[' + newIndex + '][name]" value="" placeholder="e.g. TikTok / Threads"></div>' +
                    '<div class="ctc-col-icon"><label><?php echo esc_js(__('Icon Class', 'caretochina-medical')); ?></label><input type="text" name="custom_socials[' + newIndex + '][icon]" value="fab fa-tiktok" placeholder="fab fa-tiktok"></div>' +
                    '<div class="ctc-col-url"><label><?php echo esc_js(__('Profile URL', 'caretochina-medical')); ?></label><input type="text" name="custom_socials[' + newIndex + '][url]" value="" placeholder="https://tiktok.com/@..."></div>' +
                    '<div class="ctc-col-actions"><button type="button" class="button ctc-remove-row-btn" title="<?php echo esc_js(__('Remove social link', 'caretochina-medical')); ?>"><i class="fas fa-trash-alt"></i></button></div>' +
                '</div>';
                container.append(html);
            });

            // WordPress Media Uploader for WeChat QR Image
            $(document).on('click', '.ctc-upload-media-btn', function(e) {
                e.preventDefault();
                var targetSelector = $(this).data('target');
                var customUploader = wp.media({
                    title: '<?php echo esc_js(__('Select or Upload WeChat QR Code Image', 'caretochina-medical')); ?>',
                    button: { text: '<?php echo esc_js(__('Use This Image', 'caretochina-medical')); ?>' },
                    multiple: false
                }).on('select', function() {
                    var attachment = customUploader.state().get('selection').first().toJSON();
                    $(targetSelector).val(attachment.url);
                }).open();
            });
        });
        </script>
        <?php
    }
}
