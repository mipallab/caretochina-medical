<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class CareToChina_Treatments_Grid_Widget extends Widget_Base {

    public function get_name() {
        return 'caretochina_treatments_grid';
    }

    public function get_title() {
        return __('CareToChina Treatments Grid & Search', 'caretochina-medical');
    }

    public function get_icon() {
        return 'eicon-post-grid';
    }

    public function get_categories() {
        return ['general', 'basic'];
    }

    public function get_keywords() {
        return ['treatment', 'medical', 'grid', 'search', 'ajax', 'caretochina-medical', 'card', 'pagination', 'therapy', 'surgery'];
    }

    protected function register_controls() {
        
        // ================= CONTENT TAB: LAYOUT & SEARCH =================
        $this->start_controls_section(
            'section_layout',
            [
                'label' => __('Grid & Search Controls', 'caretochina-medical'),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'show_search',
            [
                'label'        => __('Show Live AJAX Search Bar', 'caretochina-medical'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'search_placeholder',
            [
                'label'       => __('Search Bar Placeholder', 'caretochina-medical'),
                'type'        => Controls_Manager::TEXT,
                'default'     => __('Search treatment by procedure name or keyword...', 'caretochina-medical'),
                'condition'   => ['show_search' => 'yes'],
            ]
        );

        $this->add_control(
            'show_category_tabs',
            [
                'label'        => __('Show Category Filter Tabs', 'caretochina-medical'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
            ]
        );

        $this->add_responsive_control(
            'posts_per_page',
            [
                'label'          => __('Treatments Per Page', 'caretochina-medical'),
                'type'           => Controls_Manager::NUMBER,
                'default'        => 8,
                'tablet_default' => 4,
                'mobile_default' => 2,
                'min'            => 1,
                'max'            => 50,
            ]
        );

        $this->add_control(
            'show_pagination',
            [
                'label'        => __('Enable Pagination', 'caretochina-medical'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
            ]
        );

        $this->add_responsive_control(
            'columns',
            [
                'label'   => __('Columns', 'caretochina-medical'),
                'type'    => Controls_Manager::SELECT,
                'default' => '4',
                'tablet_default' => '2',
                'mobile_default' => '1',
                'options' => [
                    '1' => '1 Column',
                    '2' => '2 Columns',
                    '3' => '3 Columns',
                    '4' => '4 Columns',
                ],
                'selectors' => [
                    '{{WRAPPER}} .ctc-treatments-grid' => 'grid-template-columns: repeat({{VALUE}}, 1fr);',
                ],
            ]
        );

        $this->add_responsive_control(
            'grid_gap',
            [
                'label'      => __('Grid Gap Between Cards (px)', 'caretochina-medical'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'rem'],
                'range'      => [
                    'px' => ['min' => 0, 'max' => 60],
                ],
                'default'    => ['unit' => 'px', 'size' => 20],
                'selectors'  => [
                    '{{WRAPPER}} .ctc-treatments-grid' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'filter_bar_gap',
            [
                'label'      => __('Spacing Between Search & Grid (px)', 'caretochina-medical'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'rem'],
                'range'      => [
                    'px' => ['min' => 0, 'max' => 100],
                ],
                'default'    => ['unit' => 'px', 'size' => 32],
                'selectors'  => [
                    '{{WRAPPER}} .ctc-treat-filter-bar' => 'gap: {{SIZE}}{{UNIT}}; margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'orderby',
            [
                'label'   => __('Order By', 'caretochina-medical'),
                'type'    => Controls_Manager::SELECT,
                'default' => 'date',
                'options' => [
                    'date'  => __('Publish Date', 'caretochina-medical'),
                    'title' => __('Title (Alphabetical)', 'caretochina-medical'),
                    'price' => __('Treatment Price', 'caretochina-medical'),
                    'rand'  => __('Random', 'caretochina-medical'),
                ],
            ]
        );

        $this->add_control(
            'order',
            [
                'label'   => __('Order Direction', 'caretochina-medical'),
                'type'    => Controls_Manager::SELECT,
                'default' => 'DESC',
                'options' => [
                    'DESC' => __('Descending (DESC)', 'caretochina-medical'),
                    'ASC'  => __('Ascending (ASC)', 'caretochina-medical'),
                ],
            ]
        );

        $this->add_control(
            'button_text',
            [
                'label'   => __('Card Button Text', 'caretochina-medical'),
                'type'    => Controls_Manager::TEXT,
                'default' => __('View Treatment', 'caretochina-medical'),
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        
        $ppp_desktop = !empty($settings['posts_per_page']) ? intval($settings['posts_per_page']) : 6;
        $ppp_tablet  = !empty($settings['posts_per_page_tablet']) ? intval($settings['posts_per_page_tablet']) : $ppp_desktop;
        $ppp_mobile  = !empty($settings['posts_per_page_mobile']) ? intval($settings['posts_per_page_mobile']) : 2;

        $posts_count = $ppp_desktop;
        $show_pag    = ($settings['show_pagination'] === 'yes');
        $orderby     = !empty($settings['orderby']) ? $settings['orderby'] : 'date';
        $order       = !empty($settings['order']) ? $settings['order'] : 'DESC';
        $button_text = !empty($settings['button_text']) ? $settings['button_text'] : __('View Treatment', 'caretochina-medical');

        // Fetch Treatment Categories
        $categories = get_terms([
            'taxonomy'   => 'treatment_category',
            'hide_empty' => false,
        ]);

        $paged = get_query_var('paged') ? get_query_var('paged') : (get_query_var('page') ? get_query_var('page') : 1);

        $args = [
            'post_type'      => 'medical_treatment',
            'post_status'    => 'publish',
            'posts_per_page' => $posts_count,
            'paged'          => $paged,
            'order'          => ($order === 'ASC') ? 'ASC' : 'DESC',
        ];

        if ($orderby === 'price') {
            $args['meta_key'] = '_treatment_price';
            $args['orderby']  = 'meta_value_num';
        } elseif ($orderby === 'title') {
            $args['orderby'] = 'title';
        } elseif ($orderby === 'rand') {
            $args['orderby'] = 'rand';
        } else {
            $args['orderby'] = 'date';
        }

        $query = new \WP_Query($args);
        $widget_id = $this->get_id();
        ?>

        <div class="ctc-treatments-section" id="ctc-treatments-<?php echo esc_attr($widget_id); ?>">
            
            <?php if ($settings['show_search'] === 'yes' || $settings['show_category_tabs'] === 'yes') : ?>
                <div class="ctc-treat-filter-bar">
                    
                    <?php if ($settings['show_search'] === 'yes') : ?>
                        <div class="ctc-treat-search-box">
                            <i class="fas fa-search ctc-treat-search-icon"></i>
                            <input type="text" class="ctc-treat-search-input" placeholder="<?php echo esc_attr($settings['search_placeholder']); ?>" aria-label="<?php esc_attr_e('Search medical treatments', 'caretochina-medical'); ?>">
                        </div>
                    <?php endif; ?>

                    <?php if ($settings['show_category_tabs'] === 'yes' && !empty($categories) && !is_wp_error($categories)) : ?>
                        <div class="ctc-treat-cat-tabs">
                            <button type="button" class="ctc-treat-cat-tab active" data-category="all">
                                <i class="fas fa-th-large"></i> <?php esc_html_e('All Treatments', 'caretochina-medical'); ?>
                            </button>
                            <?php foreach ($categories as $cat) : ?>
                                <button type="button" class="ctc-treat-cat-tab" data-category="<?php echo esc_attr($cat->slug); ?>">
                                    <i class="fas fa-stethoscope"></i> <?php echo esc_html($cat->name); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </div>
            <?php endif; ?>

            <!-- Treatment Cards Grid Output -->
            <div class="ctc-treatments-grid">
                <?php if ($query->have_posts()) : 
                    while ($query->have_posts()) : $query->the_post();
                        CareToChina_Treatments_Plugin::render_treatment_card(get_the_ID(), $button_text);
                    endwhile;
                else : ?>
                    <div class="ctc-no-treatments" style="grid-column: 1 / -1; text-align: center; padding: 50px 20px; color: #64748b;">
                        <?php esc_html_e('No medical treatments published yet.', 'caretochina-medical'); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Single Pagination Container -->
            <?php if ($show_pag) : ?>
                <div class="ctc-treat-pagination-box" id="ctc-treat-pagination-<?php echo esc_attr($widget_id); ?>">
                    <?php if ($query->max_num_pages > 1) : ?>
                        <?php for ($i = 1; $i <= $query->max_num_pages; $i++) : ?>
                            <button type="button" class="ctc-treat-page-btn <?php echo ($i === intval($paged)) ? 'active' : ''; ?>" data-page="<?php echo intval($i); ?>">
                                <?php echo intval($i); ?>
                            </button>
                        <?php endfor; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php wp_reset_postdata(); ?>

        </div>

        <style>
            .ctc-treatments-section {
                width: 100%;
            }
            .ctc-treat-filter-bar {
                display: flex;
                flex-direction: column;
                gap: 16px;
                margin-bottom: 32px;
                align-items: center;
                justify-content: center;
                text-align: center;
                margin: 0 auto 32px auto;
            }

            .ctc-treat-search-box {
                position: relative !important;
                width: 100% !important;
                max-width: 600px !important;
                margin: 0 auto !important;
            }
            .ctc-treat-search-icon {
                position: absolute !important;
                left: 18px !important;
                top: 50% !important;
                transform: translateY(-50%) !important;
                color: #0f766e !important;
                font-size: 1rem !important;
                z-index: 5 !important;
                pointer-events: none !important;
            }
            .ctc-treat-search-input {
                width: 100% !important;
                padding: 14px 20px 14px 48px !important;
                border-radius: 999px !important;
                border: 1.5px solid #cbd5e1 !important;
                background: #ffffff !important;
                color: #0f172a !important;
                font-family: 'Inter', sans-serif !important;
                font-size: 0.95rem !important;
                outline: none !important;
                transition: all 0.3s ease !important;
                box-shadow: 0 4px 12px rgba(15, 118, 110, 0.05) !important;
                box-sizing: border-box !important;
            }
            .ctc-treat-search-input:focus {
                border-color: #0f766e !important;
                box-shadow: 0 6px 16px rgba(15, 118, 110, 0.15) !important;
            }

            .ctc-treat-cat-tabs {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                justify-content: center;
                width: 100%;
                margin: 0 auto;
            }
            .ctc-treat-cat-tab {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 10px 20px;
                border-radius: 999px;
                background: #f1f5f9;
                border: 1px solid #e2e8f0;
                color: #475569;
                font-family: 'Manrope', sans-serif;
                font-weight: 600;
                font-size: 0.9rem;
                cursor: pointer;
                transition: all 0.3s ease;
            }
            .ctc-treat-cat-tab:hover {
                background: #ccfbf1;
                color: #0f766e;
                border-color: #0f766e;
            }
            .ctc-treat-cat-tab.active {
                background: #0f766e;
                color: #ffffff;
                border-color: #0f766e;
                box-shadow: 0 4px 12px rgba(15, 118, 110, 0.25);
            }

            .ctc-treatments-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 20px;
                transition: opacity 0.25s ease;
            }

            .ctc-treat-pagination-box {
                display: flex;
                justify-content: center;
                gap: 8px;
                margin-top: 36px;
                width: 100%;
                flex-wrap: wrap;
            }
            .ctc-treat-page-btn {
                min-width: 42px;
                height: 42px;
                padding: 0 12px;
                border-radius: 12px;
                background: #ffffff;
                border: 1.5px solid #e2e8f0;
                color: #0f172a;
                font-family: 'Manrope', sans-serif;
                font-weight: 700;
                font-size: 0.95rem;
                cursor: pointer;
                transition: all 0.25s ease;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            .ctc-treat-page-btn:hover {
                border-color: #0f766e;
                color: #0f766e;
                background: #ccfbf1;
            }
            .ctc-treat-page-btn.active {
                background: #0f766e;
                color: #ffffff;
                border-color: #0f766e;
                box-shadow: 0 4px 12px rgba(15, 118, 110, 0.2);
            }

            /* Focus Overrides */
            .ctc-treat-page-btn:focus, .ctc-treat-page-btn:active {
                background-color: #ffffff !important;
                color: #0f172a !important;
                border-color: #0f766e !important;
                outline: none !important;
            }
            .ctc-treat-page-btn.active:focus, .ctc-treat-page-btn.active:active {
                background-color: #0f766e !important;
                color: #ffffff !important;
                border-color: #0f766e !important;
            }

            /* Dark Theme Mode Integration */
            html.dark-theme .ctc-treat-search-input, body.dark-theme .ctc-treat-search-input {
                background-color: #172033 !important;
                border-color: #28354e !important;
                color: #f8fafc !important;
            }
            html.dark-theme .ctc-treat-search-icon, body.dark-theme .ctc-treat-search-icon {
                color: #14b8a6 !important;
            }
            html.dark-theme .ctc-treat-cat-tab, body.dark-theme .ctc-treat-cat-tab {
                background-color: #172033 !important;
                border-color: #28354e !important;
                color: #94a3b8 !important;
            }
            html.dark-theme .ctc-treat-cat-tab.active, body.dark-theme .ctc-treat-cat-tab.active {
                background-color: #0f766e !important;
                color: #ffffff !important;
            }
            html.dark-theme .ctc-treat-page-btn, body.dark-theme .ctc-treat-page-btn {
                background-color: #172033 !important;
                border-color: #28354e !important;
                color: #f8fafc !important;
            }
            html.dark-theme .ctc-treat-page-btn.active, body.dark-theme .ctc-treat-page-btn.active {
                background-color: #0f766e !important;
                color: #ffffff !important;
                border-color: #0f766e !important;
            }

            @media (max-width: 1200px) {
                .ctc-treatments-grid {
                    grid-template-columns: repeat(3, 1fr);
                }
            }
            @media (max-width: 900px) {
                .ctc-treatments-grid {
                    grid-template-columns: repeat(2, 1fr);
                }
            }
            @media (max-width: 600px) {
                .ctc-treatments-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>

        <script>
        (function() {
            function initTreatmentsGrid() {
                if (typeof jQuery === 'undefined') {
                    setTimeout(initTreatmentsGrid, 50);
                    return;
                }
                var $ = jQuery;
                var $section = $('#ctc-treatments-<?php echo esc_js($widget_id); ?>');
                if (!$section.length || $section.data('grid-init') === true) return;
                $section.data('grid-init', true);

                var $grid = $section.find('.ctc-treatments-grid');
                var $pagBox = $section.find('.ctc-treat-pagination-box');
                var currentCategory = 'all';
                var currentPage = 1;
                var searchTimeout;
                var lastDevicePPP = 0;
                var orderbyVal = '<?php echo esc_js($orderby); ?>';
                var orderVal = '<?php echo esc_js($order); ?>';
                var buttonText = '<?php echo esc_js($button_text); ?>';

                function getResponsivePostsPerPage() {
                    var width = $(window).width();
                    if (width <= 767) {
                        return <?php echo intval($ppp_mobile); ?>;
                    } else if (width <= 1024) {
                        return <?php echo intval($ppp_tablet); ?>;
                    }
                    return <?php echo intval($ppp_desktop); ?>;
                }

                function doFilter(page, force) {
                    if (!page) page = 1;
                    currentPage = page;
                    var searchVal = $section.find('.ctc-treat-search-input').val();
                    var ppp = getResponsivePostsPerPage();

                    if (!force && lastDevicePPP === ppp && page === currentPage && searchVal === '' && currentCategory === 'all') {
                        // Avoid redundant fetch
                    }
                    lastDevicePPP = ppp;

                    $grid.css('opacity', '0.5');

                    $.ajax({
                        url: '<?php echo esc_url_raw(admin_url('admin-ajax.php')); ?>',
                        type: 'POST',
                        data: {
                            action: 'caretochina_filter_treatments',
                            category: currentCategory,
                            search: searchVal,
                            page: currentPage,
                            posts_per_page: ppp,
                            orderby: orderbyVal,
                            order: orderVal,
                            button_text: buttonText
                        },
                        success: function(res) {
                            $grid.css('opacity', '1');
                            if (res.success) {
                                $grid.html(res.data.html);
                                $pagBox.html(res.data.pagination_html);
                            }
                        },
                        error: function() {
                            $grid.css('opacity', '1');
                        }
                    });
                }

                // Initial responsive check
                var initialPPP = getResponsivePostsPerPage();
                if (initialPPP !== <?php echo intval($ppp_desktop); ?>) {
                    doFilter(1, true);
                }

                // Window resize debounced listener
                var resizeTimer;
                $(window).on('resize', function() {
                    clearTimeout(resizeTimer);
                    resizeTimer = setTimeout(function() {
                        var newPPP = getResponsivePostsPerPage();
                        if (newPPP !== lastDevicePPP) {
                            doFilter(1, true);
                        }
                    }, 250);
                });

                $section.on('click', '.ctc-treat-cat-tab', function() {
                    $section.find('.ctc-treat-cat-tab').removeClass('active');
                    $(this).addClass('active');
                    currentCategory = $(this).data('category');
                    doFilter(1, true);
                });

                $section.on('keyup input', '.ctc-treat-search-input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(function() {
                        doFilter(1, true);
                    }, 300);
                });

                $section.on('click', '.ctc-treat-page-btn', function() {
                    var p = $(this).data('page');
                    doFilter(p, true);
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initTreatmentsGrid);
            } else {
                initTreatmentsGrid();
            }
            window.addEventListener('load', initTreatmentsGrid);
        })();
        </script>
        <?php
    }
}
