<?php
/**
 * CareToChina Medical Concierge - Modular Pricing Shortcodes Controller
 *
 * Provides dedicated shortcodes for:
 * 1. Pricing Cards: [caretochina_pricing_cards]
 * 2. Comparison Table: [caretochina_comparison_table] / [caretochina_pricing_comparison]
 * 3. Full Package Details: [caretochina_package_details] / [caretochina_bento_details]
 * 4. Combined Full Pricing: [caretochina_pricing_plans] / [caretochina_pricing]
 *
 * @package CareToChina_Medical
 */

if (!defined('ABSPATH')) {
    exit;
}

class CareToChina_Pricing_Page {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // 1. Pricing Cards Grid Shortcodes
        add_shortcode('caretochina_pricing_cards', [$this, 'render_cards_shortcode']);
        add_shortcode('ctc_pricing_cards', [$this, 'render_cards_shortcode']);

        // 2. Comparison Matrix Table Shortcodes (No heading/subheading)
        add_shortcode('caretochina_comparison_table', [$this, 'render_comparison_shortcode']);
        add_shortcode('caretochina_pricing_comparison', [$this, 'render_comparison_shortcode']);
        add_shortcode('ctc_comparison_table', [$this, 'render_comparison_shortcode']);

        // 3. Full Package Details Bento Tabs Shortcodes (No heading/subheading)
        add_shortcode('caretochina_package_details', [$this, 'render_details_shortcode']);
        add_shortcode('caretochina_bento_details', [$this, 'render_details_shortcode']);
        add_shortcode('ctc_package_details', [$this, 'render_details_shortcode']);

        // 4. Combined All-in-One Shortcodes
        add_shortcode('caretochina_pricing_plans', [$this, 'render_pricing_shortcode']);
        add_shortcode('caretochina_pricing', [$this, 'render_pricing_shortcode']);
        add_shortcode('careyou_pricing_plans', [$this, 'render_pricing_shortcode']);
        add_shortcode('careyou_pricing', [$this, 'render_pricing_shortcode']);

