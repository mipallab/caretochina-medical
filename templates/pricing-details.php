<?php
/**
 * CareToChina Medical Concierge - Full Package Details (Bento Grid Tabs) Shortcode Template
 * Shortcode: [caretochina_package_details] or [caretochina_bento_details]
 * 
 * Renders ONLY the Bento tab buttons and full package details tab panes without section heading/subheading.
 *
 * @package CareToChina_Medical
 */

if (!defined('ABSPATH')) {
    exit;
}

$ctc_packages = class_exists('CareToChina_Packages') ? CareToChina_Packages::instance()->get_active_packages() : [];
$ctc_store_currency = class_exists('CareToChina_Packages') ? CareToChina_Packages::get_store_currency() : 'USD';
$ctc_currency_symbol = class_exists('CareToChina_Packages') ? CareToChina_Packages::get_currency_symbol($ctc_store_currency) : '$';
$ctc_global_service_notes = class_exists('CareToChina_Packages') ? CareToChina_Packages::get_global_service_notes() : '';

if (empty($ctc_packages)) {
    if (current_user_can('manage_options')) {
        echo '<div class="ctc-pricing-scope"><div class="ctc-empty-pricing-notice"><p>' . esc_html__('No active service packages found for details view.', 'caretochina-medical') . '</p></div></div>';
    }
    return;
}
?>

