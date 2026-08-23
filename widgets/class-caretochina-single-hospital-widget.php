<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class CareToChina_Single_Hospital_Widget extends Widget_Base {

    public function get_name() {
        return 'caretochina_single_hospital';
    }

    public function get_title() {
        return __('CareToChina Single Hospital Layout', 'caretochina-hospitals');
    }

    public function get_icon() {
        return 'eicon-single-page';
    }

    public function get_categories() {
        return ['general', 'basic'];
    }

    public function get_keywords() {
        return ['single', 'hospital', 'layout', 'overview', 'contact', 'caretochina'];
    }

    protected function register_controls() {
        
        $this->start_controls_section(
            'section_single_hosp_controls',
            [
                'label' => __('Single Hospital Options', 'caretochina-hospitals'),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'show_quote_btn',
            [
                'label'        => __('Show Free Quote Button', 'caretochina-hospitals'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'show_concierge_sidebar',
            [
                'label'        => __('Show CareToChina Concierge Sidebar', 'caretochina-hospitals'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'show_specialties',
            [
                'label'        => __('Show Specialities', 'caretochina-hospitals'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'show_departments',
            [
                'label'        => __('Show Departments', 'caretochina-hospitals'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'show_comments',
            [
                'label'        => __('Enable Comments / Patient Reviews', 'caretochina-hospitals'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        $post = get_post();
        if (!$post || $post->post_type !== 'hospital') {
            $latest = get_posts(['post_type' => 'hospital', 'numberposts' => 1]);
            $post = !empty($latest) ? $latest[0] : null;
        }

        if (!$post) {
            echo '<p class="ctc-no-hospital">' . __('No hospital post found to render.', 'caretochina-hospitals') . '</p>';
            return;
        }

        $post_id = $post->ID;
        $title = get_the_title($post_id);
        $content = $post->post_content;

        $type               = get_post_meta($post_id, '_hospital_type', true);
        $location           = get_post_meta($post_id, '_hospital_location', true);
        $rating             = get_post_meta($post_id, '_hospital_rating', true);
        $certification      = get_post_meta($post_id, '_hospital_certification', true);
        $quote_url          = get_post_meta($post_id, '_hospital_quote_url', true);

        if (!$location) $location = 'Shanghai, China';
        if (!$rating) $rating = '4.9 (1,240 Reviews)';
        if (!$certification) $certification = 'JCI Certified';
        if (!$type) $type = 'JCI Accredited Multi-Specialty Hospital Center';
        if (!$quote_url) $quote_url = '#booking';

        $specialties = get_the_terms($post_id, 'hospital_specialty');
        $departments = get_the_terms($post_id, 'hospital_department');
        $show_sidebar = !empty($settings['show_concierge_sidebar']) ? $settings['show_concierge_sidebar'] : ($settings['show_phone_lines'] ?? 'yes');
        $concierge = CareToChina_Hospitals_Plugin::get_hospital_concierge_data($post_id);
        ?>

        <div class="ctc-single-hosp-wrapper post-<?php echo $post_id; ?>">
            
            <div class="ctc-hosp-hero">
                <div class="ctc-hosp-hero-img-box">
                    <?php if (has_post_thumbnail($post_id)) : ?>
                        <?php echo wp_get_attachment_image(get_post_thumbnail_id($post_id), 'large', false, [
                            'class' => 'ctc-hero-img',
                            'alt' => esc_attr($title),
                            'sizes' => '(max-width: 768px) 100vw, (max-width: 1200px) 70vw, 1200px',
                            'loading' => 'eager'
                        ]); ?>
                    <?php else : ?>
                        <img src="https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?auto=format&fit=crop&w=1200&q=80" class="ctc-hero-img" alt="<?php echo esc_attr($title); ?>" loading="eager">
                    <?php endif; ?>

                    <div class="ctc-hero-overlay">
                        <span class="ctc-hero-cert"><i class="fas fa-certificate"></i> <?php echo esc_html($certification); ?></span>
                        <span class="ctc-hero-type"><i class="fas fa-hospital"></i> <?php echo esc_html($type); ?></span>
                    </div>
                </div>

                <div class="ctc-hero-header-content">
                    <div class="ctc-hero-meta">
                        <span class="ctc-hero-loc"><i class="fas fa-map-marker-alt"></i> <?php echo esc_html($location); ?></span>
                        <span class="ctc-hero-rating"><i class="fa fa-star"></i> <?php echo esc_html($rating); ?></span>
                    </div>
                    <h1 class="cy-heading ctc-hero-title"><?php echo esc_html($title); ?></h1>

                    <div class="ctc-hero-cta-row">
                        <?php if ($settings['show_quote_btn'] === 'yes') : ?>
                            <a href="<?php echo esc_attr($quote_url); ?>" class="ctc-quote-btn" aria-label="<?php esc_attr_e('Request Free Quote and Consultation', 'caretochina-hospitals'); ?>">
                                <i class="fas fa-paper-plane"></i> <?php _e('Request Free Quote & Consultation', 'caretochina-hospitals'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="ctc-hosp-layout-grid">
                
                <div class="ctc-hosp-main-col">
                    <div class="ctc-hosp-card-box">
                        <h2 class="cy-heading ctc-box-title"><i class="fas fa-info-circle"></i> <?php _e('Hospital Overview', 'caretochina-hospitals'); ?></h2>
                        <div class="cy-paragraph ctc-box-body">
                            <?php echo apply_filters('the_content', $content); ?>
                        </div>
                    </div>

                    <?php if ($settings['show_specialties'] === 'yes' && !empty($specialties) && !is_wp_error($specialties)) : ?>
                        <div class="ctc-hosp-card-box">
                            <h2 class="cy-heading ctc-box-title"><i class="fas fa-stethoscope"></i> <?php _e('Specialities & Medical Programs', 'caretochina-hospitals'); ?></h2>
                            <div class="ctc-spec-pills">
                                <?php foreach ($specialties as $spec) : ?>
                                    <span class="ctc-spec-pill"><i class="fas fa-check-circle"></i> <?php echo esc_html($spec->name); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($settings['show_departments'] === 'yes' && !empty($departments) && !is_wp_error($departments)) : ?>
                        <div class="ctc-hosp-card-box">
                            <h2 class="cy-heading ctc-box-title"><i class="fas fa-clinic-medical"></i> <?php _e('Clinical Departments', 'caretochina-hospitals'); ?></h2>
                            <div class="ctc-dept-tags">
                                <?php foreach ($departments as $dept) : ?>
                                    <span class="ctc-dept-tag">#<?php echo esc_html($dept->name); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($settings['show_comments'] === 'yes' && (comments_open($post_id) || get_comments_number($post_id))) : ?>
                        <div class="ctc-hosp-card-box ctc-comments-box">
                            <h2 class="cy-heading ctc-box-title"><i class="fas fa-comments"></i> <?php _e('Patient Reviews & Enquiries', 'caretochina-hospitals'); ?></h2>
                            <?php comments_template(); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($show_sidebar === 'yes' && $concierge['show_concierge_card'] !== 'no') : ?>
                    <div class="ctc-hosp-sidebar-col">
                        <div class="ctc-concierge-card-box">
                            
                            <!-- Concierge Header -->
                            <div class="ctc-concierge-header">
                                <div class="ctc-concierge-badge-icon">
                                    <i class="fas fa-user-nurse"></i>
                                </div>
                                <div class="ctc-concierge-title-wrap">
                                    <h3 class="ctc-concierge-title"><?php echo esc_html($concierge['title']); ?></h3>
                                    <?php if (!empty($concierge['badge'])) : ?>
                                        <span class="ctc-concierge-status-badge"><i class="fas fa-circle-dot"></i> <?php echo esc_html($concierge['badge']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Highlights -->
                            <?php if (!empty($concierge['services']) && is_array($concierge['services'])) : ?>
                                <div class="ctc-concierge-highlights">
                                    <?php foreach ($concierge['services'] as $srv) : 
                                        if (empty($srv['label']) && empty($srv['value'])) continue;
                                        $icon = !empty($srv['icon']) ? $srv['icon'] : 'fas fa-check-circle';
                                    ?>
                                        <div class="ctc-highlight-item">
                                            <?php if (!empty($srv['label'])) : ?>
                                                <span class="ctc-highlight-label"><?php echo esc_html($srv['label']); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($srv['value'])) : ?>
                                                <span class="ctc-highlight-value"><i class="<?php echo esc_attr($icon); ?>"></i> <?php echo esc_html($srv['value']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Direct Channels -->
                            <div class="ctc-channels-action-group">
                                <?php if (!empty($concierge['whatsapp']['url']) && !empty($concierge['whatsapp']['number'])) : ?>
                                    <a href="<?php echo esc_url($concierge['whatsapp']['url']); ?>" target="_blank" rel="noopener noreferrer nofollow" class="ctc-channel-btn ctc-btn-whatsapp" title="<?php esc_attr_e('Chat on WhatsApp', 'caretochina-hospitals'); ?>">
                                        <div class="ctc-ch-icon-wrap"><i class="fab fa-whatsapp"></i></div>
                                        <div class="ctc-ch-info">
                                            <span class="ctc-ch-sub"><?php echo esc_html($concierge['whatsapp']['label']); ?></span>
                                            <span class="ctc-ch-main"><?php echo esc_html($concierge['whatsapp']['number']); ?></span>
                                        </div>
                                        <div class="ctc-ch-arrow"><i class="fas fa-paper-plane"></i></div>
                                    </a>
                                <?php endif; ?>

                                <?php if (!empty($concierge['phone']['url']) && !empty($concierge['phone']['number'])) : ?>
                                    <a href="<?php echo esc_url($concierge['phone']['url']); ?>" class="ctc-channel-btn ctc-btn-phone" title="<?php esc_attr_e('Call Hotline', 'caretochina-hospitals'); ?>">
                                        <div class="ctc-ch-icon-wrap"><i class="fas fa-phone-volume"></i></div>
                                        <div class="ctc-ch-info">
                                            <span class="ctc-ch-sub"><?php echo esc_html($concierge['phone']['label']); ?></span>
                                            <span class="ctc-ch-main"><?php echo esc_html($concierge['phone']['number']); ?></span>
                                        </div>
                                        <div class="ctc-ch-arrow"><i class="fas fa-arrow-up-right-from-square"></i></div>
                                    </a>
                                <?php endif; ?>

                                <?php if (!empty($concierge['email']['url']) && !empty($concierge['email']['address'])) : ?>
                                    <a href="<?php echo esc_url($concierge['email']['url']); ?>" class="ctc-channel-btn ctc-btn-email" title="<?php esc_attr_e('Email Concierge', 'caretochina-hospitals'); ?>">
                                        <div class="ctc-ch-icon-wrap"><i class="fas fa-envelope-open-text"></i></div>
                                        <div class="ctc-ch-info">
                                            <span class="ctc-ch-sub"><?php echo esc_html($concierge['email']['label']); ?></span>
                                            <span class="ctc-ch-main"><?php echo esc_html($concierge['email']['address']); ?></span>
                                        </div>
                                        <div class="ctc-ch-arrow"><i class="fas fa-arrow-up-right-from-square"></i></div>
                                    </a>
                                <?php endif; ?>

                                <?php if (!empty($concierge['custom_channels']) && is_array($concierge['custom_channels'])) : ?>
                                    <?php foreach ($concierge['custom_channels'] as $cust) : 
                                        if (empty($cust['url'])) continue;
                                        $cust_icon = !empty($cust['icon']) ? $cust['icon'] : 'fas fa-link';
                                    ?>
                                        <a href="<?php echo esc_url($cust['url']); ?>" target="_blank" rel="noopener noreferrer nofollow" class="ctc-channel-btn ctc-btn-custom">
                                            <div class="ctc-ch-icon-wrap"><i class="<?php echo esc_attr($cust_icon); ?>"></i></div>
                                            <div class="ctc-ch-info">
                                                <span class="ctc-ch-sub"><?php echo esc_html($cust['label'] ?: __('Direct Channel', 'caretochina-hospitals')); ?></span>
                                                <span class="ctc-ch-main"><?php echo esc_html($cust['name']); ?></span>
                                            </div>
                                            <div class="ctc-ch-arrow"><i class="fas fa-arrow-up-right-from-square"></i></div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Social Bar -->
                            <?php 
                            $has_social = !empty($concierge['facebook']['url']) || !empty($concierge['instagram']['url']) || !empty($concierge['youtube']['url']) || !empty($concierge['x']['url']) || !empty($concierge['custom_socials']);
                            if ($has_social && $concierge['show_social_bar'] !== 'no') : 
                            ?>
                                <div class="ctc-concierge-social-bar">
                                    <span class="ctc-social-heading"><?php _e('Social Channels & Media:', 'caretochina-hospitals'); ?></span>
                                    <div class="ctc-social-icons">
                                        <?php if (!empty($concierge['facebook']['url'])) : ?>
                                            <a href="<?php echo esc_url($concierge['facebook']['url']); ?>" target="_blank" rel="noopener noreferrer nofollow" class="ctc-social-btn ctc-soc-fb" title="<?php echo esc_attr($concierge['facebook']['label']); ?>" aria-label="<?php esc_attr_e('Visit CareToChina on Facebook', 'caretochina-hospitals'); ?>">
                                                <i class="fab fa-facebook-f"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($concierge['instagram']['url'])) : ?>
                                            <a href="<?php echo esc_url($concierge['instagram']['url']); ?>" target="_blank" rel="noopener noreferrer nofollow" class="ctc-social-btn ctc-soc-ig" title="<?php echo esc_attr($concierge['instagram']['label']); ?>" aria-label="<?php esc_attr_e('Visit CareToChina on Instagram', 'caretochina-hospitals'); ?>">
                                                <i class="fab fa-instagram"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($concierge['youtube']['url'])) : ?>
                                            <a href="<?php echo esc_url($concierge['youtube']['url']); ?>" target="_blank" rel="noopener noreferrer nofollow" class="ctc-social-btn ctc-soc-yt" title="<?php echo esc_attr($concierge['youtube']['label']); ?>" aria-label="<?php esc_attr_e('Visit CareToChina on YouTube', 'caretochina-hospitals'); ?>">
                                                <i class="fab fa-youtube"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($concierge['x']['url'])) : ?>
                                            <a href="<?php echo esc_url($concierge['x']['url']); ?>" target="_blank" rel="noopener noreferrer nofollow" class="ctc-social-btn ctc-soc-x" title="<?php echo esc_attr($concierge['x']['label'] ?: 'X (Twitter)'); ?>" aria-label="<?php esc_attr_e('Visit CareToChina on X (formerly Twitter)', 'caretochina-hospitals'); ?>">
                                                <i class="fab fa-x-twitter"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($concierge['custom_socials']) && is_array($concierge['custom_socials'])) : ?>
                                            <?php foreach ($concierge['custom_socials'] as $csoc) : 
                                                if (empty($csoc['url'])) continue;
                                                $csoc_icon = !empty($csoc['icon']) ? $csoc['icon'] : 'fas fa-share-nodes';
                                                $csoc_title = $csoc['name'] ?? __('Social Platform', 'caretochina-hospitals');
                                            ?>
                                                <a href="<?php echo esc_url($csoc['url']); ?>" target="_blank" rel="noopener noreferrer nofollow" class="ctc-social-btn ctc-soc-custom" title="<?php echo esc_attr($csoc_title); ?>" aria-label="<?php echo esc_attr(sprintf(__('Visit CareToChina on %s', 'caretochina-hospitals'), $csoc_title)); ?>">
                                                    <i class="<?php echo esc_attr($csoc_icon); ?>"></i>
                                                </a>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                        </div>

                        <?php if ($concierge['show_booking_button'] !== 'no') : ?>
                        <div class="ctc-hosp-card-box ctc-sidebar-card ctc-quote-promo">
                            <h3 class="cy-heading ctc-sidebar-title" style="color: #FFFFFF;"><i class="fas fa-calendar-check" style="color:#5eead4;"></i> <?php _e('Need Treatment Assistance?', 'caretochina-hospitals'); ?></h3>
                            <p style="color: #CCFBF1; font-size: 0.9rem; line-height: 1.5; margin-bottom: 20px;">
                                <?php _e('Get matched with top doctors and receive a transparent treatment cost estimate in 24 hours.', 'caretochina-hospitals'); ?>
                            </p>
                            <a href="<?php echo esc_attr($concierge['booking']['url'] ?: $quote_url); ?>" class="ctc-sidebar-quote-btn">
                                <i class="fas fa-paper-plane"></i> <?php echo esc_html($concierge['booking']['label'] ?: __('Get Free Quote', 'caretochina-hospitals')); ?>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>

        </div>

        <style>
            .ctc-single-hosp-wrapper {
                width: 100%;
                font-family: 'Inter', sans-serif;
            }
            .ctc-hosp-hero {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 24px;
                overflow: hidden;
                box-shadow: 0 10px 25px -5px rgba(15, 118, 110, 0.08);
                margin-bottom: 32px;
            }
            .ctc-hosp-hero-img-box {
                position: relative;
                width: 100%;
                height: 380px;
                overflow: hidden;
            }
            .ctc-hero-img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: block;
            }
            .ctc-hero-overlay {
                position: absolute;
                top: 20px;
                left: 20px;
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
            }
            .ctc-hero-cert {
                background: #10b981;
                color: #ffffff;
                font-size: 0.85rem;
                font-weight: 600;
                padding: 6px 14px;
                border-radius: 999px;
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }
            .ctc-hero-type {
                background: #0f172abf;
                color: #ffffff;
                font-size: 0.85rem;
                font-weight: 500;
                padding: 6px 14px;
                border-radius: 999px;
                backdrop-filter: blur(4px);
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }
            .ctc-hero-header-content {
                padding: 28px 32px;
            }
            .ctc-hero-meta {
                display: flex;
                gap: 20px;
                align-items: center;
                margin-bottom: 12px;
                font-size: 0.95rem;
            }
            .ctc-hero-loc {
                color: #0f766e;
                font-weight: 600;
            }
            .ctc-hero-rating {
                color: #f59e0b;
                font-weight: 700;
            }
            .ctc-hero-title {
                font-family: 'Manrope', sans-serif;
                font-size: 2.2rem;
                font-weight: 800;
                color: #0f172a;
                margin: 0 0 10px 0;
            }
            .ctc-hero-address {
                color: #64748b;
                font-size: 0.95rem;
                margin-bottom: 24px;
            }
            .ctc-hero-cta-row {
                display: flex;
                gap: 16px;
                flex-wrap: wrap;
            }
            .ctc-quote-btn {
                background: #0f766e;
                color: #ffffff;
                padding: 12px 28px;
                border-radius: 999px;
                font-family: 'Manrope', sans-serif;
                font-weight: 700;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 10px;
                transition: all 0.3s ease;
            }
            .ctc-quote-btn:hover {
                background: #0d645c;
                color: #ffffff;
                transform: translateY(-2px);
            }
            .ctc-website-btn {
                background: transparent;
                color: #0f172a;
                border: 1.5px solid #cbd5e1;
                padding: 12px 24px;
                border-radius: 999px;
                font-family: 'Manrope', sans-serif;
                font-weight: 600;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                transition: all 0.3s ease;
            }
            .ctc-website-btn:hover {
                border-color: #0f766e;
                color: #0f766e;
                background: #ccfbf1;
            }

            .ctc-hosp-layout-grid {
                display: grid;
                grid-template-columns: 2fr 1fr;
                gap: 32px;
            }
            .ctc-hosp-card-box {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 20px;
                padding: 28px;
                margin-bottom: 24px;
                box-shadow: 0 4px 12px rgba(15, 118, 110, 0.04);
            }
            .ctc-box-title {
                font-family: 'Manrope', sans-serif;
                font-size: 1.35rem;
                font-weight: 700;
                color: #0f172a;
                margin: 0 0 20px 0;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .ctc-box-title i { color: #0f766e; }
            .ctc-box-body {
                color: #475569;
                line-height: 1.7;
                font-size: 1rem;
            }
            .ctc-spec-pills {
                display: flex;
                flex-wrap: wrap;
                gap: 12px;
            }
            .ctc-spec-pill {
                background: #ccfbf1;
                color: #0f766e;
                font-weight: 600;
                font-size: 0.9rem;
                padding: 8px 16px;
                border-radius: 999px;
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }
            .ctc-dept-tags {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
            }
            .ctc-dept-tag {
                background: #f1f5f9;
                color: #475569;
                font-weight: 500;
                font-size: 0.85rem;
                padding: 6px 14px;
                border-radius: 8px;
            }

            /* Sleek Modern Concierge Card & Communication Channels */
            .ctc-concierge-card-box {
                background: linear-gradient(145deg, #131c31 0%, #0f172a 100%);
                border: 1px solid rgba(255, 255, 255, 0.08);
                border-radius: 24px;
                padding: 26px 22px;
                margin-bottom: 24px;
                color: #ffffff;
                box-shadow: 0 12px 32px -6px rgba(15, 23, 42, 0.4);
                position: relative;
                overflow: hidden;
            }
            .ctc-concierge-card-box::before {
                content: '';
                position: absolute;
                top: -40px;
                right: -40px;
                width: 120px;
                height: 120px;
                background: radial-gradient(circle, rgba(20, 184, 166, 0.2) 0%, transparent 70%);
                border-radius: 50%;
                pointer-events: none;
            }
            .ctc-concierge-header {
                display: flex;
                align-items: center;
                gap: 14px;
                margin-bottom: 18px;
                padding-bottom: 16px;
                border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            }
            .ctc-concierge-badge-icon {
                width: 44px;
                height: 44px;
                border-radius: 12px;
                background: linear-gradient(135deg, #0f766e, #14b8a6);
                display: flex;
                align-items: center;
                justify-content: center;
                color: #ffffff;
                font-size: 20px;
                box-shadow: 0 4px 12px rgba(20, 184, 166, 0.3);
                flex-shrink: 0;
            }
            .ctc-concierge-title-wrap {
                display: flex;
                flex-direction: column;
                gap: 3px;
            }
            .ctc-concierge-title {
                font-family: 'Manrope', sans-serif;
                font-size: 1.2rem;
                font-weight: 800;
                color: #ffffff;
                margin: 0;
                letter-spacing: -0.02em;
            }
            .ctc-concierge-status-badge {
                font-size: 0.72rem;
                font-weight: 600;
                color: #2dd4bf;
                display: inline-flex;
                align-items: center;
                gap: 5px;
            }
            .ctc-concierge-status-badge i {
                font-size: 7px;
                animation: pulse-dot 2s infinite ease-in-out;
            }
            @keyframes pulse-dot {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.4; }
            }

            .ctc-concierge-highlights {
                display: flex;
                flex-direction: column;
                gap: 12px;
                margin-bottom: 20px;
            }
            .ctc-highlight-item {
                display: flex;
                flex-direction: column;
                gap: 3px;
            }
            .ctc-highlight-label {
                font-size: 0.75rem;
                font-weight: 700;
                color: #94a3b8;
                letter-spacing: 0.5px;
                text-transform: uppercase;
            }
            .ctc-highlight-value {
                color: #2dd4bf;
                font-size: 0.92rem;
                font-weight: 600;
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }

            .ctc-channels-action-group {
                display: flex;
                flex-direction: column;
                gap: 9px;
                margin-bottom: 16px;
            }
            .ctc-channel-btn {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 10px 14px;
                border-radius: 12px;
                text-decoration: none;
                background: rgba(255, 255, 255, 0.05);
                border: 1px solid rgba(255, 255, 255, 0.1);
                transition: all 0.25s ease;
                box-sizing: border-box;
            }
            .ctc-channel-btn:hover {
                background: rgba(255, 255, 255, 0.1);
                border-color: #14b8a6;
                transform: translateY(-2px);
            }
            .ctc-btn-whatsapp {
                background: linear-gradient(135deg, #15803d 0%, #16a34a 100%) !important;
                border-color: #22c55e !important;
                box-shadow: 0 4px 14px rgba(22, 163, 74, 0.35);
            }
            .ctc-btn-whatsapp:hover {
                background: linear-gradient(135deg, #166534 0%, #15803d 100%) !important;
                box-shadow: 0 6px 18px rgba(34, 197, 94, 0.45);
            }
            .ctc-ch-icon-wrap {
                width: 32px;
                height: 32px;
                border-radius: 8px;
                background: rgba(255, 255, 255, 0.12);
                display: flex;
                align-items: center;
                justify-content: center;
                color: #ffffff;
                font-size: 15px;
                flex-shrink: 0;
            }
            .ctc-ch-info {
                flex: 1;
                display: flex;
                flex-direction: column;
                gap: 1px;
                overflow: hidden;
            }
            .ctc-ch-sub {
                font-size: 0.7rem;
                font-weight: 600;
                color: rgba(255, 255, 255, 0.75);
                text-transform: uppercase;
                letter-spacing: 0.3px;
            }
            .ctc-ch-main {
                font-size: 0.85rem;
                font-weight: 700;
                color: #ffffff;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .ctc-ch-arrow {
                color: rgba(255, 255, 255, 0.6);
                font-size: 12px;
                transition: transform 0.2s ease;
            }
            .ctc-channel-btn:hover .ctc-ch-arrow {
                transform: translateX(2px);
                color: #ffffff;
            }

            .ctc-concierge-social-bar {
                padding-top: 14px;
                border-top: 1px solid rgba(255, 255, 255, 0.08);
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 8px;
            }
            .ctc-social-heading {
                font-size: 0.74rem;
                font-weight: 600;
                color: #94a3b8;
            }
            .ctc-social-icons {
                display: flex;
                align-items: center;
                gap: 7px;
            }
            .ctc-social-btn {
                width: 32px;
                height: 32px;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.08);
                display: inline-flex;
                align-items: center;
                justify-content: center;
                color: #ffffff;
                text-decoration: none;
                font-size: 13px;
                transition: all 0.2s ease;
            }
            .ctc-soc-fb:hover { background: #1877F2; color: #ffffff; transform: scale(1.1); }
            .ctc-soc-ig:hover { background: linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888); color: #ffffff; transform: scale(1.1); }
            .ctc-soc-yt:hover { background: #FF0000; color: #ffffff; transform: scale(1.1); }
            .ctc-soc-x:hover { background: #000000; color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.4); transform: scale(1.1); }
            .ctc-soc-custom:hover { background: #0f766e; color: #ffffff; border: 1px solid rgba(20, 184, 166, 0.5); transform: scale(1.1); }

            /* Dark Mode Integration */
            html.dark-theme .ctc-hosp-hero, body.dark-theme .ctc-hosp-hero,
            html.dark-theme .ctc-hosp-card-box, body.dark-theme .ctc-hosp-card-box {
                background-color: #1c2541 !important;
                border-color: #2d3748 !important;
                color: #f8fafc !important;
            }
            html.dark-theme .ctc-hero-title, body.dark-theme .ctc-hero-title,
            html.dark-theme .ctc-box-title, body.dark-theme .ctc-box-title,
            html.dark-theme .ctc-sidebar-title, body.dark-theme .ctc-sidebar-title {
                color: #f8fafc !important;
            }
            html.dark-theme .ctc-hero-address, body.dark-theme .ctc-hero-address,
            html.dark-theme .ctc-box-body, body.dark-theme .ctc-box-body {
                color: #cbd5e1 !important;
            }
            html.dark-theme .ctc-website-btn, body.dark-theme .ctc-website-btn {
                color: #f8fafc !important;
                border-color: #2d3748 !important;
            }
            html.dark-theme .ctc-dept-tag, body.dark-theme .ctc-dept-tag {
                background-color: #0b132b !important;
                color: #94a3b8 !important;
            }

            @media (min-width: 992px) {
                .ctc-hosp-sidebar-col {
                    position: sticky;
                    top: 28px;
                    align-self: flex-start;
                }
            }
            @media (max-width: 991px) {
                .ctc-hosp-layout-grid {
                    grid-template-columns: 1fr;
                }
            }
            @media (max-width: 600px) {
                .ctc-hero-header-content {
                    padding: 20px 16px;
                }
                .ctc-hero-title {
                    font-size: clamp(1.4rem, 4vw + 0.5rem, 2rem);
                }
                .ctc-hero-cta-row a,
                .ctc-sidebar-quote-btn {
                    width: 100%;
                    text-align: center;
                    justify-content: center;
                    min-height: 50px;
                }
            }
            @media (max-width: 480px) {
                .ctc-concierge-card-box {
                    padding: 20px 16px;
                }
                .ctc-concierge-social-bar {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 12px;
                }
                .ctc-social-icons {
                    flex-wrap: wrap;
                }
            }
            @media (orientation: landscape) and (max-height: 500px) {
                .ctc-hosp-hero-img-box {
                    height: 160px !important;
                }
            }
        </style>
        <?php
    }
}