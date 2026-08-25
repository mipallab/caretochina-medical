<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * CareToChina WooCommerce Admin Menu Cleanup
 *
 * This class is strictly a presentational/UI cleanup layer for the WordPress admin sidebar.
 * It hides the entire top-level WooCommerce admin menu along with Products, Analytics, and
 * Marketing for all users (including administrators), providing a streamlined medical UI.
 *
 * CRITICAL ARCHITECTURAL GUARANTEES:
 * - This class does NOT unregister, deregister, or disable the 'product' post type, 'shop_order'
 *   post type, HPOS orders table, or any WooCommerce core data structures or REST APIs.
 * - Medical treatments sync directly to WC_Product instances, and bookings create WC_Order instances.
 * - Gateway processing (Stripe, PayPal), webhook handling, refunds, and background reconciliation
 *   continue to operate identically regardless of sidebar menu visibility.
 * - This cleanup is purely cosmetic and does not alter WooCommerce backend execution or loading overhead.
 * - Because WooCommerce menu slugs/titles can evolve across major releases, menu removal uses both
 *   primary slug matching and fallback title matching, accompanied by a lightweight self-check.
 */
class CareToChina_WooCommerce_Admin_Cleanup {

    private static $instance = null;

    const WARNING_TRANSIENT = 'ctc_wc_menu_cleanup_warning';

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Run at late priority 999 after WooCommerce finishes registering all menus (default WC priority is 50-60)
        add_action('admin_menu', [$this, 'clean_admin_menus'], 999);
        add_action('admin_notices', [$this, 'display_fallback_warning_notice']);
    }

    /**
     * Clean and hide unused WooCommerce top-level wp-admin menus for all users
     */
    public function clean_admin_menus() {
        global $menu;

        if (!is_array($menu)) {
            return;
        }

        // 1. FAST-PATH: Known WooCommerce top-level slugs (Hidden for all users, including administrators)
        $always_hide_top_slugs = [
            'woocommerce',
            'edit.php?post_type=product',
            'wc-admin&path=/analytics/overview',
            'analytics',
            'woocommerce-marketing',
            'wc-admin&path=/marketing',
            'edit.php?post_type=shop_order',
            'wc-orders',
            'admin.php?page=wc-orders',
        ];

        foreach ($always_hide_top_slugs as $slug) {
            remove_menu_page($slug);
        }

        // 2. FALLBACK MATCHING: Iterate top-level $menu by title text
        // Ensures resilience if WooCommerce alters internal top-level slugs across major releases
        $this->filter_menus_by_title();

        // 3. SELF-CHECK: Verify targeted top-level items are actually gone
        $this->perform_self_check();
    }

    /**
     * Fallback title-based menu filter for top-level menu entries
     */
    private function filter_menus_by_title() {
        global $menu;

        $target_keywords = ['woocommerce', 'product', 'analytics', 'marketing'];

        if (is_array($menu)) {
            foreach ($menu as $idx => $item) {
                if (!isset($item[0], $item[2])) {
                    continue;
                }

                $slug = $item[2];

                // NEVER hide CareToChina plugin menus
                if (strpos($slug, 'caretochina-medical') !== false) {
                    continue;
                }

                $raw_title = wp_strip_all_tags($item[0]);
                $clean_title = strtolower(trim(html_entity_decode($raw_title, ENT_QUOTES, 'UTF-8')));

                foreach ($target_keywords as $kw) {
                    if (strpos($clean_title, $kw) !== false || strpos($slug, $kw) !== false) {
                        remove_menu_page($slug);
                        unset($menu[$idx]);
                        break;
                    }
                }
            }
        }
    }

    /**
     * Self-check: verify whether targeted top-level items remain in $menu
     */
    private function perform_self_check() {
        global $menu;

        $unhidden_items = [];
        $target_keywords = ['woocommerce', 'product', 'analytics', 'marketing'];

        if (is_array($menu)) {
            foreach ($menu as $item) {
                if (!isset($item[0], $item[2])) {
                    continue;
                }

                $slug = $item[2];

                // Ignore CareToChina plugin menus
                if (strpos($slug, 'caretochina-medical') !== false) {
                    continue;
                }

                $raw_title = wp_strip_all_tags($item[0]);
                $clean_title = strtolower(trim(html_entity_decode($raw_title, ENT_QUOTES, 'UTF-8')));

                foreach ($target_keywords as $kw) {
                    if (strpos($clean_title, $kw) !== false || strpos($slug, $kw) !== false) {
                        $unhidden_items[] = esc_html($raw_title) . ' (' . esc_html($slug) . ')';
                        break;
                    }
                }
            }
        }

        if (!empty($unhidden_items)) {
            set_transient(self::WARNING_TRANSIENT, implode(', ', $unhidden_items), DAY_IN_SECONDS);
        } else {
            delete_transient(self::WARNING_TRANSIENT);
        }
    }

    /**
     * Surface dismissible admin notice if self-check caught an unhidden top-level menu item.
     * Gated strictly to administrators (manage_options) only.
     */
    public function display_fallback_warning_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $warning = get_transient(self::WARNING_TRANSIENT);
        if ($warning) {
            echo '<div class="notice notice-warning is-dismissible"><p><strong>' . esc_html__('CareToChina Medical Suite Notice:', 'caretochina-medical') . '</strong> ' .
                esc_html__('WooCommerce\'s menu structure may have changed after an update — please verify the hidden menu settings still work as expected.', 'caretochina-medical') .
                ' <span style="color:#64748B; font-size:12px;">(' . esc_html(/* translators: %s: dynamic value */
sprintf(__('Detected items: %s', 'caretochina-medical'), $warning)) . ')</span></p></div>';
        }
    }
}
