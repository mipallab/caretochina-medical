<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Background;

class CareToChina_Hospitals_Slider_Widget extends Widget_Base {

    public function get_name() {
        return 'caretochina_hospitals_slider';
    }

    public function get_title() {
        return __('CareToChina Hospitals Slider', 'caretochina-medical');
    }

    public function get_icon() {
        return 'eicon-slides';
    }

    public function get_categories() {
        return ['general', 'basic'];
    }

    public function get_keywords() {
        return ['hospital', 'slider', 'carousel', 'swiper', 'caretochina-medical', 'jci', 'drag'];
    }

    protected function register_controls() {
        
        // ================= CONTENT TAB: SLIDER QUERY & SETTINGS =================
        $this->start_controls_section(
            'section_slider_content',
            [
                'label' => __('Slider Query & Layout', 'caretochina-medical'),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'posts_count',
            [
                'label'   => __('Hospitals Count', 'caretochina-medical'),
                'type'    => Controls_Manager::NUMBER,
                'default' => 8,
                'min'     => 2,
                'max'     => 30,
            ]
        );

        $cities = get_terms(['taxonomy' => 'hospital_city', 'hide_empty' => false]);
        $city_options = ['all' => __('All Locations', 'caretochina-medical')];
        if (!empty($cities) && !is_wp_error($cities)) {
            foreach ($cities as $c) {
                $city_options[$c->slug] = $c->name;
            }
        }

        $this->add_control(
            'city_filter',
            [
                'label'   => __('Filter by City', 'caretochina-medical'),
                'type'    => Controls_Manager::SELECT,
                'default' => 'all',
                'options' => $city_options,
            ]
        );

        $this->add_responsive_control(
            'slides_to_show',
            [
                'label'   => __('Slides to Show', 'caretochina-medical'),
                'type'    => Controls_Manager::SELECT,
                'default' => '3',
                'tablet_default' => '2',
                'mobile_default' => '1',
                'options' => [
                    '1' => '1 Slide',
                    '2' => '2 Slides',
                    '3' => '3 Slides',
                    '4' => '4 Slides',
                ],
            ]
        );

        $this->add_responsive_control(
            'slides_gap',
            [
                'label'      => __('Gap Between Cards (px)', 'caretochina-medical'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'rem'],
                'range'      => [
                    'px' => ['min' => 0, 'max' => 80],
                ],
                'default'    => ['unit' => 'px', 'size' => 24],
            ]
        );

        $this->add_control(
            'enable_drag',
            [
                'label'        => __('Enable Mouse Drag & Touch Swipe', 'caretochina-medical'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'label_on'     => __('Yes', 'caretochina-medical'),
                'label_off'    => __('No', 'caretochina-medical'),
                'return_value' => 'yes',
            ]
        );

        $this->add_control(
            'autoplay',
            [
                'label'        => __('Autoplay', 'caretochina-medical'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'label_on'     => __('Yes', 'caretochina-medical'),
                'label_off'    => __('No', 'caretochina-medical'),
                'return_value' => 'yes',
            ]
        );

        $this->add_control(
            'show_arrows',
            [
                'label'        => __('Show Navigation Arrows', 'caretochina-medical'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'label_on'     => __('Yes', 'caretochina-medical'),
                'label_off'    => __('No', 'caretochina-medical'),
                'return_value' => 'yes',
            ]
        );

        $this->add_control(
            'arrow_icon_style',
            [
                'label'     => __('Arrow Icon Style', 'caretochina-medical'),
                'type'      => Controls_Manager::SELECT,
                'default'   => 'chevron',
                'options'   => [
                    'chevron' => __('Chevron (< >)', 'caretochina-medical'),
                    'arrow'   => __('Long Arrow (← →)', 'caretochina-medical'),
                    'caret'   => __('Caret (◀ ▶)', 'caretochina-medical'),
                    'angle'   => __('Angle Thin (‹ ›)', 'caretochina-medical'),
                ],
                'condition' => ['show_arrows' => 'yes'],
            ]
        );

        $this->add_control(
            'show_dots',
            [
                'label'        => __('Show Dots Pagination', 'caretochina-medical'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'label_on'     => __('Yes', 'caretochina-medical'),
                'label_off'    => __('No', 'caretochina-medical'),
                'return_value' => 'yes',
            ]
        );

        $this->end_controls_section();

        // ================= STYLE TAB: SLIDER WRAPPER STYLING =================
        $this->start_controls_section(
            'section_style_wrapper',
            [
                'label' => __('Slider Container & Padding', 'caretochina-medical'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        // Responsive Wrapper Padding Control across all devices
        $this->add_responsive_control(
            'slider_wrapper_padding',
            [
                'label'      => __('Wrapper Padding', 'caretochina-medical'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'default'    => [
                    'top' => '0', 'right' => '55', 'bottom' => '0', 'left' => '55', 'unit' => 'px', 'isLinked' => false
                ],
                'selectors'  => [
                    '{{WRAPPER}} .ctc-hosp-slider-wrapper' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
                ],
            ]
        );

        $this->end_controls_section();

        // ================= STYLE TAB: CARD STYLING =================
        $this->start_controls_section(
            'section_style_cards',
            [
                'label' => __('Hospital Card Styling', 'caretochina-medical'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'card_max_width',
            [
                'label'      => __('Card Max Width (px)', 'caretochina-medical'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range'      => [
                    'px' => ['min' => 200, 'max' => 800],
                ],
                'selectors'  => [
                    '{{WRAPPER}} .ctc-hospital-card' => 'max-width: {{SIZE}}{{UNIT}}; margin-left: auto; margin-right: auto;',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name'     => 'card_bg',
                'label'    => __('Card Background', 'caretochina-medical'),
                'types'    => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .ctc-hospital-card',
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name'     => 'card_border',
                'label'    => __('Border', 'caretochina-medical'),
                'selector' => '{{WRAPPER}} .ctc-hospital-card',
            ]
        );

        $this->add_responsive_control(
            'card_border_radius',
            [
                'label'      => __('Border Radius (px)', 'caretochina-medical'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors'  => [
                    '{{WRAPPER}} .ctc-hospital-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    '{{WRAPPER}} .ctc-hosp-img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} 0 0;',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name'     => 'card_box_shadow',
                'label'    => __('Box Shadow', 'caretochina-medical'),
                'selector' => '{{WRAPPER}} .ctc-hospital-card',
            ]
        );

        $this->end_controls_section();

        // ================= STYLE TAB: TYPOGRAPHY & COLORS =================
        $this->start_controls_section(
            'section_style_typography',
            [
                'label' => __('Typography & Colors', 'caretochina-medical'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'title_typography',
                'label'    => __('Title Typography', 'caretochina-medical'),
                'selector' => '{{WRAPPER}} .ctc-hosp-title a',
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label'     => __('Title Color', 'caretochina-medical'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ctc-hosp-title a' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'title_hover_color',
            [
                'label'     => __('Title Hover Color', 'caretochina-medical'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ctc-hosp-title a:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'specs_color',
            [
                'label'     => __('Specialities Text Color', 'caretochina-medical'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ctc-hosp-specs' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'rating_color',
            [
                'label'     => __('Rating Stars Color', 'caretochina-medical'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ctc-hosp-rating' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // ================= STYLE TAB: SLIDER NAVIGATION =================
        $this->start_controls_section(
            'section_style_navigation',
            [
                'label' => __('Slider Arrows & Dots', 'caretochina-medical'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'arrow_box_size',
            [
                'label'      => __('Arrow Button Size (px)', 'caretochina-medical'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => [
                    'px' => ['min' => 28, 'max' => 90],
                ],
                'default'    => ['unit' => 'px', 'size' => 46],
                'selectors'  => [
                    '{{WRAPPER}} .ctc-slider-arrow' => 'width: {{SIZE}}{{UNIT}} !important; height: {{SIZE}}{{UNIT}} !important;',
                ],
            ]
        );

        $this->add_responsive_control(
            'arrow_icon_size',
            [
                'label'      => __('Arrow Icon Size (px)', 'caretochina-medical'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => [
                    'px' => ['min' => 10, 'max' => 50],
                ],
                'default'    => ['unit' => 'px', 'size' => 18],
                'selectors'  => [
                    '{{WRAPPER}} .ctc-slider-arrow svg' => 'width: {{SIZE}}{{UNIT}} !important; height: {{SIZE}}{{UNIT}} !important;',
                ],
            ]
        );

        $this->add_control(
            'arrow_bg',
            [
                'label'     => __('Arrow Background', 'caretochina-medical'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ctc-slider-arrow' => 'background-color: {{VALUE}} !important;',
                ],
            ]
        );

        $this->add_control(
            'arrow_color',
            [
                'label'     => __('Arrow Icon Color', 'caretochina-medical'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ctc-slider-arrow' => 'color: {{VALUE}} !important; border-color: {{VALUE}} !important;',
                    '{{WRAPPER}} .ctc-slider-arrow svg' => 'stroke: {{VALUE}} !important; fill: {{VALUE}} !important;',
                ],
            ]
        );

        $this->add_control(
            'arrow_hover_bg',
            [
                'label'     => __('Arrow Hover Background', 'caretochina-medical'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ctc-slider-arrow:hover' => 'background-color: {{VALUE}} !important;',
                ],
            ]
        );

        $this->add_control(
            'dot_color',
            [
                'label'     => __('Dot Color', 'caretochina-medical'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ctc-swiper-dots .swiper-pagination-bullet' => 'background-color: {{VALUE}} !important;',
                ],
            ]
        );

        $this->add_control(
            'dot_active_color',
            [
                'label'     => __('Active Dot Color', 'caretochina-medical'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ctc-swiper-dots .swiper-pagination-bullet-active' => 'background-color: {{VALUE}} !important;',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        if (empty($settings)) {
            $settings = $this->get_settings();
        }

        $posts_count = !empty($settings['posts_count']) ? intval($settings['posts_count']) : 8;
        
        // Responsive Slides to Show for all Elementor viewports
        $slides_desktop  = !empty($settings['slides_to_show']) ? intval($settings['slides_to_show']) : 3;
        $slides_laptop   = !empty($settings['slides_to_show_laptop']) ? intval($settings['slides_to_show_laptop']) : $slides_desktop;
        $slides_tab_ext  = !empty($settings['slides_to_show_tablet_extra']) ? intval($settings['slides_to_show_tablet_extra']) : 2;
        $slides_tablet   = !empty($settings['slides_to_show_tablet']) ? intval($settings['slides_to_show_tablet']) : 2;
        $slides_mob_ext  = !empty($settings['slides_to_show_mobile_extra']) ? intval($settings['slides_to_show_mobile_extra']) : 1;
        $slides_mobile   = !empty($settings['slides_to_show_mobile']) ? intval($settings['slides_to_show_mobile']) : 1;
        
        // Responsive Gaps for all Elementor viewports
        $gap_desktop = (isset($settings['slides_gap']['size']) && $settings['slides_gap']['size'] !== '') ? intval($settings['slides_gap']['size']) : 24;
        $gap_tablet  = (isset($settings['slides_gap_tablet']['size']) && $settings['slides_gap_tablet']['size'] !== '') ? intval($settings['slides_gap_tablet']['size']) : $gap_desktop;
        $gap_mobile  = (isset($settings['slides_gap_mobile']['size']) && $settings['slides_gap_mobile']['size'] !== '') ? intval($settings['slides_gap_mobile']['size']) : 16;

        $arrow_style = !empty($settings['arrow_icon_style']) ? $settings['arrow_icon_style'] : 'chevron';

        $args = [
            'post_type'      => 'hospital',
            'post_status'    => 'publish',
            'posts_per_page' => $posts_count,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if (!empty($settings['city_filter']) && $settings['city_filter'] !== 'all') {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
            $args['tax_query'] = [
                [
                    'taxonomy' => 'hospital_city',
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field($settings['city_filter']),
                ]
            ];
        }

        $query = new \WP_Query($args);
        $widget_id = $this->get_id();

        if ($query->have_posts()) :
            ?>
            <div class="ctc-hosp-slider-wrapper" id="ctc-hosp-slider-<?php echo esc_attr($widget_id); ?>">
                
                <div class="swiper-container ctc-hosp-swiper">
                    <div class="swiper-wrapper">
                        <?php while ($query->have_posts()) : $query->the_post(); ?>
                            <div class="swiper-slide">
                                <?php CareToChina_Hospitals_Plugin::render_hospital_card(get_the_ID()); ?>
                            </div>
                        <?php endwhile; wp_reset_postdata(); ?>
                    </div>

                    <?php if ($settings['show_dots'] === 'yes') : ?>
                        <div class="swiper-pagination ctc-swiper-dots"></div>
                    <?php endif; ?>
                </div>

                <?php if ($settings['show_arrows'] === 'yes') : ?>
                    <button type="button" class="ctc-slider-arrow ctc-prev-arrow" aria-label="<?php esc_attr_e('Previous Slide', 'caretochina-medical'); ?>">
                        <?php if ($arrow_style === 'arrow') : ?>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                        <?php elseif ($arrow_style === 'caret') : ?>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
                        <?php elseif ($arrow_style === 'angle') : ?>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                        <?php else : ?>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                        <?php endif; ?>
                    </button>

                    <button type="button" class="ctc-slider-arrow ctc-next-arrow" aria-label="<?php esc_attr_e('Next Slide', 'caretochina-medical'); ?>">
                        <?php if ($arrow_style === 'arrow') : ?>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                        <?php elseif ($arrow_style === 'caret') : ?>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
                        <?php elseif ($arrow_style === 'angle') : ?>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        <?php else : ?>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        <?php endif; ?>
                    </button>
                <?php endif; ?>

            </div>

            <style>
                .ctc-hosp-slider-wrapper {
                    position: relative !important;
                    width: 100% !important;
                    box-sizing: border-box !important;
                    margin: 0 auto !important;
                }
                .ctc-hosp-swiper {
                    width: 100% !important;
                    overflow: hidden !important;
                    padding-bottom: 50px !important;
                    position: relative !important;
                    touch-action: pan-y !important;
                    cursor: grab;
                }
                .ctc-hosp-swiper:active {
                    cursor: grabbing;
                }
                .ctc-hosp-swiper .swiper-wrapper {
                    display: flex !important;
                    transition-property: transform !important;
                    box-sizing: content-box !important;
                }
                .ctc-hosp-swiper .swiper-slide {
                    flex-shrink: 0 !important;
                    height: auto !important;
                    display: flex !important;
                    box-sizing: border-box !important;
                    user-select: none;
                }
                .ctc-hosp-swiper .swiper-slide .ctc-hospital-card {
                    height: 100% !important;
                    width: 100% !important;
                }

                .ctc-slider-arrow {
                    position: absolute !important;
                    top: 45% !important;
                    transform: translateY(-50%) !important;
                    width: 46px !important;
                    height: 46px !important;
                    border-radius: 50% !important;
                    background: #ffffff !important;
                    border: 1.5px solid #0f766e !important;
                    color: #0f766e !important;
                    display: flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    cursor: pointer !important;
                    z-index: 30 !important;
                    box-shadow: 0 4px 14px rgba(15, 118, 110, 0.2) !important;
                    transition: all 0.3s ease !important;
                    outline: none !important;
                    pointer-events: auto !important;
                }
                .ctc-slider-arrow:hover {
                    background: #0f766e !important;
                    color: #ffffff !important;
                    border-color: #0f766e !important;
                }
                .ctc-slider-arrow:hover svg {
                    stroke: #ffffff !important;
                    fill: #ffffff !important;
                }
                .ctc-prev-arrow { left: 4px !important; }
                .ctc-next-arrow { right: 4px !important; }
                
                .ctc-swiper-dots {
                    position: absolute !important;
                    bottom: 10px !important;
                    left: 0 !important;
                    width: 100% !important;
                    display: flex !important;
                    justify-content: center !important;
                    gap: 8px !important;
                    z-index: 20 !important;
                }
                .ctc-swiper-dots .swiper-pagination-bullet {
                    width: 10px !important;
                    height: 10px !important;
                    border-radius: 50% !important;
                    background: #cbd5e1 !important;
                    cursor: pointer !important;
                    transition: all 0.3s ease !important;
                    opacity: 0.8 !important;
                    display: inline-block !important;
                    margin: 0 3px !important;
                }
                .ctc-swiper-dots .swiper-pagination-bullet-active {
                    background: #0f766e !important;
                    width: 28px !important;
                    border-radius: 999px !important;
                    opacity: 1 !important;
                }

                /* Dark Mode Support */
                html.dark-theme .ctc-hospital-card, body.dark-theme .ctc-hospital-card {
                    background-color: #1c2541 !important;
                    border-color: #0f766e69 !important;
                }
                html.dark-theme .ctc-hosp-title a, body.dark-theme .ctc-hosp-title a {
                    color: #f8fafc !important;
                }
                html.dark-theme .ctc-hosp-title a:hover, body.dark-theme .ctc-hosp-title a:hover {
                    color: #14b8a6 !important;
                }
                html.dark-theme .ctc-hosp-specs, body.dark-theme .ctc-hosp-specs {
                    color: #94a3b8 !important;
                }
                html.dark-theme .ctc-hosp-footer, body.dark-theme .ctc-hosp-footer {
                    border-top-color: #2d3748 !important;
                }
                html.dark-theme .ctc-hosp-btn, body.dark-theme .ctc-hosp-btn {
                    color: #f8fafc !important;
                    border-color: #2d3748 !important;
                }
                html.dark-theme .ctc-hosp-btn:hover, body.dark-theme .ctc-hosp-btn:hover {
                    color: #0f766e !important;
                    background-color: #ccfbf1 !important;
                    border-color: #0f766e !important;
                }
                html.dark-theme .ctc-slider-arrow, body.dark-theme .ctc-slider-arrow {
                    background-color: #1c2541 !important;
                    border-color: #14b8a6 !important;
                    color: #14b8a6 !important;
                }
                html.dark-theme .ctc-slider-arrow svg, body.dark-theme .ctc-slider-arrow svg {
                    stroke: #14b8a6 !important;
                }
                html.dark-theme .ctc-slider-arrow:hover, body.dark-theme .ctc-slider-arrow:hover {
                    background-color: #0f766e !important;
                    color: #ffffff !important;
                }
                html.dark-theme .ctc-slider-arrow:hover svg, body.dark-theme .ctc-slider-arrow:hover svg {
                    stroke: #ffffff !important;
                }
            </style>

            <script>
            (function() {
                function getActiveDeviceSlides() {
                    if (window.elementorFrontend && typeof elementorFrontend.getCurrentDeviceMode === 'function') {
                        var dev = elementorFrontend.getCurrentDeviceMode();
                        if (dev === 'mobile') return <?php echo intval($slides_mobile); ?>;
                        if (dev === 'mobile_extra') return <?php echo intval($slides_mob_ext); ?>;
                        if (dev === 'tablet') return <?php echo intval($slides_tablet); ?>;
                        if (dev === 'tablet_extra') return <?php echo intval($slides_tab_ext); ?>;
                        if (dev === 'laptop') return <?php echo intval($slides_laptop); ?>;
                    }
                    var w = window.innerWidth;
                    if (w <= 480) return <?php echo intval($slides_mobile); ?>;
                    if (w <= 767) return <?php echo intval($slides_mob_ext); ?>;
                    if (w <= 880) return <?php echo intval($slides_tablet); ?>;
                    if (w <= 1024) return <?php echo intval($slides_tab_ext); ?>;
                    if (w <= 1200) return <?php echo intval($slides_laptop); ?>;
                    return <?php echo intval($slides_desktop); ?>;
                }

                function runHospitalsSwiper() {
                    var wrapper = document.getElementById('ctc-hosp-slider-<?php echo esc_js($widget_id); ?>');
                    if (!wrapper) return;

                    var container = wrapper.querySelector('.ctc-hosp-swiper');
                    var nextArrow = wrapper.querySelector('.ctc-next-arrow');
                    var prevArrow = wrapper.querySelector('.ctc-prev-arrow');
                    var dotsBox   = wrapper.querySelector('.ctc-swiper-dots');

                    if (container && typeof Swiper !== 'undefined') {
                        if (container.swiper) {
                            try { container.swiper.destroy(true, true); } catch(e) {}
                        }

                        var allowDrag = <?php echo ($settings['enable_drag'] === 'yes') ? 'true' : 'false'; ?>;
                        var currentSlides = getActiveDeviceSlides();

                        var swiperConfig = {
                            slidesPerView: currentSlides,
                            spaceBetween: <?php echo intval($gap_desktop); ?>,
                            loop: true,
                            observer: true,
                            observeParents: true,
                            grabCursor: allowDrag,
                            simulateTouch: allowDrag,
                            allowTouchMove: allowDrag,
                            touchRatio: 1,
                            touchAngle: 45,
                            shortSwipes: true,
                            longSwipes: true,
                            breakpoints: {
                                1201: { slidesPerView: <?php echo intval($slides_desktop); ?>, spaceBetween: <?php echo intval($gap_desktop); ?> },
                                1025: { slidesPerView: <?php echo intval($slides_laptop); ?>, spaceBetween: <?php echo intval($gap_desktop); ?> },
                                881:  { slidesPerView: <?php echo intval($slides_tab_ext); ?>, spaceBetween: <?php echo intval($gap_tablet); ?> },
                                768:  { slidesPerView: <?php echo intval($slides_tablet); ?>, spaceBetween: <?php echo intval($gap_tablet); ?> },
                                481:  { slidesPerView: <?php echo intval($slides_mob_ext); ?>, spaceBetween: <?php echo intval($gap_mobile); ?> },
                                0:    { slidesPerView: <?php echo intval($slides_mobile); ?>, spaceBetween: <?php echo intval($gap_mobile); ?> }
                            }
                        };

                        <?php if ($settings['autoplay'] === 'yes') : ?>
                        swiperConfig.autoplay = {
                            delay: 3500,
                            disableOnInteraction: false
                        };
                        <?php endif; ?>

                        <?php if ($settings['show_arrows'] === 'yes') : ?>
                        if (nextArrow && prevArrow) {
                            swiperConfig.navigation = {
                                nextEl: nextArrow,
                                prevEl: prevArrow
                            };
                        }
                        <?php endif; ?>

                        <?php if ($settings['show_dots'] === 'yes') : ?>
                        if (dotsBox) {
                            swiperConfig.pagination = {
                                el: dotsBox,
                                clickable: true
                            };
                        }
                        <?php endif; ?>

                        var swiperInst = new Swiper(container, swiperConfig);

                        if (nextArrow) {
                            nextArrow.onclick = function(e) {
                                e.preventDefault();
                                e.stopPropagation();
                                if (swiperInst) swiperInst.slideNext();
                            };
                        }
                        if (prevArrow) {
                            prevArrow.onclick = function(e) {
                                e.preventDefault();
                                e.stopPropagation();
                                if (swiperInst) swiperInst.slidePrev();
                            };
                        }
                    }
                }

                if (document.readyState === 'complete' || document.readyState === 'interactive') {
                    setTimeout(runHospitalsSwiper, 100);
                } else {
                    document.addEventListener('DOMContentLoaded', runHospitalsSwiper);
                }

                window.addEventListener('load', runHospitalsSwiper);

                if (window.jQuery) {
                    window.jQuery(document).ready(runHospitalsSwiper);
                    window.jQuery(window).on('elementor/frontend/init', function() {
                        if (window.elementorFrontend && window.elementorFrontend.hooks) {
                            elementorFrontend.hooks.addAction('frontend/element_ready/caretochina_hospitals_slider.default', function() {
                                runHospitalsSwiper();
                            });

                            // Re-run Swiper when switching device modes in Elementor Editor!
                            if (elementorFrontend.isEditMode()) {
                                elementorFrontend.on('device:mode:change', function() {
                                    setTimeout(runHospitalsSwiper, 150);
                                });
                            }
                        }
                    });
                }
            })();
            </script>
            <?php
        endif;
    }
}