        // Register Assets
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);

        // Register Polylang translation strings
        add_action('init', [$this, 'register_polylang_strings']);
    }

    public function register_polylang_strings() {
        if (function_exists('pll_register_string')) {
            pll_register_string('Our Pricing Plan', 'Our Pricing Plan', 'CareToChina Pricing');
            pll_register_string('Pricing Plans & Concierge Tiers', 'Pricing Plans & Concierge Tiers', 'CareToChina Pricing');
            pll_register_string('Find the Package That Fits You Best', 'Find the Package That Fits You Best', 'CareToChina Pricing');
            pll_register_string('Full Package Details', 'Full Package Details', 'CareToChina Pricing');
            pll_register_string('Everything Included, Clearly Explained', 'Everything Included, Clearly Explained', 'CareToChina Pricing');
            pll_register_string('View Plan Details', 'View Plan Details', 'CareToChina Pricing');
            pll_register_string('Choose Plan', 'Choose Plan', 'CareToChina Pricing');
        }
    }

    /**
     * Register Styles and Scripts
     */
    public function register_assets() {
        if (!wp_style_is('font-awesome', 'registered') && !wp_style_is('font-awesome', 'enqueued')) {
            wp_register_style('font-awesome', CARETOCHINA_MEDICAL_URL . 'assets/vendor/font-awesome/css/all.min.css', [], '6.4.0');
        }

        // Google Fonts for high-end typography (Inter, Manrope, Roboto)
        wp_register_style(
            'caretochina-pricing-fonts',
            'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Manrope:wght@600;700;800;900&family=Roboto:wght@400;500;700&display=swap',
            [],
            CARETOCHINA_MEDICAL_VERSION
        );

        wp_register_style(
            'caretochina-pricing-style',
            CARETOCHINA_MEDICAL_URL . 'assets/css/pricing-style.css',
            ['caretochina-pricing-fonts'],
            CARETOCHINA_MEDICAL_VERSION
        );

        wp_register_script(
            'caretochina-pricing-script',
            CARETOCHINA_MEDICAL_URL . 'assets/js/pricing-script.js',
            [],
            CARETOCHINA_MEDICAL_VERSION,
            true
        );
    }

    /**
     * Enqueue pricing page assets
     */
    public function enqueue_assets() {
        if (!wp_style_is('font-awesome', 'registered') && !wp_style_is('font-awesome', 'enqueued')) {
            wp_enqueue_style('font-awesome', CARETOCHINA_MEDICAL_URL . 'assets/vendor/font-awesome/css/all.min.css', [], '6.4.0');
        } else {
            wp_enqueue_style('font-awesome');
        }
        wp_enqueue_style('caretochina-pricing-fonts');
        wp_enqueue_style('caretochina-pricing-style');
        wp_enqueue_script('caretochina-pricing-script');
    }

    /**
     * Helper to determine plan letter tag (e.g. PLAN A, PLAN B)
     */
    public static function get_plan_tag($pkg, $index = 0) {
        $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
        if (isset($pkg->title) && preg_match('/plan\s+([a-z0-9]+)/i', $pkg->title, $matches)) {
            return 'PLAN ' . strtoupper($matches[1]);
        }
        $idx = isset($pkg->order) && $pkg->order > 0 ? ($pkg->order - 1) : $index;
        $letter = $letters[$idx] ?? chr(65 + $index);
        return 'PLAN ' . $letter;
    }

    /**
     * Helper to get clean title without "Plan X:" prefix if redundant
     */
    public static function get_clean_title($title) {
        return preg_replace('/^plan\s+[a-z0-9]+:\s*/i', '', (string)$title);
    }

    /**
     * Helper to format matrix cell text
     */
    public static function render_matrix_cell($value, $is_highlight = false) {
        $val = trim((string)$value);
        if ($val === '1' || $val === '✓' || strtolower($val) === 'yes' || strtolower($val) === 'true') {
            return '<span class="p2-chk">✓</span>';
        }
        if ($val === '' || $val === '0' || $val === '—' || $val === '-' || strtolower($val) === 'none' || strtolower($val) === 'no') {
            return '<span class="p2-dsh">—</span>';
        }
        return esc_html($val);
    }

    /**
     * Render Pricing Cards Grid
     * Shortcode: [caretochina_pricing_cards]
     */
    public function render_cards_shortcode($atts = []) {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return '[caretochina_pricing_cards]';
        }
        $this->enqueue_assets();

        ob_start();
        $template = CARETOCHINA_MEDICAL_PATH . 'templates/pricing-cards.php';
        if (file_exists($template)) {
            include $template;
        }
        return ob_get_clean();
    }

    /**
     * Render Comparison Table (No heading/subheading)
     * Shortcode: [caretochina_comparison_table] or [caretochina_pricing_comparison]
     */
    public function render_comparison_shortcode($atts = []) {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return '[caretochina_comparison_table]';
        }
        $this->enqueue_assets();

        ob_start();
        $template = CARETOCHINA_MEDICAL_PATH . 'templates/pricing-comparison.php';
        if (file_exists($template)) {
            include $template;
        }
        return ob_get_clean();
    }

    /**
     * Render Full Package Details Bento Tabs (No heading/subheading)
     * Shortcode: [caretochina_package_details] or [caretochina_bento_details]
     */
    public function render_details_shortcode($atts = []) {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return '[caretochina_package_details]';
        }
        $this->enqueue_assets();

        ob_start();
        $template = CARETOCHINA_MEDICAL_PATH . 'templates/pricing-details.php';
        if (file_exists($template)) {
            include $template;
        }
        return ob_get_clean();
    }

    /**
     * Render Combined Pricing Suite (Cards + Comparison + Details)
     * Shortcode: [caretochina_pricing_plans]
     */
    public function render_pricing_shortcode($atts = []) {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return '[caretochina_pricing_plans]';
        }
        $this->enqueue_assets();

        ob_start();
        echo '<div class="ctc-pricing-page-wrapper ctc-pricing-scope">';
        
        $cards_tpl = CARETOCHINA_MEDICAL_PATH . 'templates/pricing-cards.php';
        if (file_exists($cards_tpl)) {
            include $cards_tpl;
        }

        $cmp_tpl = CARETOCHINA_MEDICAL_PATH . 'templates/pricing-comparison.php';
        if (file_exists($cmp_tpl)) {
            echo '<div style="margin-top: 50px;">';
            include $cmp_tpl;
            echo '</div>';
        }

        $dtl_tpl = CARETOCHINA_MEDICAL_PATH . 'templates/pricing-details.php';
        if (file_exists($dtl_tpl)) {
            echo '<div style="margin-top: 50px;">';
            include $dtl_tpl;
            echo '</div>';
        }

        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Public alias to render full pricing html
     */
    public function render_pricing_html() {
        return $this->render_pricing_shortcode();
    }
}
