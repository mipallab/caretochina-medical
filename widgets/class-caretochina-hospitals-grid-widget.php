<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class CareToChina_Hospitals_Grid_Widget extends Widget_Base {

    public function get_name() {
        return 'caretochina_hospitals_grid';
    }

    public function get_title() {
        return __('CareToChina Hospitals Grid & Search', 'caretochina-medical');
    }

    public function get_icon() {
        return 'eicon-gallery-grid';
    }

    public function get_categories() {
        return ['general', 'basic'];
    }

    public function get_keywords() {
        return ['hospital', 'grid', 'search', 'ajax', 'jci', 'caretochina-medical', 'card', 'pagination'];
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
                'default'     => __('Search hospital by name or specialty...', 'caretochina-medical'),
                'condition'   => ['show_search' => 'yes'],
            ]
        );

        $this->add_control(
            'show_city_tabs',
            [
                'label'        => __('Show City / Location Filter Tabs', 'caretochina-medical'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
            ]
        );

        $this->add_responsive_control(
            'posts_per_page',
            [
                'label'          => __('Hospitals Per Page', 'caretochina-medical'),
                'type'           => Controls_Manager::NUMBER,
                'default'        => 6,
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
                'default' => '3',
                'tablet_default' => '2',
                'mobile_default' => '1',
                'options' => [
                    '1' => '1 Column',
                    '2' => '2 Columns',
                    '3' => '3 Columns',
                    '4' => '4 Columns',
                ],
                'selectors' => [
                    '{{WRAPPER}} .ctc-hospitals-grid' => 'grid-template-columns: repeat({{VALUE}}, 1fr);',
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
                'default'    => ['unit' => 'px', 'size' => 24],
                'selectors'  => [
                    '{{WRAPPER}} .ctc-hospitals-grid' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        // Gap Control Between Search, Location Tabs, and Grid
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
                    '{{WRAPPER}} .ctc-hosp-filter-bar' => 'gap: {{SIZE}}{{UNIT}}; margin-bottom: {{SIZE}}{{UNIT}};',
                ],
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
        $show_pag = ($settings['show_pagination'] === 'yes');

        // Get Cities
        $cities = get_terms([
            'taxonomy'   => 'hospital_city',
            'hide_empty' => false,
        ]);

        $paged = get_query_var('paged') ? get_query_var('paged') : (get_query_var('page') ? get_query_var('page') : 1);

        $args = [
            'post_type'      => 'hospital',
            'post_status'    => 'publish',
            'posts_per_page' => $posts_count,
            'paged'          => $paged,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $query = new \WP_Query($args);
        $widget_id = $this->get_id();
        ?>

        <div class="ctc-hospitals-section" id="ctc-hospitals-<?php echo esc_attr($widget_id); ?>">
            
            <?php if ($settings['show_search'] === 'yes' || $settings['show_city_tabs'] === 'yes') : ?>
                <div class="ctc-hosp-filter-bar">
                    
                    <?php if ($settings['show_search'] === 'yes') : ?>
                        <div class="ctc-hosp-search-box">
                            <i class="fas fa-search ctc-search-icon"></i>
                            <input type="text" class="ctc-hosp-search-input" placeholder="<?php echo esc_attr($settings['search_placeholder']); ?>">
                        </div>
                    <?php endif; ?>

                    <?php if ($settings['show_city_tabs'] === 'yes' && !empty($cities) && !is_wp_error($cities)) : ?>
                        <div class="ctc-city-tabs">
                            <button type="button" class="ctc-city-tab active" data-city="all">
                                <i class="fas fa-globe"></i> <?php esc_html_e('All Locations', 'caretochina-medical'); ?>
                            </button>
                            <?php foreach ($cities as $city) : ?>
                                <button type="button" class="ctc-city-tab" data-city="<?php echo esc_attr($city->slug); ?>">
                                    <i class="fas fa-city"></i> <?php echo esc_html($city->name); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </div>
            <?php endif; ?>

            <!-- Hospital Grid Output -->
            <div class="ctc-hospitals-grid">
                <?php if ($query->have_posts()) : 
                    while ($query->have_posts()) : $query->the_post();
                        CareToChina_Hospitals_Plugin::render_hospital_card(get_the_ID());
                    endwhile;
                else : ?>
                    <div class="ctc-no-hospitals" style="grid-column: 1 / -1; text-align: center; padding: 50px 20px; color: #64748b;">
                        <?php esc_html_e('No hospitals published yet.', 'caretochina-medical'); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- SINGLE Pagination Container -->
            <?php if ($show_pag) : ?>
                <div class="ctc-hosp-pagination-box" id="ctc-pagination-<?php echo esc_attr($widget_id); ?>">
                    <?php if ($query->max_num_pages > 1) : ?>
                        <?php for ($i = 1; $i <= $query->max_num_pages; $i++) : ?>
                            <button type="button" class="ctc-hosp-page-btn <?php echo ($i === intval($paged)) ? 'active' : ''; ?>" data-page="<?php echo intval($i); ?>">
                                <?php echo intval($i); ?>
                            </button>
                        <?php endfor; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php wp_reset_postdata(); ?>

        </div>

        <style>
            .ctc-hospitals-section {
                width: 100%;
            }
            .ctc-hosp-filter-bar {
                display: flex;
                flex-direction: column;
                gap: 16px;
                margin-bottom: 32px;
                align-items: center;
                justify-content: center;
                text-align: center;
                margin: 0 auto 32px auto;
            }

            .ctc-hosp-search-box {
                position: relative !important;
                width: 100% !important;
                max-width: 600px !important;
                margin: 0 auto !important;
            }
            .ctc-search-icon {
                position: absolute !important;
                left: 18px !important;
                top: 50% !important;
                transform: translateY(-50%) !important;
                color: #0f766e !important;
                font-size: 1rem !important;
                z-index: 5 !important;
                pointer-events: none !important;
            }
            .ctc-hosp-search-input {
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
            .ctc-hosp-search-input:focus {
                border-color: #0f766e !important;
                box-shadow: 0 6px 16px rgba(15, 118, 110, 0.15) !important;
            }

            .ctc-city-tabs {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                justify-content: center;
                width: 100%;
                margin: 0 auto;
            }
            .ctc-city-tab {
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
            .ctc-city-tab:hover {
                background: #ccfbf1;
                color: #0f766e;
                border-color: #0f766e;
            }
            .ctc-city-tab.active {
                background: #0f766e;
                color: #ffffff;
                border-color: #0f766e;
                box-shadow: 0 4px 12px rgba(15, 118, 110, 0.25);
            }

            .ctc-hospitals-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 24px;
            }

            .ctc-hosp-pagination-box {
                display: flex;
                justify-content: center;
                gap: 8px;
                margin-top: 36px;
                width: 100%;
                flex-wrap: wrap;
            }
            .ctc-hosp-page-btn {
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
            .ctc-hosp-page-btn:hover {
                border-color: #0f766e;
                color: #0f766e;
                background: #ccfbf1;
            }
            .ctc-hosp-page-btn.active {
                background: #0f766e;
                color: #ffffff;
                border-color: #0f766e;
                box-shadow: 0 4px 12px rgba(15, 118, 110, 0.2);
            }

            /* Dark Theme Mode Integration */
            html.dark-theme .ctc-hosp-search-input, body.dark-theme .ctc-hosp-search-input {
                background-color: #1c2541 !important;
                border-color: #2d3748 !important;
                color: #f8fafc !important;
            }
            html.dark-theme .ctc-search-icon, body.dark-theme .ctc-search-icon {
                color: #14b8a6 !important;
            }
            html.dark-theme .ctc-city-tab, body.dark-theme .ctc-city-tab {
                background-color: #1c2541 !important;
                border-color: #2d3748 !important;
                color: #94a3b8 !important;
            }
            html.dark-theme .ctc-city-tab.active, body.dark-theme .ctc-city-tab.active {
                background-color: #0f766e !important;
                color: #ffffff !important;
            }
            html.dark-theme .ctc-hosp-page-btn, body.dark-theme .ctc-hosp-page-btn {
                background-color: #1c2541 !important;
                border-color: #2d3748 !important;
                color: #f8fafc !important;
            }
            html.dark-theme .ctc-hosp-page-btn.active, body.dark-theme .ctc-hosp-page-btn.active {
                background-color: #0f766e !important;
                color: #ffffff !important;
                border-color: #0f766e !important;
            }

            @media (max-width: 1024px) {
                .ctc-hospitals-grid {
                    grid-template-columns: repeat(2, 1fr);
                }
            }
            @media (max-width: 767px) {
                .ctc-hospitals-grid {
                    grid-template-columns: 1fr;
                }
            }
                    /* Override theme reset.css #c36 red focus/hover color for pagination & tabs */
            .ctc-hosp-page-btn:focus, .ctc-hosp-page-btn:focus-visible, .ctc-hosp-page-btn:active {
                background-color: #ffffff !important;
                color: #0f172a !important;
                border-color: #0f766e !important;
                outline: none !important;
                box-shadow: 0 0 0 2px rgba(15, 118, 110, 0.2) !important;
            }
            .ctc-hosp-page-btn.active:focus, .ctc-hosp-page-btn.active:focus-visible, .ctc-hosp-page-btn.active:active {
                background-color: #0f766e !important;
                color: #ffffff !important;
                border-color: #0f766e !important;
                outline: none !important;
            }
            .ctc-city-tab:focus, .ctc-city-tab:focus-visible, .ctc-city-tab:active {
                background: #f1f5f9 !important;
                color: #475569 !important;
                border-color: #e2e8f0 !important;
                outline: none !important;
            }
            .ctc-city-tab.active:focus, .ctc-city-tab.active:focus-visible, .ctc-city-tab.active:active {
                background: #0f766e !important;
                color: #ffffff !important;
                border-color: #0f766e !important;
                outline: none !important;
            }

            /* Dark Theme Focus Overrides */
            html.dark-theme .ctc-hosp-page-btn:focus, body.dark-theme .ctc-hosp-page-btn:focus,
            html.dark-theme .ctc-hosp-page-btn:focus-visible, body.dark-theme .ctc-hosp-page-btn:focus-visible,
            html.dark-theme .ctc-hosp-page-btn:active, body.dark-theme .ctc-hosp-page-btn:active {
                background-color: #1c2541 !important;
                color: #f8fafc !important;
                border-color: #14b8a6 !important;
                outline: none !important;
            }
            html.dark-theme .ctc-hosp-page-btn.active:focus, body.dark-theme .ctc-hosp-page-btn.active:focus,
            html.dark-theme .ctc-hosp-page-btn.active:focus-visible, body.dark-theme .ctc-hosp-page-btn.active:focus-visible,
            html.dark-theme .ctc-hosp-page-btn.active:active, body.dark-theme .ctc-hosp-page-btn.active:active {
                background-color: #0f766e !important;
                color: #ffffff !important;
                border-color: #0f766e !important;
                outline: none !important;
            }
            html.dark-theme .ctc-city-tab:focus, body.dark-theme .ctc-city-tab:focus,
            html.dark-theme .ctc-city-tab:focus-visible, body.dark-theme .ctc-city-tab:focus-visible,
            html.dark-theme .ctc-city-tab:active, body.dark-theme .ctc-city-tab:active {
                background-color: #1c2541 !important;
                color: #94a3b8 !important;
                border-color: #2d3748 !important;
                outline: none !important;
            }
            html.dark-theme .ctc-city-tab.active:focus, body.dark-theme .ctc-city-tab.active:focus,
            html.dark-theme .ctc-city-tab.active:focus-visible, body.dark-theme .ctc-city-tab.active:focus-visible,
            html.dark-theme .ctc-city-tab.active:active, body.dark-theme .ctc-city-tab.active:active {
                background-color: #0f766e !important;
                color: #ffffff !important;
                border-color: #0f766e !important;
                outline: none !important;
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            var $section = $('#ctc-hospitals-<?php echo esc_js($widget_id); ?>');
            var $grid = $section.find('.ctc-hospitals-grid');
            var $pagBox = $section.find('.ctc-hosp-pagination-box');
            var currentCity = 'all';
            var currentPage = 1;
            var searchTimeout;
            var lastDevicePPP = 0;

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
                var searchVal = $section.find('.ctc-hosp-search-input').val();
                var ppp = getResponsivePostsPerPage();

                if (!force && lastDevicePPP === ppp && page === currentPage && searchVal === '' && currentCity === 'all') {
                    // Avoid redundant fetch if PPP hasn't changed
                }
                lastDevicePPP = ppp;

                $grid.css('opacity', '0.5');

                $.ajax({
                    url: '<?php echo esc_url_raw(admin_url('admin-ajax.php')); ?>',
                    type: 'POST',
                    data: {
                        action: 'caretochina_filter_hospitals',
                        city: currentCity,
                        search: searchVal,
                        page: currentPage,
                        posts_per_page: ppp
                    },
                    success: function(res) {
                        $grid.css('opacity', '1');
                        if (res.success) {
                            $grid.html(res.data.html);
                            $pagBox.html(res.data.pagination_html);
                        }
                    }
                });
            }

            // Initial check on load: if device is Tablet or Mobile, refresh grid with exact responsive PPP
            var initialPPP = getResponsivePostsPerPage();
            if (initialPPP !== <?php echo intval($ppp_desktop); ?>) {
                doFilter(1, true);
            }

            // Window resize debounced listener to dynamically adjust grid & pagination when switching orientations/devices
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

            $section.on('click', '.ctc-city-tab', function() {
                $section.find('.ctc-city-tab').removeClass('active');
                $(this).addClass('active');
                currentCity = $(this).data('city');
                doFilter(1, true);
            });

            $section.on('keyup input', '.ctc-hosp-search-input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    doFilter(1, true);
                }, 300);
            });

            $section.on('click', '.ctc-hosp-page-btn', function() {
                var p = $(this).data('page');
                doFilter(p, true);
            });
        });
        </script>
        <?php
    }
}