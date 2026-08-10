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
    $address            = get_post_meta($post_id, '_hospital_address', true);
    $rating             = get_post_meta($post_id, '_hospital_rating', true);
    $certification      = get_post_meta($post_id, '_hospital_certification', true);
    $phone_main         = get_post_meta($post_id, '_hospital_phone_main', true);
    $phone_appointment  = get_post_meta($post_id, '_hospital_phone_appointment', true);
    $phone_dept         = get_post_meta($post_id, '_hospital_phone_dept', true);
    $phone_emergency    = get_post_meta($post_id, '_hospital_phone_emergency', true);
    $website            = get_post_meta($post_id, '_hospital_website', true);
    $quote_url          = get_post_meta($post_id, '_hospital_quote_url', true);

    if (!$location) $location = 'Shanghai, China';
    if (!$rating) $rating = '4.9 (1,240 Reviews)';
    if (!$certification) $certification = 'JCI Certified';
    if (!$type) $type = 'JCI Accredited Multi-Specialty Hospital Center';
    if (!$quote_url) $quote_url = '#booking';

    $specialties = get_the_terms($post_id, 'hospital_specialty');
    $departments = get_the_terms($post_id, 'hospital_department');
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
                
                <?php if (!empty($address)) : ?>
                    <p class="ctc-hero-address"><i class="fas fa-location-arrow"></i> <?php echo esc_html($address); ?></p>
                <?php endif; ?>

                <div class="ctc-hero-cta-row">
                    <a href="<?php echo esc_attr($quote_url); ?>" class="ctc-quote-btn">
                        <i class="fas fa-paper-plane"></i> <?php _e('Request Free Quote & Consultation', 'caretochina-hospitals'); ?>
                    </a>

                    <?php if (!empty($website)) : ?>
                        <a href="<?php echo esc_url($website); ?>" target="_blank" rel="noopener noreferrer" class="ctc-website-btn">
                            <i class="fas fa-external-link-alt"></i> <?php _e('Official Website', 'caretochina-hospitals'); ?>
                        </a>
                    <?php endif; ?>
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
                        <?php  ?>
                    </div>
                <?php endif; ?>

            </div>

            <!-- Right Sidebar Column: Contact Phone Breakdown & Fast Info -->
            <div class="ctc-hosp-sidebar-col">
                
                <div class="ctc-hosp-card-box ctc-sidebar-card">
                    <h3 class="cy-heading ctc-sidebar-title"><i class="fas fa-phone-alt"></i> <?php _e('Hospital Phone Lines', 'caretochina-hospitals'); ?></h3>
                    <ul class="ctc-phone-list">
                        <?php if (!empty($phone_main)) : ?>
                            <li>
                                <span class="ctc-phone-label"><?php _e('Main Line:', 'caretochina-hospitals'); ?></span>
                                <a href="tel:<?php echo esc_attr($phone_main); ?>" class="ctc-phone-val"><i class="fas fa-phone"></i> <?php echo esc_html($phone_main); ?></a>
                            </li>
                        <?php endif; ?>

                        <?php if (!empty($phone_appointment)) : ?>
                            <li>
                                <span class="ctc-phone-label"><?php _e('Appointments:', 'caretochina-hospitals'); ?></span>
                                <a href="tel:<?php echo esc_attr($phone_appointment); ?>" class="ctc-phone-val"><i class="fas fa-calendar-check"></i> <?php echo esc_html($phone_appointment); ?></a>
                            </li>
                        <?php endif; ?>

                        <?php if (!empty($phone_dept)) : ?>
                            <li>
                                <span class="ctc-phone-label"><?php _e('Department Desk:', 'caretochina-hospitals'); ?></span>
                                <a href="tel:<?php echo esc_attr($phone_dept); ?>" class="ctc-phone-val"><i class="fas fa-building"></i> <?php echo esc_html($phone_dept); ?></a>
                            </li>
                        <?php endif; ?>

                        <?php if (!empty($phone_emergency)) : ?>
                            <li class="ctc-emergency-item">
                                <span class="ctc-phone-label"><?php _e('24/7 Emergency:', 'caretochina-hospitals'); ?></span>
                                <a href="tel:<?php echo esc_attr($phone_emergency); ?>" class="ctc-phone-val ctc-emer-val"><i class="fas fa-ambulance"></i> <?php echo esc_html($phone_emergency); ?></a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="ctc-hosp-card-box ctc-sidebar-card ctc-quote-promo">
                    <h3 class="cy-heading ctc-sidebar-title" style="color: #FFFFFF;"><?php _e('Need Treatment Assistance?', 'caretochina-hospitals'); ?></h3>
                    <p style="color: #CCFBF1; font-size: 0.9rem; line-height: 1.5; margin-bottom: 20px;">
                        <?php _e('Get matched with top doctors and receive a transparent treatment cost estimate in 24 hours.', 'caretochina-hospitals'); ?>
                    </p>
                    <a href="<?php echo esc_attr($quote_url); ?>" class="ctc-sidebar-quote-btn">
                        <i class="fas fa-paper-plane"></i> <?php _e('Get Free Quote', 'caretochina-hospitals'); ?>
                    </a>
                </div>

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

        .ctc-sidebar-title {
            font-family: 'Manrope', sans-serif;
            font-size: 1.15rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 16px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .ctc-sidebar-title i { color: #0f766e; }
        .ctc-phone-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .ctc-phone-list li {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .ctc-phone-label {
            font-size: 0.82rem;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
        }
        .ctc-phone-val {
            color: #0f766e;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .ctc-emergency-item {
            background: #fef2f2;
            padding: 12px;
            border-radius: 12px;
            border: 1px solid #fecaca;
        }
        .ctc-emer-val {
            color: #dc2626 !important;
            font-weight: 700;
        }

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