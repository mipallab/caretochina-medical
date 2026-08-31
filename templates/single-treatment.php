<?php
/**
 * Single Medical Treatment Template - CareToChina Medical Suite
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()) : the_post();
$post_id = get_the_ID();

// If post is built with Elementor or currently in Elementor editor, render standard container
if (class_exists('\Elementor\Plugin') && (\Elementor\Plugin::$instance->db->is_built_with_elementor($post_id) || \Elementor\Plugin::$instance->editor->is_edit_mode() || \Elementor\Plugin::$instance->preview->is_preview_mode())) {
    ?>
    <main id="primary" class="site-main ctc-elementor-single-treatment" style="padding: 40px 0;">
        <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 0 20px;">
            <?php the_content(); ?>
        </div>
    </main>
    <?php
    break;
}

    $title        = get_the_title();
    $sub_heading  = get_post_meta($post_id, '_treatment_sub_heading', true);
    $price        = get_post_meta($post_id, '_treatment_price', true);
    $day_stay     = get_post_meta($post_id, '_treatment_day_stay', true);
    $rating       = get_post_meta($post_id, '_treatment_rating', true);
    $success_rate = get_post_meta($post_id, '_treatment_success_rate', true);
    
    // Parse Booking Link / Widget Trigger Selector (#id, .class, or URL)
    $raw_quote_target = trim(strval(get_post_meta($post_id, '_treatment_quote_url', true)));
    $quote_target_selector = '';
    $quote_url_href = '#';

    if (empty($raw_quote_target)) {
        $quote_url_href = '#booking';
        $quote_target_selector = '#booking';
    } elseif (str_starts_with($raw_quote_target, '#') || str_starts_with($raw_quote_target, '.')) {
        $quote_target_selector = $raw_quote_target;
        $quote_url_href = '#';
    } elseif (filter_var($raw_quote_target, FILTER_VALIDATE_URL) || str_starts_with($raw_quote_target, '/') || str_starts_with($raw_quote_target, 'tel:') || str_starts_with($raw_quote_target, 'mailto:')) {
        $quote_url_href = $raw_quote_target;
        $quote_target_selector = '';
    } else {
        // If entered without leading '#' or '.', treat as selector/ID
        $quote_target_selector = '#' . $raw_quote_target;
        $quote_url_href = '#' . $raw_quote_target;
    }

    if (!$rating) $rating = '4.9 (480 Reviews)';
    if (!$success_rate) $success_rate = '98.5% Success Rate';

    $categories = get_the_terms($post_id, 'treatment_category');
    $primary_cat = (!empty($categories) && !is_wp_error($categories)) ? $categories[0]->name : __('Specialized Care', 'caretochina-medical');

    $curr_symbol = class_exists('CareToChina_Packages') ? CareToChina_Packages::get_currency_symbol() : '$';
    if ($price !== '' && $price !== false && $price !== null) {
        $clean_num = trim(str_replace([',', ' ', '$', '€', '£', '¥', 'USD', 'EUR', 'GBP', 'CNY'], '', $price));
        if (is_numeric($clean_num) && $clean_num !== '') {
            $price_display = $curr_symbol . number_format(floatval($clean_num), 0);
        } else {
            $price_display = esc_html($price);
        }
    } else {
        $price_display = __('Upon Consultation', 'caretochina-medical');
    }

    // Fetch concierge channels from settings
    $settings = class_exists('CareToChina_Hospital_Settings') ? CareToChina_Hospital_Settings::get_settings() : [];
    $whatsapp_num = $settings['whatsapp_number'] ?? '';
    $phone_num    = $settings['phone_number'] ?? '';
    $email_addr   = $settings['email'] ?? '';
    ?>

    <div class="ctc-treatment-single-page">
        
        <!-- Hero Header Banner -->
        <header class="ctc-treat-single-hero">
            <div class="ctc-treat-single-container">
                
                <!-- Breadcrumbs -->
                <nav class="ctc-treat-breadcrumbs" aria-label="Breadcrumb">
                    <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'caretochina-medical'); ?></a>
                    <span class="sep">/</span>
                    <a href="<?php echo esc_url(get_post_type_archive_link('medical_treatment')); ?>"><?php esc_html_e('Medical Treatments', 'caretochina-medical'); ?></a>
                    <span class="sep">/</span>
                    <span class="current"><?php echo esc_html($title); ?></span>
                </nav>

                <div class="ctc-treat-hero-meta">
                    <span class="ctc-badge-cat-hero"><i class="fas fa-stethoscope"></i> <?php echo esc_html($primary_cat); ?></span>
                    <?php if (!empty($day_stay)) : ?>
                        <span class="ctc-badge-stay-hero"><i class="fas fa-clock"></i> <?php echo esc_html($day_stay); ?></span>
                    <?php endif; ?>
                    <span class="ctc-badge-rating-hero"><i class="fa fa-star"></i> <?php echo esc_html($rating); ?></span>
                </div>

                <h1 class="ctc-treat-single-title"><?php echo esc_html($title); ?></h1>

                <?php if (!empty($sub_heading)) : ?>
                    <p class="ctc-treat-single-subtitle"><?php echo esc_html($sub_heading); ?></p>
                <?php endif; ?>

            </div>
        </header>

        <!-- Main Content Area -->
        <main class="ctc-treat-single-container ctc-treat-layout-wrap">
            
            <!-- Left: Main Treatment Article -->
            <article class="ctc-treat-main-content">
                
                <!-- Featured Image Showcase -->
                <?php if (has_post_thumbnail()) : ?>
                    <div class="ctc-treat-main-img-box">
                        <?php the_post_thumbnail('full', ['class' => 'ctc-treat-main-img', 'alt' => esc_attr($title)]); ?>
                    </div>
                <?php endif; ?>

                <!-- Procedure Highlights Banner -->
                <div class="ctc-treat-highlights-grid">
                    <div class="ctc-treat-hl-card">
                        <div class="ctc-treat-hl-icon"><i class="fas fa-hand-holding-usd"></i></div>
                        <div class="ctc-treat-hl-info">
                            <span class="lbl"><?php esc_html_e('Estimated Price', 'caretochina-medical'); ?></span>
                            <span class="val"><?php echo esc_html($price_display); ?></span>
                        </div>
                    </div>

                    <div class="ctc-treat-hl-card">
                        <div class="ctc-treat-hl-icon"><i class="fas fa-bed"></i></div>
                        <div class="ctc-treat-hl-info">
                            <span class="lbl"><?php esc_html_e('Hospital Stay', 'caretochina-medical'); ?></span>
                            <span class="val"><?php echo esc_html(!empty($day_stay) ? $day_stay : __('Flexible', 'caretochina-medical')); ?></span>
                        </div>
                    </div>

                    <div class="ctc-treat-hl-card">
                        <div class="ctc-treat-hl-icon"><i class="fas fa-shield-alt"></i></div>
                        <div class="ctc-treat-hl-info">
                            <span class="lbl"><?php esc_html_e('Clinical Quality', 'caretochina-medical'); ?></span>
                            <span class="val"><?php echo esc_html($success_rate); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Rich Post Content (Editor Description) -->
                <div class="ctc-treat-body-content">
                    <h2 class="ctc-treat-sec-heading"><?php esc_html_e('Treatment Overview & Clinical Details', 'caretochina-medical'); ?></h2>
                    <div class="ctc-treat-prose">
                        <?php the_content(); ?>
                    </div>
                </div>

                <!-- Inquiry & Booking Callout -->
                <div class="ctc-treat-cta-banner">
                    <div class="ctc-treat-cta-text">
                        <h3><?php esc_html_e('Ready to Consult with Specialist Physicians in China?', 'caretochina-medical'); ?></h3>
                        <p><?php esc_html_e('Our multilingual medical coordinators assist with second opinions, customized cost estimates, travel arrangements, and hospital admission.', 'caretochina-medical'); ?></p>
                    </div>
                    <div class="ctc-treat-cta-actions">
                        <a href="<?php echo esc_attr($quote_url_href); ?>" data-trigger-target="<?php echo esc_attr($quote_target_selector ?: $quote_url_href); ?>" class="ctc-btn-hero-book ctc-inquiry-trigger-btn">
                            <i class="fas fa-calendar-check"></i> <?php esc_html_e('Book Consultation', 'caretochina-medical'); ?>
                        </a>
                    </div>
                </div>

            </article>

            <!-- Right Sidebar: Quick Summary & Concierge Channels -->
            <aside class="ctc-treat-sidebar">
                
                <!-- Quick Summary Card -->
                <div class="ctc-treat-side-card">
                    <h3 class="ctc-treat-side-title"><?php esc_html_e('Treatment Summary', 'caretochina-medical'); ?></h3>
                    
                    <div class="ctc-treat-side-price-box">
                        <span class="lbl"><?php esc_html_e('Estimated Cost', 'caretochina-medical'); ?></span>
                        <span class="price"><?php echo esc_html($price_display); ?></span>
                    </div>

                    <ul class="ctc-treat-side-list">
                        <li>
                            <i class="fas fa-calendar-day"></i>
                            <div>
                                <strong><?php esc_html_e('Stay Duration:', 'caretochina-medical'); ?></strong>
                                <span><?php echo esc_html(!empty($day_stay) ? $day_stay : __('Determined by diagnosis', 'caretochina-medical')); ?></span>
                            </div>
                        </li>
                        <li>
                            <i class="fas fa-tags"></i>
                            <div>
                                <strong><?php esc_html_e('Category:', 'caretochina-medical'); ?></strong>
                                <span><?php echo esc_html($primary_cat); ?></span>
                            </div>
                        </li>
                        <li>
                            <i class="fas fa-star"></i>
                            <div>
                                <strong><?php esc_html_e('Patient Rating:', 'caretochina-medical'); ?></strong>
                                <span><?php echo esc_html($rating); ?></span>
                            </div>
                        </li>
                        <li>
                            <i class="fas fa-language"></i>
                            <div>
                                <strong><?php esc_html_e('Support:', 'caretochina-medical'); ?></strong>
                                <span><?php esc_html_e('1-on-1 English Concierge', 'caretochina-medical'); ?></span>
                            </div>
                        </li>
                    </ul>

                    <a href="<?php echo esc_attr($quote_url_href); ?>" data-trigger-target="<?php echo esc_attr($quote_target_selector ?: $quote_url_href); ?>" class="ctc-treat-side-btn ctc-inquiry-trigger-btn">
                        <i class="fas fa-paper-plane"></i> <?php esc_html_e('Inquire For This Treatment', 'caretochina-medical'); ?>
                    </a>
                </div>

                <!-- Direct Concierge Help Card -->
                <div class="ctc-treat-side-card ctc-concierge-card">
                    <h4 class="ctc-concierge-title"><i class="fas fa-headset" style="color:#0f766e;"></i> <?php esc_html_e('Need Immediate Assistance?', 'caretochina-medical'); ?></h4>
                    <p class="ctc-concierge-sub"><?php esc_html_e('Speak directly with our medical desk coordinators.', 'caretochina-medical'); ?></p>

                    <?php if (!empty($whatsapp_num)) : 
                        $clean_wa = preg_replace('/[^0-9]/', '', $whatsapp_num);
                        $wa_msg = urlencode(sprintf('Hello CareToChina, I am inquiring about the treatment: %s', $title));
                        ?>
                        <a href="https://api.whatsapp.com/send?phone=<?php echo esc_attr($clean_wa); ?>&text=<?php echo esc_attr($wa_msg); ?>" target="_blank" rel="noopener noreferrer" class="ctc-channel-btn whatsapp">
                            <i class="fab fa-whatsapp"></i> <?php esc_html_e('Chat on WhatsApp', 'caretochina-medical'); ?>
                        </a>
                    <?php endif; ?>

                    <?php if (!empty($phone_num)) : ?>
                        <a href="tel:<?php echo esc_attr(preg_replace('/[^+0-9]/', '', $phone_num)); ?>" class="ctc-channel-btn phone">
                            <i class="fas fa-phone-alt"></i> <?php echo esc_html($phone_num); ?>
                        </a>
                    <?php endif; ?>

                    <?php if (!empty($email_addr)) : ?>
                        <a href="mailto:<?php echo esc_attr($email_addr); ?>?subject=<?php echo esc_attr(rawurlencode('Inquiry: ' . $title)); ?>" class="ctc-channel-btn email">
                            <i class="fas fa-envelope"></i> <?php esc_html_e('Direct Email', 'caretochina-medical'); ?>
                        </a>
                    <?php endif; ?>
                </div>

            </aside>

        </main>

    </div>

    <style>
        .ctc-treatment-single-page {
            width: 100%;
            background-color: #f8fafc;
            min-height: 100vh;
            padding-bottom: 80px;
            overflow-x: hidden;
            box-sizing: border-box;
        }
        .ctc-treat-single-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
            box-sizing: border-box;
            width: 100%;
        }
        .ctc-treat-single-hero {
            background: linear-gradient(135deg, #0f172a 0%, #134e4a 100%);
            padding: 44px 0 54px 0;
            color: #ffffff;
            margin-bottom: 36px;
            width: 100%;
            box-sizing: border-box;
        }
        .ctc-treat-breadcrumbs {
            font-family: 'Inter', sans-serif;
            font-size: 0.85rem;
            color: #94a3b8;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            line-height: 1.4;
        }
        .ctc-treat-breadcrumbs a {
            color: #cbd5e1;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        .ctc-treat-breadcrumbs a:hover {
            color: #2dd4bf;
        }
        .ctc-treat-breadcrumbs .current {
            color: #2dd4bf;
            font-weight: 600;
        }
        .ctc-treat-hero-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }
        .ctc-badge-cat-hero {
            background: rgba(45, 212, 191, 0.2);
            color: #2dd4bf;
            border: 1px solid rgba(45, 212, 191, 0.4);
            padding: 5px 14px;
            border-radius: 40px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        .ctc-badge-stay-hero {
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
            padding: 5px 14px;
            border-radius: 40px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        .ctc-badge-rating-hero {
            color: #fbbf24;
            font-weight: 700;
            font-size: 0.82rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }
        .ctc-treat-single-title {
            font-family: 'Manrope', 'Inter', -apple-system, sans-serif;
            font-size: 2.3rem;
            font-weight: 800;
            color: #ffffff;
            margin: 0 0 10px 0;
            line-height: 1.25;
            word-break: break-word;
        }
        .ctc-treat-single-subtitle {
            font-family: 'Inter', -apple-system, sans-serif;
            font-size: 1.05rem;
            color: #cbd5e1;
            margin: 0;
            max-width: 800px;
            line-height: 1.6;
            word-break: break-word;
        }

        .ctc-treat-layout-wrap {
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 32px;
            box-sizing: border-box;
            width: 100%;
        }
        .ctc-treat-main-content {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            padding: 32px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            box-sizing: border-box;
            width: 100%;
            overflow: hidden;
        }
        .ctc-treat-main-img-box {
            width: 100%;
            height: 380px;
            max-height: 420px;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 28px;
            background-color: #f1f5f9;
            position: relative;
        }
        .ctc-treat-main-img {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            object-position: center center !important;
            display: block !important;
            border-radius: 12px !important;
        }

        .ctc-treat-highlights-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 1px solid #f1f5f9;
            box-sizing: border-box;
            width: 100%;
        }
        .ctc-treat-hl-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-sizing: border-box;
        }
        .ctc-treat-hl-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: #ccfbf1;
            color: #0f766e;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            flex-shrink: 0;
        }
        .ctc-treat-hl-info {
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .ctc-treat-hl-info .lbl {
            font-size: 11px;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .ctc-treat-hl-info .val {
            font-size: 1rem;
            color: #0f172a;
            font-weight: 700;
            font-family: 'Manrope', sans-serif;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .ctc-treat-sec-heading {
            font-family: 'Manrope', sans-serif;
            font-size: 1.35rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 18px 0;
            line-height: 1.3;
        }
        .ctc-treat-prose {
            font-family: 'Inter', sans-serif;
            color: #334155;
            font-size: 0.98rem;
            line-height: 1.75;
            word-break: break-word;
        }
        .ctc-treat-prose p {
            margin-bottom: 16px;
        }
        .ctc-treat-prose img {
            max-width: 100% !important;
            height: auto !important;
            border-radius: 10px;
        }
        .ctc-treat-prose iframe, .ctc-treat-prose video {
            max-width: 100% !important;
        }

        .ctc-treat-cta-banner {
            background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%);
            border-radius: 14px;
            padding: 26px 28px;
            margin-top: 36px;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            box-sizing: border-box;
            width: 100%;
        }
        .ctc-treat-cta-text h3 {
            color: #ffffff;
            font-size: 1.2rem;
            font-weight: 700;
            margin: 0 0 8px 0;
            font-family: 'Manrope', sans-serif;
            line-height: 1.35;
        }
        .ctc-treat-cta-text p {
            color: #ccfbf1;
            font-size: 0.9rem;
            margin: 0;
            line-height: 1.5;
        }
        .ctc-btn-hero-book {
            background: #ffffff;
            color: #0f766e;
            font-family: 'Manrope', sans-serif;
            font-weight: 700;
            padding: 12px 24px;
            border-radius: 50px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            transition: all 0.25s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            font-size: 0.92rem;
            flex-shrink: 0;
        }
        .ctc-btn-hero-book:hover {
            background: #0f172a;
            color: #ffffff;
            transform: translateY(-2px);
        }

        /* Sidebar Styling */
        .ctc-treat-sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
            width: 100%;
            box-sizing: border-box;
        }
        .ctc-treat-side-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            box-sizing: border-box;
            width: 100%;
        }
        .ctc-treat-side-title {
            font-family: 'Manrope', sans-serif;
            font-size: 1.15rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 16px 0;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 10px;
        }
        .ctc-treat-side-price-box {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 12px;
            padding: 14px;
            margin-bottom: 18px;
            text-align: center;
        }
        .ctc-treat-side-price-box .lbl {
            font-size: 11px;
            color: #166534;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
        }
        .ctc-treat-side-price-box .price {
            font-family: 'Manrope', sans-serif;
            font-size: 1.5rem;
            font-weight: 800;
            color: #0f766e;
            display: block;
            margin-top: 4px;
            word-break: break-word;
        }
        .ctc-treat-side-list {
            list-style: none;
            padding: 0;
            margin: 0 0 20px 0;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .ctc-treat-side-list li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 0.9rem;
            color: #475569;
            line-height: 1.4;
        }
        .ctc-treat-side-list li i {
            color: #0f766e;
            font-size: 14px;
            margin-top: 3px;
            flex-shrink: 0;
        }
        .ctc-treat-side-list li strong {
            color: #0f172a;
            margin-right: 4px;
        }
        .ctc-treat-side-btn {
            background: #0f766e;
            color: #ffffff;
            font-family: 'Manrope', sans-serif;
            font-weight: 700;
            font-size: 0.92rem;
            padding: 13px 20px;
            border-radius: 50px;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            box-sizing: border-box;
            transition: all 0.25s ease;
        }
        .ctc-treat-side-btn:hover {
            background: #115e59;
            color: #ffffff;
            box-shadow: 0 6px 16px rgba(15, 118, 110, 0.3);
            transform: translateY(-2px);
        }

        .ctc-concierge-title {
            font-family: 'Manrope', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 6px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .ctc-concierge-sub {
            font-size: 0.84rem;
            color: #64748b;
            margin: 0 0 14px 0;
            line-height: 1.4;
        }
        .ctc-channel-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 14px;
            border-radius: 10px;
            font-size: 0.88rem;
            font-weight: 600;
            text-decoration: none;
            margin-bottom: 8px;
            transition: all 0.2s ease;
            box-sizing: border-box;
            width: 100%;
        }
        .ctc-channel-btn.whatsapp {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }
        .ctc-channel-btn.whatsapp:hover {
            background: #25D366;
            color: #ffffff;
            border-color: #25D366;
        }
        .ctc-channel-btn.phone {
            background: #f0f9ff;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }
        .ctc-channel-btn.phone:hover {
            background: #0ea5e9;
            color: #ffffff;
            border-color: #0ea5e9;
        }
        .ctc-channel-btn.email {
            background: #fdf4ff;
            color: #7e22ce;
            border: 1px solid #f5d0fe;
        }
        .ctc-channel-btn.email:hover {
            background: #a855f7;
            color: #ffffff;
            border-color: #a855f7;
        }

        /* Dark Theme Support */
        html.dark-theme .ctc-treatment-single-page, body.dark-theme .ctc-treatment-single-page {
            background-color: #0a0e1a !important;
        }
        html.dark-theme .ctc-treat-main-content, body.dark-theme .ctc-treat-main-content,
        html.dark-theme .ctc-treat-side-card, body.dark-theme .ctc-treat-side-card {
            background-color: #172033 !important;
            border-color: #28354e !important;
            box-shadow: 0 4px 20px rgba(0,0,0,0.4) !important;
        }
        html.dark-theme .ctc-treat-sec-heading, body.dark-theme .ctc-treat-sec-heading,
        html.dark-theme .ctc-treat-side-title, body.dark-theme .ctc-treat-side-title,
        html.dark-theme .ctc-concierge-title, body.dark-theme .ctc-concierge-title {
            color: #f8fafc !important;
        }
        html.dark-theme .ctc-treat-hl-card, body.dark-theme .ctc-treat-hl-card {
            background-color: #101726 !important;
            border-color: #28354e !important;
        }
        html.dark-theme .ctc-treat-hl-info .val, body.dark-theme .ctc-treat-hl-info .val {
            color: #f8fafc !important;
        }
        html.dark-theme .ctc-treat-prose, body.dark-theme .ctc-treat-prose {
            color: #cbd5e1 !important;
        }
        html.dark-theme .ctc-treat-side-list li, body.dark-theme .ctc-treat-side-list li {
            color: #94a3b8 !important;
        }
        html.dark-theme .ctc-treat-side-list li strong, body.dark-theme .ctc-treat-side-list li strong {
            color: #f8fafc !important;
        }
        html.dark-theme .ctc-treat-side-price-box, body.dark-theme .ctc-treat-side-price-box {
            background-color: #101726 !important;
            border-color: #0f766e !important;
        }

        /* Responsive Breakpoints - Tablets & Small Desktops (max 1024px) */
        @media (max-width: 1024px) {
            .ctc-treat-layout-wrap {
                grid-template-columns: 1fr 320px;
                gap: 24px;
            }
            .ctc-treat-main-content {
                padding: 26px;
            }
            .ctc-treat-single-title {
                font-size: 2rem;
            }
            .ctc-treat-main-img-box {
                height: 320px;
            }
        }

        /* Responsive Breakpoints - Tablet Portrait & Mobile Landscape (max 991px) */
        @media (max-width: 991px) {
            .ctc-treat-single-container {
                padding: 0 20px;
            }
            .ctc-treat-layout-wrap {
                grid-template-columns: 1fr;
                gap: 24px;
            }
            .ctc-treat-single-hero {
                padding: 36px 0 44px 0;
                margin-bottom: 28px;
            }
            .ctc-treat-single-title {
                font-size: 1.8rem;
            }
            .ctc-treat-single-subtitle {
                font-size: 0.98rem;
            }
            .ctc-treat-main-content {
                padding: 24px;
            }
            .ctc-treat-main-img-box {
                height: 280px;
                margin-bottom: 22px;
            }
            .ctc-treat-highlights-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 12px;
                margin-bottom: 26px;
            }
            .ctc-treat-cta-banner {
                flex-direction: column;
                text-align: center;
                padding: 24px 20px;
            }
            .ctc-treat-cta-actions {
                width: 100%;
            }
            .ctc-btn-hero-book {
                width: 100%;
                justify-content: center;
                box-sizing: border-box;
            }
        }

        /* Responsive Breakpoints - Mobile Landscape (max 767px) */
        @media (max-width: 767px) {
            .ctc-treat-single-container {
                padding: 0 16px;
            }
            .ctc-treat-single-hero {
                padding: 30px 0 38px 0;
                margin-bottom: 22px;
            }
            .ctc-treat-single-title {
                font-size: 1.55rem;
                line-height: 1.25;
            }
            .ctc-treat-single-subtitle {
                font-size: 0.92rem;
            }
            .ctc-treat-main-content {
                padding: 20px 16px;
            }
            .ctc-treat-main-img-box {
                height: 230px;
                margin-bottom: 20px;
            }
            .ctc-treat-highlights-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
                margin-bottom: 22px;
                padding-bottom: 18px;
            }
            .ctc-treat-hl-card {
                padding: 10px 12px;
            }
            .ctc-treat-hl-icon {
                width: 36px;
                height: 36px;
                font-size: 15px;
            }
            .ctc-treat-hl-info .val {
                font-size: 0.92rem;
            }
            .ctc-treat-sec-heading {
                font-size: 1.2rem;
                margin-bottom: 14px;
            }
            .ctc-treat-prose {
                font-size: 0.92rem;
                line-height: 1.65;
            }
            .ctc-treat-side-card {
                padding: 20px 16px;
            }
            .ctc-treat-side-price-box .price {
                font-size: 1.35rem;
            }
        }

        /* Responsive Breakpoints - Mobile Portrait (max 480px) */
        @media (max-width: 480px) {
            .ctc-treat-single-container {
                padding: 0 12px;
            }
            .ctc-treat-single-hero {
                padding: 24px 0 30px 0;
                margin-bottom: 18px;
            }
            .ctc-treat-breadcrumbs {
                font-size: 0.76rem;
                gap: 5px;
                margin-bottom: 12px;
            }
            .ctc-treat-hero-meta {
                gap: 6px;
                margin-bottom: 10px;
            }
            .ctc-badge-cat-hero, .ctc-badge-stay-hero {
                padding: 4px 10px;
                font-size: 0.72rem;
            }
            .ctc-treat-single-title {
                font-size: 1.35rem;
                line-height: 1.25;
            }
            .ctc-treat-single-subtitle {
                font-size: 0.86rem;
                line-height: 1.45;
            }
            .ctc-treat-main-img-box {
                height: 190px;
                margin-bottom: 16px;
                border-radius: 10px;
            }
            .ctc-treat-main-img {
                border-radius: 10px !important;
            }
            .ctc-treat-highlights-grid {
                grid-template-columns: 1fr;
                gap: 8px;
                margin-bottom: 20px;
            }
            .ctc-treat-hl-card {
                padding: 10px 12px;
            }
            .ctc-treat-main-content, .ctc-treat-side-card {
                padding: 16px 14px;
                border-radius: 12px;
            }
            .ctc-treat-cta-banner {
                padding: 18px 14px;
                border-radius: 12px;
            }
            .ctc-treat-cta-text h3 {
                font-size: 1.05rem;
            }
            .ctc-treat-cta-text p {
                font-size: 0.82rem;
            }
            .ctc-btn-hero-book {
                font-size: 0.86rem;
                padding: 11px 18px;
            }
            .ctc-treat-side-btn {
                font-size: 0.86rem;
                padding: 11px 16px;
            }
            .ctc-channel-btn {
                font-size: 0.82rem;
                padding: 9px 12px;
            }
        }
    </style>

    <script>
    (function() {
        function handleInquiryAction(e) {
            var btn = e.currentTarget;
            var target = btn.getAttribute('data-trigger-target') || btn.getAttribute('href');
            if (!target || target === '#') return;

            var selector = target.trim();

            // 1. If selector starts with # or .
            if (selector.startsWith('#') || selector.startsWith('.')) {
                var targetEl = document.querySelector(selector);
                if (targetEl) {
                    e.preventDefault();
                    targetEl.click();
                    if (typeof targetEl.scrollIntoView === 'function') {
                        targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    return false;
                }
            }

            // 2. Check if element exists by naked ID or Class
            if (!selector.startsWith('http://') && !selector.startsWith('https://') && !selector.startsWith('/') && !selector.startsWith('tel:') && !selector.startsWith('mailto:')) {
                var fallbackEl = document.getElementById(selector) || document.querySelector('.' + selector);
                if (fallbackEl) {
                    e.preventDefault();
                    fallbackEl.click();
                    if (typeof fallbackEl.scrollIntoView === 'function') {
                        fallbackEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    return false;
                }
            }

            // 3. Otherwise standard URL navigation proceeds naturally
        }

        document.addEventListener('DOMContentLoaded', function() {
            var btns = document.querySelectorAll('.ctc-inquiry-trigger-btn, .ctc-treat-side-btn, .ctc-btn-hero-book');
            for (var i = 0; i < btns.length; i++) {
                btns[i].addEventListener('click', handleInquiryAction);
            }
        });
    })();
    </script>
    <?php
    endwhile;

get_footer();
