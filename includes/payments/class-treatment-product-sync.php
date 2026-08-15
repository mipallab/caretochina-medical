<?php
if (!defined('ABSPATH')) {
    exit;
}

class CareToChina_Treatment_Product_Sync {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Sync product when hospital_specialty taxonomy terms are updated/created
        add_action('created_hospital_specialty', [$this, 'sync_specialty_term_to_product'], 10, 2);
        add_action('edited_hospital_specialty', [$this, 'sync_specialty_term_to_product'], 10, 2);
        add_action('delete_hospital_specialty', [$this, 'soft_delete_specialty_product'], 10, 2);
    }

    /**
     * Retrieve or create a virtual non-taxable WC_Product for a treatment/specialty
     */
    public function get_or_create_product($treatment_name, $treatment_id = 0) {
        if (!class_exists('WooCommerce')) {
            return false;
        }

        $sanitized_name = trim($treatment_name);
        if (empty($sanitized_name)) {
            $sanitized_name = __('General Medical Treatment', 'caretochina-medical');
        }

        // Query for existing synced product by meta or title
        $args = [
            'post_type'      => 'product',
            'post_status'    => ['publish', 'draft', 'private'],
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'     => '_caretochina_treatment_name',
                    'value'   => $sanitized_name,
                    'compare' => '=',
                ],
            ],
        ];

        if ($treatment_id > 0) {
            $args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key'     => '_caretochina_treatment_id',
                    'value'   => $treatment_id,
                    'compare' => '=',
                ],
                [
                    'key'     => '_caretochina_treatment_name',
                    'value'   => $sanitized_name,
                    'compare' => '=',
                ],
            ];
        }

        $products = get_posts($args);

        if (!empty($products)) {
            return wc_get_product($products[0]->ID);
        }

        // Create new virtual non-taxable WooCommerce Product
        $product = new WC_Product_Simple();
        $product->set_name($sanitized_name);
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden'); // Hide from standard WC storefront
        $product->set_virtual(true);
        $product->set_tax_status('none');
        $product->set_regular_price('0'); // Price is overridden per order at snapshot time
        $product->update_meta_data('_caretochina_treatment_name', $sanitized_name);
        if ($treatment_id > 0) {
            $product->update_meta_data('_caretochina_treatment_id', $treatment_id);
        }
        $product->save();

        return $product;
    }

    /**
     * Hook callback for hospital_specialty term save
     */
    public function sync_specialty_term_to_product($term_id, $tt_id = 0) {
        $term = get_term($term_id, 'hospital_specialty');
        if ($term && !is_wp_error($term)) {
            $this->get_or_create_product($term->name, $term->term_id);
        }
    }

    /**
     * Soft deactivate backing WC_Product when term is deleted (retains post for historical orders/refunds)
     */
    public function soft_delete_specialty_product($term_id, $tt_id = 0) {
        if (!class_exists('WooCommerce')) {
            return;
        }

        $args = [
            'post_type'      => 'product',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'     => '_caretochina_treatment_id',
                    'value'   => $term_id,
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
