<?php
/**
 * CareToChina Medical Concierge - Dynamic Pricing Cards Shortcode Template
 * Shortcode: [caretochina_pricing_cards]
 *
 * @package CareToChina_Medical
 */

if (!defined('ABSPATH')) {
    exit;
}

$ctc_packages = class_exists('CareToChina_Packages') ? CareToChina_Packages::instance()->get_active_packages() : [];
$ctc_store_currency = class_exists('CareToChina_Packages') ? CareToChina_Packages::get_store_currency() : 'USD';
$ctc_currency_symbol = class_exists('CareToChina_Packages') ? CareToChina_Packages::get_currency_symbol($ctc_store_currency) : '$';

if (empty($ctc_packages)) {
    if (current_user_can('manage_options')) {
        echo '<div class="ctc-pricing-scope"><div class="ctc-empty-pricing-notice"><p>' . esc_html__('No active service packages found. Please configure them in Hospitals -> Service Packages.', 'caretochina-medical') . '</p></div></div>';
    }
    return;
}

// Display packages sorted (Plan D to Plan A, matching 4-tier cards layout)
$ctc_display_cards = $ctc_packages;
if (count($ctc_display_cards) === 4) {
    usort($ctc_display_cards, function($a, $b) {
        return ($b->order ?? 0) - ($a->order ?? 0);
    });
}
?>

<div class="ctc-pricing-cards-wrapper ctc-pricing-scope">
    <div class="pricing-cards-section" style="padding:0; margin:0 auto; width:100%;">
        <div class="pricing-grid">
            <?php 
            foreach ($ctc_display_cards as $ctc_idx => $ctc_pkg) : 
                $ctc_plan_tag = class_exists('CareToChina_Pricing_Page') ? CareToChina_Pricing_Page::get_plan_tag($ctc_pkg, $ctc_idx) : 'PLAN ' . chr(65 + $ctc_idx);
                $ctc_clean_title = class_exists('CareToChina_Pricing_Page') ? CareToChina_Pricing_Page::get_clean_title($ctc_pkg->title) : $ctc_pkg->title;
                $ctc_badge = trim((string)$ctc_pkg->badge);
                $ctc_is_ultimate = (stripos($ctc_badge, 'ultimate') !== false || stripos($ctc_plan_tag, 'PLAN A') !== false || ($ctc_pkg->order ?? 0) === 1);
                $ctc_is_popular = (stripos($ctc_badge, 'popular') !== false || stripos($ctc_plan_tag, 'PLAN B') !== false);
                $ctc_is_highlighted = $ctc_is_ultimate;
                $ctc_tab_target = 'tab-' . $ctc_pkg->id;

                // Extract feature highlights dynamically
                $ctc_feature_lines = [];
                if (!empty($ctc_pkg->coordination)) {
                    $ctc_raw_lines = explode("\n", $ctc_pkg->coordination);
                    foreach ($ctc_raw_lines as $ctc_l) {
                        $ctc_clean_l = trim(preg_replace('/^[•\-\*\d\.\s]+/u', '', $ctc_l));
                        if (!empty($ctc_clean_l)) {
                            if (strpos($ctc_clean_l, ':') !== false) {
                                $ctc_parts = explode(':', $ctc_clean_l);
                                $ctc_feature_lines[] = trim($ctc_parts[0]);
                            } else {
                                $ctc_feature_lines[] = $ctc_clean_l;
                            }
                        }
                        if (count($ctc_feature_lines) >= 3) break;
                    }
                }

                if (!empty($ctc_pkg->vehicle)) {
                    $ctc_feature_lines[] = $ctc_pkg->vehicle;
                }
                if (!empty($ctc_pkg->interpreter) && count($ctc_feature_lines) < 5) {
                    $ctc_feature_lines[] = $ctc_pkg->interpreter;
                }
                if (!empty($ctc_pkg->accommodation) && stripos($ctc_pkg->accommodation, 'No accommodation') === false && count($ctc_feature_lines) < 5) {
                    $ctc_feature_lines[] = $ctc_pkg->accommodation;
                }
                $ctc_feature_lines = array_slice($ctc_feature_lines, 0, 5);
            ?>
                <div class="pricing-card <?php echo $ctc_is_highlighted ? 'highlighted' : ''; ?>">
                    <div>
                        <?php if (!empty($ctc_badge) || $ctc_is_ultimate || $ctc_is_popular) : ?>
                            <div class="card-badge-container">
                                <?php if ($ctc_is_ultimate) : ?>
                                    <span class="ultimate-tag"><?php echo esc_html(!empty($ctc_badge) ? $ctc_badge : 'ULTIMATE'); ?></span>
                                <?php elseif ($ctc_is_popular) : ?>
                                    <span class="popular-tag"><?php echo esc_html(!empty($ctc_badge) ? $ctc_badge : 'POPULAR'); ?></span>
                                <?php else : ?>
                                    <span class="popular-tag" style="background:var(--ctc-p-primary-subtle); color:var(--ctc-p-primary);"><?php echo esc_html($ctc_badge); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="plan-tag"><?php echo esc_html($ctc_plan_tag); ?></div>
                        <h3 class="plan-title"><?php echo esc_html($ctc_clean_title); ?></h3>

                        <div class="plan-price">
                            <?php echo esc_html($ctc_currency_symbol . number_format($ctc_pkg->price)); ?>
                            <span><?php echo esc_html(!empty($ctc_pkg->timeline) ? $ctc_pkg->timeline : __('service fee', 'caretochina-medical')); ?></span>
                        </div>

                        <p class="plan-desc"><?php echo esc_html(!empty($ctc_pkg->positioning) ? $ctc_pkg->positioning : $ctc_clean_title); ?></p>

                        <?php if (!empty($ctc_feature_lines)) : ?>
                            <ul class="feature-list">
                                <?php foreach ($ctc_feature_lines as $ctc_feature) : ?>
                                    <li>
                                        <i class="fas fa-check-circle"></i>
                                        <span><?php echo esc_html($ctc_feature); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <a href="#details" class="btn-plan" data-target-tab="<?php echo esc_attr($ctc_tab_target); ?>" onclick="if(window.p2SwitchTab) window.p2SwitchTab('<?php echo esc_attr($ctc_tab_target); ?>');">
                        <?php echo $ctc_is_highlighted ? esc_html__('Choose Plan', 'caretochina-medical') : esc_html__('View Plan Details', 'caretochina-medical'); ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
