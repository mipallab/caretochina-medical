<?php
if (!defined('ABSPATH')) {
    exit;
}

class CareToChina_Package_Product_Sync {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('save_post_ctc_package', [$this, 'sync_package_on_save'], 20, 2);
        add_action('transition_post_status', [$this, 'sync_package_on_status_change'], 20, 3);
        add_action('before_delete_post', [$this, 'soft_delete_package_product'], 10, 1);
    }

    /**
     * Retrieve or create a virtual non-taxable WC_Product for a Concierge Package
     */
    public function get_or_create_product($package_name, $package_id = 0) {
        if (!class_exists('WooCommerce')) {
            return false;
        }

        $sanitized_name = trim($package_name);
        if (empty($sanitized_name)) {
            $sanitized_name = __('CareToChina Concierge Package', 'caretochina-medical');
        }

        $package_id = intval($package_id);

        // Query for existing synced product by meta or title
        $args = [
            'post_type'      => 'product',
            'post_status'    => ['publish', 'draft', 'private'],
            'posts_per_page' => 1,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            'meta_query'     => [
                [
                    'key'     => '_caretochina_package_name',
                    'value'   => $sanitized_name,
                    'compare' => '=',
                ],
            ],
        ];

        if ($package_id > 0) {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            $args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key'     => '_caretochina_package_id',
                    'value'   => $package_id,
                    'compare' => '=',
                ],
                [
                    'key'     => '_caretochina_package_name',
                    'value'   => $sanitized_name,
                    'compare' => '=',
                ],
            ];
        }

        $products = get_posts($args);

        if (!empty($products)) {
            $wc_product = wc_get_product($products[0]->ID);
            if ($wc_product) {
                // Ensure name and package ID are current
                if ($package_id > 0 && $wc_product->get_meta('_caretochina_package_id') != $package_id) {
                    $wc_product->update_meta_data('_caretochina_package_id', $package_id);
                }
                if ($wc_product->get_name() !== $sanitized_name) {
                    $wc_product->set_name($sanitized_name);
                    $wc_product->save();
                }
                return $wc_product;
            }
        }

        // Create new virtual non-taxable WooCommerce Product
        $product = new WC_Product_Simple();
        $product->set_name($sanitized_name);
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden'); // Hide from standard WC storefront
        $product->set_virtual(true);
        $product->set_tax_status('none');
        $product->set_regular_price('0'); // Price is overridden per order at snapshot time
        $product->update_meta_data('_caretochina_package_name', $sanitized_name);
        if ($package_id > 0) {
            $product->update_meta_data('_caretochina_package_id', $package_id);
        }
        $product->save();

        return $product;
    }

    /**
     * Hook callback on ctc_package save
     */
    public function sync_package_on_save($post_id, $post) {
        if (!class_exists('WooCommerce') || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        $is_active = intval(get_post_meta($post_id, '_ctc_pkg_is_active', true));
        $price = floatval(get_post_meta($post_id, '_ctc_pkg_price', true));
        $product = $this->get_or_create_product($post->post_title, $post_id);

        if ($product) {
            $status = ($post->post_status === 'publish' && $is_active === 1 && $price > 0) ? 'publish' : 'draft';
            $product->set_status($status);
            $product->set_name($post->post_title);
            if ($price > 0) {
                $product->set_regular_price(strval($price));
            }
            $product->save();
        }
    }

    /**
     * Hook callback on post status change
     */
    public function sync_package_on_status_change($new_status, $old_status, $post) {
        if ($post->post_type !== 'ctc_package' || !class_exists('WooCommerce')) {
            return;
        }

        $product = $this->get_or_create_product($post->post_title, $post->ID);
        if ($product) {
            $is_active = intval(get_post_meta($post->ID, '_ctc_pkg_is_active', true));
            $price = floatval(get_post_meta($post->ID, '_ctc_pkg_price', true));
            $status = ($new_status === 'publish' && $is_active === 1 && $price > 0) ? 'publish' : 'draft';
            $product->set_status($status);
            $product->save();
        }
    }

    /**
     * Soft deactivate backing WC_Product when package is deleted (preserves historical orders)
     */
    public function soft_delete_package_product($post_id) {
        if (get_post_type($post_id) !== 'ctc_package' || !class_exists('WooCommerce')) {
            return;
        }

        $args = [
            'post_type'      => 'product',
            'posts_per_page' => 1,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            'meta_query'     => [
                [
                    'key'     => '_caretochina_package_id',
                    'value'   => $post_id,
                    'compare' => '=',
                ],
            ],
        ];

        $products = get_posts($args);
        if (!empty($products)) {
            $product = wc_get_product($products[0]->ID);
            if ($product) {
                $product->set_status('draft'); // Soft deactivate
                $product->save();
            }
        }
    }
}
