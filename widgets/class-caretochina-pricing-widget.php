<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class CareToChina_Pricing_Widget extends Widget_Base {

    public function get_name() {
        return 'caretochina_pricing_plans';
    }

    public function get_title() {
        return __('CareToChina Dynamic Pricing Plans', 'caretochina-medical');
    }

    public function get_icon() {
        return 'eicon-price-table';
    }

    public function get_categories() {
        return ['general', 'basic'];
    }

    public function get_keywords() {
        return ['pricing', 'plans', 'packages', 'medical', 'comparison', 'bento', 'table', 'caretochina'];
    }

    public function get_style_depends() {
        return ['caretochina-font-awesome', 'font-awesome', 'caretochina-booking-style'];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Pricing Display Settings', 'caretochina-medical'),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'pricing_info',
            [
                'type'            => Controls_Manager::RAW_HTML,
                'raw'             => __('Renders dynamic Service Packages from WordPress Admin (Hospitals -> Service Packages) with 4-Cards Grid, Comparison Matrix Table, Bento Tabs, and Light/Dark Mode toggle.', 'caretochina-medical'),
                'content_classes' => 'elementor-descriptor',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        if (class_exists('CareToChina_Pricing_Page')) {
            echo CareToChina_Pricing_Page::instance()->render_pricing_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }
}