<div class="ctc-pricing-details-wrapper ctc-pricing-scope" id="details">
    <div class="details-section" style="padding:0; margin:0 auto; width:100%;">
        
        <!-- TAB PILL BUTTONS -->
        <div class="p2-tab-btn-bar" role="tablist">
            <?php foreach ($ctc_packages as $ctc_idx => $ctc_pkg) : 
                $ctc_plan_tag = class_exists('CareToChina_Pricing_Page') ? CareToChina_Pricing_Page::get_plan_tag($ctc_pkg, $ctc_idx) : 'PLAN ' . chr(65 + $ctc_idx);
                $ctc_clean_title = class_exists('CareToChina_Pricing_Page') ? CareToChina_Pricing_Page::get_clean_title($ctc_pkg->title) : $ctc_pkg->title;
                $ctc_tab_id = 'tab-' . $ctc_pkg->id;
                $ctc_is_first = ($ctc_idx === 0);
                $ctc_btn_label = $ctc_plan_tag . ' · ' . $ctc_currency_symbol . number_format($ctc_pkg->price) . ' (' . (!empty($ctc_pkg->badge) ? $ctc_pkg->badge : $ctc_clean_title) . ')';
            ?>
                <button type="button" 
                        id="btn_<?php echo esc_attr($ctc_tab_id); ?>" 
                        class="p2-tab-pill-btn <?php echo $ctc_is_first ? 'p2-active-tab' : ''; ?>" 
                        data-tab-target="<?php echo esc_attr($ctc_tab_id); ?>"
                        role="tab"
                        aria-selected="<?php echo $ctc_is_first ? 'true' : 'false'; ?>"
                        aria-controls="pane_<?php echo esc_attr($ctc_tab_id); ?>"
                        onclick="if(window.p2SwitchTab) window.p2SwitchTab('<?php echo esc_js($ctc_tab_id); ?>');">
                    <?php echo esc_html($ctc_btn_label); ?>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- TAB PANES -->
        <?php foreach ($ctc_packages as $ctc_idx => $ctc_pkg) : 
            $ctc_tab_id = 'tab-' . $ctc_pkg->id;
            $ctc_is_first = ($ctc_idx === 0);
            $ctc_plan_tag = class_exists('CareToChina_Pricing_Page') ? CareToChina_Pricing_Page::get_plan_tag($ctc_pkg, $ctc_idx) : 'PLAN ' . chr(65 + $ctc_idx);
            $ctc_clean_title = class_exists('CareToChina_Pricing_Page') ? CareToChina_Pricing_Page::get_clean_title($ctc_pkg->title) : $ctc_pkg->title;

            // Parse coordination bullet points into clean items
            $ctc_coordination_items = [];
            if (!empty($ctc_pkg->coordination)) {
                $ctc_lines = explode("\n", $ctc_pkg->coordination);
                foreach ($ctc_lines as $ctc_line) {
                    $ctc_clean = trim(preg_replace('/^[•\-\*\d\.\s]+/u', '', $ctc_line));
                    if (!empty($ctc_clean)) {
                        $ctc_coordination_items[] = $ctc_clean;
                    }
                }
            }
            $ctc_coordination_count = count($ctc_coordination_items);

            // Global service notes parsing
            $ctc_notes_lines = [];
            if (!empty($ctc_global_service_notes)) {
                $ctc_n_lines = explode("\n", $ctc_global_service_notes);
                foreach ($ctc_n_lines as $ctc_nl) {
                    $ctc_nl_clean = trim($ctc_nl);
                    if (!empty($ctc_nl_clean)) {
                        $ctc_notes_lines[] = $ctc_nl_clean;
                    }
                }
            }
        ?>
            <div id="pane_<?php echo esc_attr($ctc_tab_id); ?>" 
                 class="p2-tab-pane <?php echo $ctc_is_first ? 'active-pane' : ''; ?>" 
                 data-tab-id="<?php echo esc_attr($ctc_tab_id); ?>"
                 role="tabpanel"
                 aria-labelledby="btn_<?php echo esc_attr($ctc_tab_id); ?>"
                 style="<?php echo $ctc_is_first ? 'display:block;' : 'display:none;'; ?>">

                <!-- Bento Hero Banner -->
                <div class="bento-hero-card">
                    <div class="bento-hero-info">
                        <span class="bento-badge"><?php echo esc_html('CaretoChina · ' . $ctc_plan_tag); ?></span>
                        <h3 class="bento-hero-title"><?php echo esc_html($ctc_pkg->title); ?></h3>
                        <p class="bento-hero-pos"><?php echo esc_html(!empty($ctc_pkg->positioning) ? $ctc_pkg->positioning : $ctc_clean_title); ?></p>
                    </div>
                    <div class="bento-hero-action">
                        <div class="bento-hero-price-wrap">
                            <span class="bento-hero-price"><?php echo esc_html($ctc_currency_symbol . number_format($ctc_pkg->price)); ?></span>
                            <span class="bento-hero-pricesub"><?php echo esc_html(!empty($ctc_pkg->timeline) ? $ctc_pkg->timeline : __('Full Concierge Escort', 'caretochina-medical')); ?></span>
                        </div>
                        <a href="#booking" class="bento-hero-book-btn" data-package-id="<?php echo esc_attr($ctc_pkg->id); ?>">
                            <i class="fas fa-calendar-check"></i> <?php esc_html_e('Book This Package', 'caretochina-medical'); ?>
                        </a>
                    </div>
                </div>

                <!-- Bento Grid Layout -->
                <div class="bento-grid-layout">
                    
                    <!-- 1. Medical Coordination Box (Full Width) -->
                    <?php if (!empty($ctc_coordination_items)) : ?>
                        <div class="bento-box bento-span-full">
                            <div class="bento-header">
                                <span>🏥</span>
                                <h4><?php esc_html_e('Medical Coordination', 'caretochina-medical'); ?></h4>
                                <span class="bento-count-badge">
                                    <?php 
                                    /* translators: %d: count of included services */
                                    echo esc_html(sprintf(__('%d Services Included', 'caretochina-medical'), $ctc_coordination_count)); 
                                    ?>
                                </span>
                            </div>
                            <div class="bento-med-grid">
                                <?php foreach ($ctc_coordination_items as $ctc_c_item) : 
                                    $ctc_has_colon = (strpos($ctc_c_item, ':') !== false);
                                ?>
                                    <div class="bento-med-item">
                                        <span class="p2-chk">✓</span>
                                        <div>
                                            <?php if ($ctc_has_colon) : 
                                                $ctc_parts = explode(':', $ctc_c_item, 2);
                                            ?>
                                                <strong><?php echo esc_html(trim($ctc_parts[0])); ?>:</strong> <?php echo esc_html(trim($ctc_parts[1])); ?>
                                            <?php else : ?>
                                                <?php echo esc_html($ctc_c_item); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- 2. Transport & Language Box -->
                    <div class="bento-box">
                        <div class="bento-header">
                            <span>🚗</span>
                            <h4><?php esc_html_e('Transport & Language Support', 'caretochina-medical'); ?></h4>
                        </div>
                        <div style="display:flex; flex-direction:column; gap:12px;">
                            <div class="bento-sub-item">
                                <span>🚗</span>
                                <div>
                                    <div class="bento-sub-lbl"><?php esc_html_e('Dedicated Vehicle', 'caretochina-medical'); ?></div>
                                    <div class="bento-text"><?php echo esc_html(!empty($ctc_pkg->vehicle) ? $ctc_pkg->vehicle : __('Business vehicle with driver', 'caretochina-medical')); ?></div>
                                </div>
                            </div>
                            <div class="bento-sub-item">
                                <span>🗣️</span>
                                <div>
                                    <div class="bento-sub-lbl"><?php esc_html_e('Dedicated Interpreter / Transfer', 'caretochina-medical'); ?></div>
                                    <div class="bento-text"><?php echo esc_html(!empty($ctc_pkg->interpreter) ? $ctc_pkg->interpreter : __('Driver + bilingual translation device for airport transfers', 'caretochina-medical')); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Accommodation & Dining Box -->
                    <div class="bento-box">
                        <div class="bento-header">
                            <span>🏨</span>
                            <h4><?php esc_html_e('Accommodation & Dining', 'caretochina-medical'); ?></h4>
                        </div>
                        <div style="display:flex; flex-direction:column; gap:12px;">
                            <?php 
                            $ctc_has_acc = (!empty($ctc_pkg->accommodation) && stripos($ctc_pkg->accommodation, 'No accommodation') === false);
                            $ctc_has_din = (!empty($ctc_pkg->dining) && stripos($ctc_pkg->dining, 'No dining') === false);
                            ?>
                            <div class="bento-sub-item" style="<?php echo !$ctc_has_acc ? 'opacity:0.6;' : ''; ?>">
                                <span>🏨</span>
                                <div>
                                    <div class="bento-sub-lbl" style="<?php echo !$ctc_has_acc ? 'color:var(--ctc-p-text-muted);' : ''; ?>"><?php esc_html_e('Accommodation', 'caretochina-medical'); ?></div>
                                    <div class="bento-text" style="<?php echo !$ctc_has_acc ? 'color:var(--ctc-p-text-muted); font-style:italic;' : ''; ?>">
                                        <?php echo esc_html(!empty($ctc_pkg->accommodation) ? $ctc_pkg->accommodation : __('No accommodation service', 'caretochina-medical')); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="bento-sub-item" style="<?php echo !$ctc_has_din ? 'opacity:0.6;' : ''; ?>">
                                <span>🍽️</span>
                                <div>
                                    <div class="bento-sub-lbl" style="<?php echo !$ctc_has_din ? 'color:var(--ctc-p-text-muted);' : ''; ?>"><?php esc_html_e('Dining', 'caretochina-medical'); ?></div>
                                    <div class="bento-text" style="<?php echo !$ctc_has_din ? 'color:var(--ctc-p-text-muted); font-style:italic;' : ''; ?>">
                                        <?php echo esc_html(!empty($ctc_pkg->dining) ? $ctc_pkg->dining : __('No dining service', 'caretochina-medical')); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Patient Companion Benefits Box -->
                    <div class="bento-box">
                        <div class="bento-header">
                            <span>👥</span>
                            <h4><?php esc_html_e('Patient Companion Benefits', 'caretochina-medical'); ?></h4>
                        </div>
                        <p class="bento-text" style="<?php echo empty($ctc_pkg->companion) ? 'color:var(--ctc-p-text-muted); font-style:italic;' : ''; ?>">
                            <?php echo esc_html(!empty($ctc_pkg->companion) ? $ctc_pkg->companion : __('No companion add-ons included in this tier.', 'caretochina-medical')); ?>
                        </p>
                    </div>

                    <!-- 5. Exclusive Travel & Leisure Box -->
                    <div class="bento-box">
                        <div class="bento-header">
                            <span>✨</span>
                            <h4><?php esc_html_e('Exclusive Travel & Leisure', 'caretochina-medical'); ?></h4>
                        </div>
                        <p class="bento-text" style="<?php echo empty($ctc_pkg->travel) || stripos($ctc_pkg->travel, 'No travel') !== false ? 'color:var(--ctc-p-text-muted); font-style:italic;' : ''; ?>">
                            <?php echo esc_html(!empty($ctc_pkg->travel) ? $ctc_pkg->travel : __('No travel & leisure add-ons included.', 'caretochina-medical')); ?>
                        </p>
                    </div>

                    <!-- 6. Service Notes & Policies Box (Full Width) -->
                    <?php if (!empty($ctc_notes_lines)) : ?>
                        <div class="bento-box bento-span-full bento-notes-card">
                            <div class="bento-header">
                                <span>📋</span>
                                <h4><?php esc_html_e('Service Notes & Official Policies', 'caretochina-medical'); ?></h4>
                            </div>
                            <div style="display:flex; flex-direction:column; gap:8px;">
                                <?php foreach ($ctc_notes_lines as $ctc_n_idx => $ctc_note_line) : 
                                    $ctc_note_num = $ctc_n_idx + 1;
                                    if (preg_match('/^(\d+)[\.\)]\s*(.*)/', $ctc_note_line, $ctc_n_match)) {
                                        $ctc_note_num = $ctc_n_match[1];
                                        $ctc_note_content = $ctc_n_match[2];
                                    } else {
                                        $ctc_note_content = $ctc_note_line;
                                    }
                                    $ctc_has_colon = (strpos($ctc_note_content, ':') !== false);
                                ?>
                                    <div class="bento-note-item">
                                        <span class="bento-note-num"><?php echo esc_html($ctc_note_num); ?></span>
                                        <div>
                                            <?php if ($ctc_has_colon) : 
                                                $ctc_n_parts = explode(':', $ctc_note_content, 2);
                                            ?>
                                                <strong><?php echo esc_html(trim($ctc_n_parts[0])); ?>:</strong> <?php echo esc_html(trim($ctc_n_parts[1])); ?>
                                            <?php else : ?>
                                                <?php echo esc_html($ctc_note_content); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
