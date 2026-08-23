<?php
/**
 * Single Hospital Template - CareToChina
 */
get_header();

while (have_posts()) : the_post();
    $post_id = get_the_ID();

    // If post is built with Elementor or currently being edited in Elementor editor, render standard Elementor content container
    if (class_exists('\Elementor\Plugin') && (\Elementor\Plugin::$instance->db->is_built_with_elementor($post_id) || \Elementor\Plugin::$instance->editor->is_edit_mode() || \Elementor\Plugin::$instance->preview->is_preview_mode())) {
        ?>
        <main id="primary" class="site-main ctc-elementor-single-hospital" style="padding: 40px 0;">
            <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 0 20px;">
                <?php the_content(); ?>
            </div>
        </main>
        <?php
        get_footer();
        return;
    }

    $title = get_the_title();

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

    // Fetch dynamic concierge & booking communication channels
    $concierge = CareToChina_Hospitals_Plugin::get_hospital_concierge_data($post_id);
    ?>

    <div class="ctc-single-hosp-container">
        
        <!-- Hospital Header Banner -->
        <div class="ctc-hosp-hero">
            <div class="ctc-hosp-hero-img-box">
                <?php if (has_post_thumbnail()) : ?>
                    <?php the_post_thumbnail('full', ['class' => 'ctc-hero-img', 'alt' => esc_attr($title)]); ?>
                <?php else : ?>
                    <img src="https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?auto=format&fit=crop&w=1200&q=80" class="ctc-hero-img" alt="<?php echo esc_attr($title); ?>">
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
                    <a href="<?php echo esc_attr($quote_url); ?>" class="ctc-quote-btn">
                        <i class="fas fa-paper-plane"></i> <?php _e('Request Free Quote & Consultation', 'caretochina-hospitals'); ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content & Sidebar Layout -->
        <div class="ctc-hosp-layout-grid">
            
            <!-- Left Main Column: Overview, Specialities, Departments, Comments -->
            <div class="ctc-hosp-main-col">
                
                <!-- Overview Section -->
                <div class="ctc-hosp-card-box">
                    <h2 class="cy-heading ctc-box-title"><i class="fas fa-info-circle"></i> <?php _e('Hospital Overview', 'caretochina-hospitals'); ?></h2>
                    <div class="cy-paragraph ctc-box-body">
                        <?php the_content(); ?>
                    </div>
                </div>

                <!-- Specialities Section -->
                <?php if (!empty($specialties) && !is_wp_error($specialties)) : ?>
                    <div class="ctc-hosp-card-box">
                        <h2 class="cy-heading ctc-box-title"><i class="fas fa-stethoscope"></i> <?php _e('Specialities & Medical Programs', 'caretochina-hospitals'); ?></h2>
                        <div class="ctc-spec-pills">
                            <?php foreach ($specialties as $spec) : ?>
                                <span class="ctc-spec-pill"><i class="fas fa-check-circle"></i> <?php echo esc_html($spec->name); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Departments Section -->
                <?php if (!empty($departments) && !is_wp_error($departments)) : ?>
                    <div class="ctc-hosp-card-box">
                        <h2 class="cy-heading ctc-box-title"><i class="fas fa-clinic-medical"></i> <?php _e('Clinical Departments', 'caretochina-hospitals'); ?></h2>
                        <div class="ctc-dept-tags">
                            <?php foreach ($departments as $dept) : ?>
                                <span class="ctc-dept-tag">#<?php echo esc_html($dept->name); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Patient Reviews / Comments -->
                <?php if (comments_open() || get_comments_number()) : ?>
                    <div class="ctc-hosp-card-box ctc-comments-box">
                        <h2 class="cy-heading ctc-box-title"><i class="fas fa-comments"></i> <?php _e('Patient Reviews & Enquiries', 'caretochina-hospitals'); ?></h2>
                        <?php comments_template(); ?>
                    </div>
                <?php endif; ?>

            </div>

            <!-- Right Sidebar Column: CareToChina Concierge & Direct Booking Channels -->
            <div class="ctc-hosp-sidebar-col">
                
                <?php if ($concierge['show_concierge_card'] !== 'no') : ?>
                <div class="ctc-concierge-card-box">
                    
                    <!-- Concierge Card Header -->
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

                    <!-- Concierge Services & Highlights -->
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

                    <!-- Direct Chat & Booking Channels -->
                    <div class="ctc-channels-action-group">

                        <!-- WhatsApp Direct Chat Button -->
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

                        <!-- Phone Hotline -->
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

                        <!-- Email Channel -->
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

                        <!-- Custom Extensible Channels -->
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

                    <!-- Social & Media Links Bar (Facebook, Instagram, YouTube, X & Extensible Socials) -->
                    <?php 
                    $has_social = !empty($concierge['facebook']['url']) || !empty($concierge['instagram']['url']) || !empty($concierge['youtube']['url']) || !empty($concierge['x']['url']) || !empty($concierge['custom_socials']);
                    if ($has_social && $concierge['show_social_bar'] !== 'no') : 
                    ?>
                        <div class="ctc-concierge-social-bar">
                            <span class="ctc-social-heading"><?php _e('Social Channels & Media:', 'caretochina-hospitals'); ?></span>
                            <div class="ctc-social-icons">
                                <?php if (!empty($concierge['facebook']['url'])) : ?>
                                    <a href="<?php echo esc_url($concierge['facebook']['url']); ?>" target="_blank" rel="noopener noreferrer nofollow" class="ctc-social-btn ctc-soc-fb" title="<?php echo esc_attr($concierge['facebook']['label']); ?>">
                                        <i class="fab fa-facebook-f"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($concierge['instagram']['url'])) : ?>
                                    <a href="<?php echo esc_url($concierge['instagram']['url']); ?>" target="_blank" rel="noopener noreferrer nofollow" class="ctc-social-btn ctc-soc-ig" title="<?php echo esc_attr($concierge['instagram']['label']); ?>">
                                        <i class="fab fa-instagram"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($concierge['youtube']['url'])) : ?>
                                    <a href="<?php echo esc_url($concierge['youtube']['url']); ?>" target="_blank" rel="noopener noreferrer nofollow" class="ctc-social-btn ctc-soc-yt" title="<?php echo esc_attr($concierge['youtube']['label']); ?>">
                                        <i class="fab fa-youtube"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($concierge['x']['url'])) : ?>
                                    <a href="<?php echo esc_url($concierge['x']['url']); ?>" target="_blank" rel="noopener noreferrer nofollow" class="ctc-social-btn ctc-soc-x" title="<?php echo esc_attr($concierge['x']['label'] ?: 'X (Twitter)'); ?>">
                                        <i class="fab fa-x-twitter"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($concierge['custom_socials']) && is_array($concierge['custom_socials'])) : ?>
                                    <?php foreach ($concierge['custom_socials'] as $csoc) : 
                                        if (empty($csoc['url'])) continue;
                                        $csoc_icon = !empty($csoc['icon']) ? $csoc['icon'] : 'fas fa-share-nodes';
                                    ?>
                                        <a href="<?php echo esc_url($csoc['url']); ?>" target="_blank" rel="noopener noreferrer nofollow" class="ctc-social-btn ctc-soc-custom" title="<?php echo esc_attr($csoc['name'] ?? __('Social Platform', 'caretochina-hospitals')); ?>">
                                            <i class="<?php echo esc_attr($csoc_icon); ?>"></i>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
                <?php endif; ?>

                <!-- Booking / Consultation Promo Card -->
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

        </div>

    </div>

    <style>
        .ctc-single-hosp-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
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
            margin-top: 60px;
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
        .ctc-box-title i {
            color: #0f766e;
        }
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

        .ctc-quote-promo {
            background: linear-gradient(135deg, #0f766e, #0d645c) !important;
            color: #ffffff !important;
        }
        .ctc-sidebar-quote-btn {
            background: #ffffff;
            color: #0f766e;
            font-family: 'Manrope', sans-serif;
            font-weight: 700;
            padding: 10px 20px;
            border-radius: 999px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s ease;
        }
        .ctc-sidebar-quote-btn:hover {
            background: #ccfbf1;
            color: #0d645c;
        }

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

        @media (max-width: 991px) {
            .ctc-hosp-layout-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <?php
endwhile;

get_footer();