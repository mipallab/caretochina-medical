<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * CareToChina WooCommerce Admin Menu Cleanup
 *
 * This class is strictly a presentational/UI cleanup layer for the WordPress admin sidebar.
 * It hides unused WooCommerce navigation menus (Products, Analytics, Marketing) for all users,
 * and restricts Orders navigation to Administrators only (for raw WC_Order inspection & debugging).
 *
 * CRITICAL ARCHITECTURAL GUARANTEES:
 * - This class does NOT unregister, deregister, or disable the 'product' post type, 'shop_order'
 *   post type, HPOS orders table, or any WooCommerce core data structures or REST APIs.
 * - Medical treatments sync directly to WC_Product instances, and bookings create WC_Order instances.
 * - Gateway processing (Stripe, PayPal), webhook handling, refunds, and background reconciliation
 *   continue to operate identically regardless of sidebar menu visibility.
 * - This cleanup is purely cosmetic and does not alter WooCommerce backend execution or loading overhead.
 * - Because WooCommerce menu slugs/titles can evolve across major releases, menu removal uses both
 *   primary slug matching and fallback title matching, accompanied by a role-aware self-check.
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
     * Clean and hide unused WooCommerce wp-admin menus
     */
    public function clean_admin_menus() {
        global $menu, $submenu;

        if (!is_array($menu)) {
            return;
        }

        $is_admin = current_user_can('manage_options');

        // 1. FAST-PATH: Known WooCommerce top-level slugs (Always hidden for all users)
        $always_hide_top_slugs = [
            'edit.php?post_type=product',
            'wc-admin&path=/analytics/overview',
            'analytics',
            'woocommerce-marketing',
            'wc-admin&path=/marketing',
        ];

        foreach ($always_hide_top_slugs as $slug) {
            remove_menu_page($slug);
        }

        // Fast-path submenus under 'woocommerce' parent that should be hidden for all users
        $always_hide_wc_submenus = [
            'edit.php?post_type=shop_coupon',
            'wc-admin&path=/analytics/overview',
            'wc-admin&path=/marketing',
        ];

        foreach ($always_hide_wc_submenus as $sub_slug) {
            remove_submenu_page('woocommerce', $sub_slug);
        }

        // Orders: Hidden from non-administrators only (kept visible for administrators as fallback/debugging view)
        if (!$is_admin) {
            remove_submenu_page('woocommerce', 'edit.php?post_type=shop_order');
            remove_submenu_page('woocommerce', 'wc-orders');
            remove_submenu_page('woocommerce', 'admin.php?page=wc-orders');
            remove_menu_page('edit.php?post_type=shop_order');
            remove_menu_page('wc-orders');
            remove_menu_page('admin.php?page=wc-orders');
        }

        // 2. FALLBACK MATCHING: Iterate $menu and $submenu by title text
        // Ensures resilience if WooCommerce alters internal slugs across major version updates
        $this->filter_menus_by_title($is_admin);

        // 3. ROLE-AWARE SELF-CHECK: Verify targeted items are actually gone
        $this->perform_self_check($is_admin);
    }

    /**
     * Fallback title-based menu filter
     *
     * @param bool $is_admin
     */
    private function filter_menus_by_title($is_admin) {
        global $menu, $submenu;

        $top_level_keywords = ['product', 'analytics', 'marketing'];

        // Filter top-level $menu
        if (is_array($menu)) {
            foreach ($menu as $idx => $item) {
                if (!isset($item[0], $item[2])) {
                    continue;
                }
                $raw_title = strip_tags($item[0]);
                $clean_title = strtolower(trim(html_entity_decode($raw_title, ENT_QUOTES, 'UTF-8')));
                $slug = $item[2];

                // NEVER hide WooCommerce main menu or WooCommerce Settings
                if ($slug === 'woocommerce' || strpos($slug, 'wc-settings') !== false || ($slug === 'wc-settings' && strpos($clean_title, 'setting') !== false)) {
                    continue;
                }

                // If non-admin and an Orders menu is registered as top-level, hide it
                if (!$is_admin && (strpos($clean_title, 'order') !== false || strpos($slug, 'shop_order') !== false || strpos($slug, 'wc-orders') !== false)) {
                    remove_menu_page($slug);
                    unset($menu[$idx]);
                    continue;
                }

                // Hide Products, Analytics, Marketing
                foreach ($top_level_keywords as $kw) {
                    if (strpos($clean_title, $kw) !== false || strpos($slug, $kw) !== false) {
                        remove_menu_page($slug);
                        unset($menu[$idx]);
                        break;
                    }
                }
            }
        }

        // Filter submenus under 'woocommerce'
        if (isset($submenu['woocommerce']) && is_array($submenu['woocommerce'])) {
            foreach ($submenu['woocommerce'] as $sub_idx => $sub_item) {
                if (!isset($sub_item[0], $sub_item[2])) {
                    continue;
                }
                $raw_title = strip_tags($sub_item[0]);
                $clean_title = strtolower(trim(html_entity_decode($raw_title, ENT_QUOTES, 'UTF-8')));
                $sub_slug = $sub_item[2];

                // NEVER hide Settings (wc-settings) or its sub-tabs
                if ($sub_slug === 'wc-settings' || strpos($clean_title, 'setting') !== false) {
                    continue;
                }

                // Hide Analytics, Marketing, Coupons submenus if any remain under 'woocommerce'
                if (strpos($clean_title, 'analytic') !== false || strpos($clean_title, 'marketing') !== false || strpos($clean_title, 'coupon') !== false) {
                    remove_submenu_page('woocommerce', $sub_slug);
                    unset($submenu['woocommerce'][$sub_idx]);
                    continue;
                }

                // Hide Orders for non-administrators
                if (!$is_admin && (strpos($clean_title, 'order') !== false || strpos($sub_slug, 'order') !== false)) {
                    remove_submenu_page('woocommerce', $sub_slug);
                    unset($submenu['woocommerce'][$sub_idx]);
                    continue;
                }
            }
        }
    }

    /**
     * Role-aware self-check: verify whether targeted items remain in menu structures
     *
     * When running in an Administrator context, Orders is INTENTIONALLY kept visible
     * and is explicitly excluded from unhidden detection to prevent false warnings.
     *
     * @param bool $is_admin
     */
    private function perform_self_check($is_admin) {
        global $menu, $submenu;

        $unhidden_items = [];

        // Check top-level $menu for Products, Analytics, Marketing
        if (is_array($menu)) {
            foreach ($menu as $item) {
                if (!isset($item[0], $item[2])) {
                    continue;
                }
                $raw_title = strip_tags($item[0]);
                $clean_title = strtolower(trim(html_entity_decode($raw_title, ENT_QUOTES, 'UTF-8')));
                $slug = $item[2];

                // Ignore core allowed items
                if ($slug === 'woocommerce' || strpos($slug, 'wc-settings') !== false) {
                    continue;
                }

                // If non-admin, also check top-level Orders
                if (!$is_admin && (strpos($clean_title, 'order') !== false || strpos($slug, 'shop_order') !== false || strpos($slug, 'wc-orders') !== false)) {
                    $unhidden_items[] = esc_html($raw_title) . ' (' . esc_html($slug) . ')';
                }

                if (
                    strpos($clean_title, 'product') !== false || strpos($slug, 'post_type=product') !== false ||
                    strpos($clean_title, 'analytic') !== false || strpos($slug, 'analytics') !== false ||
                    strpos($clean_title, 'marketing') !== false || strpos($slug, 'marketing') !== false
                ) {
                    $unhidden_items[] = esc_html($raw_title) . ' (' . esc_html($slug) . ')';
                }
            }
        }

        // Check submenus under 'woocommerce'
        if (isset($submenu['woocommerce']) && is_array($submenu['woocommerce'])) {
            foreach ($submenu['woocommerce'] as $sub_item) {
                if (!isset($sub_item[0], $sub_item[2])) {
                    continue;
                }
                $raw_title = strip_tags($sub_item[0]);
                $clean_title = strtolower(trim(html_entity_decode($raw_title, ENT_QUOTES, 'UTF-8')));
                $slug = $sub_item[2];

                // Settings is explicitly allowed
                if ($slug === 'wc-settings' || strpos($clean_title, 'setting') !== false) {
                    continue;
                }

                // Check for lingering Analytics/Marketing/Coupons submenus
                if (
                    strpos($clean_title, 'analytic') !== false || strpos($slug, 'analytics') !== false ||
                    strpos($clean_title, 'marketing') !== false || strpos($slug, 'marketing') !== false ||
                    strpos($clean_title, 'coupon') !== false || strpos($slug, 'shop_coupon') !== false
                ) {
                    $unhidden_items[] = 'Submenu: ' . esc_html($raw_title) . ' (' . esc_html($slug) . ')';
                }

                // Check for lingering Orders ONLY if user is NOT an administrator
                if (!$is_admin && (strpos($clean_title, 'order') !== false || strpos($slug, 'order') !== false)) {
                    $unhidden_items[] = 'Submenu: ' . esc_html($raw_title) . ' (' . esc_html($slug) . ')';
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
     * Surface dismissible admin notice if self-check caught an unhidden menu item
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
                ' <span style="color:#64748B; font-size:12px;">(' . esc_html(sprintf(__('Detected items: %s', 'caretochina-medical'), $warning)) . ')</span></p></div>';
        }
    }
}
