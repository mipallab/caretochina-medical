<?php
/**
 * CareToChina Medical Concierge - Dynamic Comparison Matrix Table Shortcode Template
 * Shortcode: [caretochina_comparison_table] or [caretochina_pricing_comparison]
 * 
 * Renders ONLY the comparison table matrix without section heading/subheading.
 *
 * @package CareToChina_Medical
 */

if (!defined('ABSPATH')) {
    exit;
}

$ctc_matrix_packages = class_exists('CareToChina_Packages') ? CareToChina_Packages::instance()->get_active_packages() : [];
$ctc_store_currency = class_exists('CareToChina_Packages') ? CareToChina_Packages::get_store_currency() : 'USD';
$ctc_currency_symbol = class_exists('CareToChina_Packages') ? CareToChina_Packages::get_currency_symbol($ctc_store_currency) : '$';

if (empty($ctc_matrix_packages)) {
    if (current_user_can('manage_options')) {
        echo '<div class="ctc-pricing-scope"><div class="ctc-empty-pricing-notice"><p>' . esc_html__('No active service packages found for comparison table.', 'caretochina-medical') . '</p></div></div>';
    }
    return;
}
?>

<div class="ctc-pricing-compare-wrapper ctc-pricing-scope">
    <div class="p2-compare-system-wrap" style="width:100%;">
        <div class="p2-cmp-box">
            <table class="p2-tbl">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Service & Benefit', 'caretochina-medical'); ?></th>
                        <?php 
                        foreach ($ctc_matrix_packages as $ctc_idx => $ctc_pkg) : 
                            $ctc_plan_tag = class_exists('CareToChina_Pricing_Page') ? CareToChina_Pricing_Page::get_plan_tag($ctc_pkg, $ctc_idx) : 'PLAN ' . chr(65 + $ctc_idx);
                            $ctc_is_hl = ($ctc_idx === 0 || stripos($ctc_plan_tag, 'PLAN A') !== false || stripos((string)$ctc_pkg->badge, 'ultimate') !== false);
                            $ctc_badge_txt = !empty($ctc_pkg->badge) ? $ctc_pkg->badge : $ctc_plan_tag;
                        ?>
                            <th class="<?php echo $ctc_is_hl ? 'p2-hl' : ''; ?>">
                                <span style="font-size:15px; font-weight:800; display:block; <?php echo !$ctc_is_hl ? 'color:var(--ctc-p-primary);' : ''; ?>">
                                    <?php echo esc_html($ctc_plan_tag); ?>
                                </span>
                                <span style="font-size:11px; opacity:0.85; color:var(--ctc-p-text-muted);">
                                    <?php echo esc_html($ctc_badge_txt . ' · ' . $ctc_currency_symbol . number_format($ctc_pkg->price)); ?>
                                </span>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <!-- GROUP 1: MEDICAL COORDINATION -->
                    <tr class="p2-grp"><td colspan="<?php echo esc_attr(count($ctc_matrix_packages) + 1); ?>"><?php esc_html_e('Medical Coordination', 'caretochina-medical'); ?></td></tr>
                    
                    <?php
                    $ctc_med_rows = [
                        'med_report'      => __('Medical report assessment', 'caretochina-medical'),
                        'hosp_recom'      => __('Hospital & specialist recommendations', 'caretochina-medical'),
                        'treatment_plan'  => __('Treatment plan & cost estimate', 'caretochina-medical'),
                        'video_consult'   => __('Pre-departure video consultation', 'caretochina-medical'),
                        'doc_visa'        => __('Medical documents & visa assistance', 'caretochina-medical'),
                        'priority_sched'  => __('Priority consultation scheduling', 'caretochina-medical'),
                        'hospitalization' => __('Hospitalization coordination', 'caretochina-medical'),
                        'post_op'         => __('Post-operative follow-up', 'caretochina-medical'),
                        'vip_fasttrack'   => __('VIP fast-track access', 'caretochina-medical'),
                    ];

                    foreach ($ctc_med_rows as $ctc_field_key => $ctc_row_label) : ?>
                        <tr>
                            <td><?php echo esc_html($ctc_row_label); ?></td>
                            <?php foreach ($ctc_matrix_packages as $ctc_idx => $ctc_pkg) : 
                                $ctc_is_hl = ($ctc_idx === 0 || stripos((string)$ctc_pkg->badge, 'ultimate') !== false);
                                $ctc_val = $ctc_pkg->matrix[$ctc_field_key] ?? '';
                            ?>
                                <td class="<?php echo $ctc_is_hl ? 'p2-hl' : ''; ?>">
                                    <?php echo class_exists('CareToChina_Pricing_Page') ? CareToChina_Pricing_Page::render_matrix_cell($ctc_val, $ctc_is_hl) : esc_html($ctc_val); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>

                    <!-- GROUP 2: TRANSPORT & LANGUAGE -->
                    <tr class="p2-grp"><td colspan="<?php echo esc_attr(count($ctc_matrix_packages) + 1); ?>"><?php esc_html_e('Transport & Language', 'caretochina-medical'); ?></td></tr>
                    
                    <?php
                    $ctc_trans_rows = [
                        'vehicle'          => __('Dedicated vehicle', 'caretochina-medical'),
                        'interpreter'      => __('Bilingual interpreter / device', 'caretochina-medical'),
                        'airport_transfer' => __('Airport transfer support', 'caretochina-medical'),
                    ];

                    foreach ($ctc_trans_rows as $ctc_field_key => $ctc_row_label) : ?>
                        <tr>
                            <td><?php echo esc_html($ctc_row_label); ?></td>
                            <?php foreach ($ctc_matrix_packages as $ctc_idx => $ctc_pkg) : 
                                $ctc_is_hl = ($ctc_idx === 0 || stripos((string)$ctc_pkg->badge, 'ultimate') !== false);
                                $ctc_val = $ctc_pkg->matrix[$ctc_field_key] ?? '';
                            ?>
                                <td class="<?php echo $ctc_is_hl ? 'p2-hl' : ''; ?>">
                                    <?php echo class_exists('CareToChina_Pricing_Page') ? CareToChina_Pricing_Page::render_matrix_cell($ctc_val, $ctc_is_hl) : esc_html($ctc_val); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>

                    <!-- GROUP 3: ACCOMMODATION & DINING -->
                    <tr class="p2-grp"><td colspan="<?php echo esc_attr(count($ctc_matrix_packages) + 1); ?>"><?php esc_html_e('Accommodation & Dining', 'caretochina-medical'); ?></td></tr>
                    
                    <?php
                    $ctc_stay_rows = [
                        'accommodation' => __('Accommodation', 'caretochina-medical'),
                        'dining'        => __('Dining', 'caretochina-medical'),
                    ];

                    foreach ($ctc_stay_rows as $ctc_field_key => $ctc_row_label) : ?>
                        <tr>
                            <td><?php echo esc_html($ctc_row_label); ?></td>
                            <?php foreach ($ctc_matrix_packages as $ctc_idx => $ctc_pkg) : 
                                $ctc_is_hl = ($ctc_idx === 0 || stripos((string)$ctc_pkg->badge, 'ultimate') !== false);
                                $ctc_val = $ctc_pkg->matrix[$ctc_field_key] ?? '';
                            ?>
                                <td class="<?php echo $ctc_is_hl ? 'p2-hl' : ''; ?>">
                                    <?php echo class_exists('CareToChina_Pricing_Page') ? CareToChina_Pricing_Page::render_matrix_cell($ctc_val, $ctc_is_hl) : esc_html($ctc_val); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>

                    <!-- GROUP 4: COMPANION & LEISURE -->
                    <tr class="p2-grp"><td colspan="<?php echo esc_attr(count($ctc_matrix_packages) + 1); ?>"><?php esc_html_e('Companion & Leisure', 'caretochina-medical'); ?></td></tr>
                    
                    <?php
                    $ctc_comp_rows = [
                        'companion' => __('Patient companion benefits', 'caretochina-medical'),
                        'travel'    => __('Travel & leisure', 'caretochina-medical'),
                    ];

                    foreach ($ctc_comp_rows as $ctc_field_key => $ctc_row_label) : ?>
                        <tr>
                            <td><?php echo esc_html($ctc_row_label); ?></td>
                            <?php foreach ($ctc_matrix_packages as $ctc_idx => $ctc_pkg) : 
                                $ctc_is_hl = ($ctc_idx === 0 || stripos((string)$ctc_pkg->badge, 'ultimate') !== false);
                                $ctc_val = $ctc_pkg->matrix[$ctc_field_key] ?? '';
                            ?>
                                <td class="<?php echo $ctc_is_hl ? 'p2-hl' : ''; ?>">
                                    <?php echo class_exists('CareToChina_Pricing_Page') ? CareToChina_Pricing_Page::render_matrix_cell($ctc_val, $ctc_is_hl) : esc_html($ctc_val); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>

                    <!-- GROUP 5: ADDITIONAL SUPPORT -->
                    <tr class="p2-grp"><td colspan="<?php echo esc_attr(count($ctc_matrix_packages) + 1); ?>"><?php esc_html_e('Additional Support', 'caretochina-medical'); ?></td></tr>
                    
                    <?php
                    $ctc_supp_rows = [
                        'support_247'  => __('24/7 service support', 'caretochina-medical'),
                        'connectivity' => __('International connectivity', 'caretochina-medical'),
                    ];

                    foreach ($ctc_supp_rows as $ctc_field_key => $ctc_row_label) : ?>
                        <tr>
                            <td><?php echo esc_html($ctc_row_label); ?></td>
                            <?php foreach ($ctc_matrix_packages as $ctc_idx => $ctc_pkg) : 
                                $ctc_is_hl = ($ctc_idx === 0 || stripos((string)$ctc_pkg->badge, 'ultimate') !== false);
                                $ctc_val = $ctc_pkg->matrix[$ctc_field_key] ?? '';
                            ?>
                                <td class="<?php echo $ctc_is_hl ? 'p2-hl' : ''; ?>">
                                    <?php echo class_exists('CareToChina_Pricing_Page') ? CareToChina_Pricing_Page::render_matrix_cell($ctc_val, $ctc_is_hl) : esc_html($ctc_val); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
