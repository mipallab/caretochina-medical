<?php
if (!defined('ABSPATH')) {
    exit;
}

class CareToChina_Pricing_Plans {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Admin Menu & Hooks
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('hospital_specialty_edit_form_fields', [$this, 'render_taxonomy_term_pricing_section'], 10, 2);

        // AJAX Endpoints: Public (Logged-in & Anonymous)
        add_action('wp_ajax_ctc_get_treatment_plans', [$this, 'handle_get_treatment_plans']);
        add_action('wp_ajax_nopriv_ctc_get_treatment_plans', [$this, 'handle_get_treatment_plans']);

        // AJAX Endpoints: Admin / Staff Management
        add_action('wp_ajax_ctc_admin_save_pricing_plan', [$this, 'handle_admin_save_plan']);
        add_action('wp_ajax_ctc_admin_delete_pricing_plan', [$this, 'handle_admin_delete_plan']);
        add_action('wp_ajax_ctc_admin_toggle_pricing_plan', [$this, 'handle_admin_toggle_plan']);
    }

    public static function get_store_currency() {
        if (function_exists('get_woocommerce_currency')) {
            return get_woocommerce_currency();
        }
        return get_option('ctc_payment_currency', 'USD');
    }

    public static function get_currency_symbol($currency = '') {
        if (empty($currency)) {
            $currency = self::get_store_currency();
        }
        if (function_exists('get_woocommerce_currency_symbol')) {
            $symbol = get_woocommerce_currency_symbol($currency);
            if (!empty($symbol)) {
                return $symbol;
            }
        }
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'CNY' => '¥',
            'RMB' => '¥',
            'JPY' => '¥',
            'AUD' => 'A$',
            'CAD' => 'C$',
            'SGD' => 'S$',
            'HKD' => 'HK$',
            'AED' => 'AED',
            'SAR' => 'SAR',
            'BDT' => '৳',
            'INR' => '₹',
            'MYR' => 'RM',
            'THB' => '฿',
            'PHP' => '₱',
            'IDR' => 'Rp',
            'VND' => '₫',
            'KRW' => '₩',
            'TRY' => '₺',
            'RUB' => '₽',
            'BRL' => 'R$',
            'ZAR' => 'R',
            'NZD' => 'NZ$',
            'CHF' => 'CHF',
        ];
        return $symbols[strtoupper($currency)] ?? strtoupper($currency);
    }

    /**
     * Fetch all plans for a specific treatment/specialty term
     */
    public function get_plans_for_treatment($treatment_id, $only_active = true) {
        global $wpdb;
        $table = $wpdb->prefix . 'caretochina_pricing_plans';
        $treatment_id = intval($treatment_id);

        if ($treatment_id <= 0) {
            return [];
        }

        $where = $only_active ? ' AND is_active = 1 ' : '';
        $sql = $wpdb->prepare(
            "SELECT * FROM $table WHERE treatment_id = %d $where ORDER BY display_order ASC, id ASC",
            $treatment_id
        );

        $results = $wpdb->get_results($sql);
        return $results ?: [];
    }

    /**
     * Get single pricing plan by ID
     */
    public function get_plan($plan_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'caretochina_pricing_plans';
        $plan_id = intval($plan_id);

        if ($plan_id <= 0) {
            return null;
        }

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $plan_id));
    }

    /**
     * Check if a treatment has at least one active plan
     */
    public function has_active_plans($treatment_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'caretochina_pricing_plans';
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE treatment_id = %d AND is_active = 1",
            intval($treatment_id)
        ));
        return intval($count) > 0;
    }

    /**
     * Check reference count in bookings and payment requests
     */
    public function get_plan_reference_count($plan_id) {
        global $wpdb;
        $plan_id = intval($plan_id);
        $tbl_bookings = $wpdb->prefix . 'caretochina_bookings';
        $tbl_requests = $wpdb->prefix . 'caretochina_payment_requests';

        $cnt_bookings = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tbl_bookings WHERE pricing_plan_id = %d", $plan_id));
        $cnt_requests = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tbl_requests WHERE pricing_plan_id = %d", $plan_id));

        return intval($cnt_bookings) + intval($cnt_requests);
    }

    /**
     * Save / Update a Pricing Plan
     */
    public function save_plan($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'caretochina_pricing_plans';

        $id           = intval($data['id'] ?? 0);
        $treatment_id = intval($data['treatment_id'] ?? 0);
        $name         = sanitize_text_field($data['name'] ?? '');
        $price        = floatval($data['price'] ?? 0.00);
        $description  = sanitize_textarea_field($data['description'] ?? '');
        $display_order= intval($data['display_order'] ?? 0);
        $is_active    = isset($data['is_active']) ? (intval($data['is_active']) ? 1 : 0) : 1;

        // SERVER-SIDE CURRENCY ENFORCEMENT: Ignore client input, strictly derive from store
        $currency     = self::get_store_currency();

        if ($treatment_id <= 0) {
            return new WP_Error('invalid_treatment', __('Please select a valid medical specialty / treatment.', 'caretochina-medical'));
        }

        if (empty($name)) {
            return new WP_Error('invalid_name', __('Pricing plan name is required.', 'caretochina-medical'));
        }

        if ($price <= 0) {
            return new WP_Error('invalid_price', __('Pricing plan amount must be greater than zero.', 'caretochina-medical'));
        }

        $payload = [
            'treatment_id' => $treatment_id,
            'name'         => $name,
            'price'        => $price,
            'currency'     => $currency,
            'description'  => $description,
            'display_order'=> $display_order,
            'is_active'    => $is_active,
        ];

        if ($id > 0) {
            $updated = $wpdb->update($table, $payload, ['id' => $id]);
            if ($updated === false) {
                return new WP_Error('db_error', __('Failed to update pricing plan.', 'caretochina-medical'));
            }
            return $this->get_plan($id);
        } else {
            $inserted = $wpdb->insert($table, $payload);
            if (!$inserted) {
                return new WP_Error('db_error', __('Failed to create pricing plan.', 'caretochina-medical'));
            }
            return $this->get_plan($wpdb->insert_id);
        }
    }

    /**
     * Delete Pricing Plan (with server-side hard-delete prevention)
     */
    public function delete_plan($plan_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'caretochina_pricing_plans';
        $plan_id = intval($plan_id);

        $plan = $this->get_plan($plan_id);
        if (!$plan) {
            return new WP_Error('not_found', __('Pricing plan not found.', 'caretochina-medical'));
        }

        $ref_count = $this->get_plan_reference_count($plan_id);
        if ($ref_count > 0) {
            return new WP_Error(
                'plan_referenced',
                sprintf(__('This pricing plan is referenced by %d existing booking(s) or payment request(s) and cannot be permanently deleted. Please deactivate it instead to preserve financial history.', 'caretochina-medical'), $ref_count)
            );
        }

        $deleted = $wpdb->delete($table, ['id' => $plan_id]);
        if (!$deleted) {
            return new WP_Error('db_error', __('Failed to delete pricing plan.', 'caretochina-medical'));
        }

        return true;
    }

    /**
     * Toggle active/inactive status
     */
    public function toggle_plan_status($plan_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'caretochina_pricing_plans';
        $plan = $this->get_plan($plan_id);
        if (!$plan) {
            return new WP_Error('not_found', __('Pricing plan not found.', 'caretochina-medical'));
        }

        $new_status = $plan->is_active ? 0 : 1;
        $wpdb->update($table, ['is_active' => $new_status], ['id' => $plan_id]);

        return $this->get_plan($plan_id);
    }

    /* =========================================================================
     * AJAX HANDLERS
     * ========================================================================= */

    /**
     * Public Read-Only Endpoint for Booking Wizard & anonymous users
     */
    public function handle_get_treatment_plans() {
        $treatment_id = intval($_GET['treatment_id'] ?? $_POST['treatment_id'] ?? 0);
        if ($treatment_id <= 0) {
            wp_send_json_error(['message' => __('Invalid treatment ID.', 'caretochina-medical')]);
        }

        $plans = $this->get_plans_for_treatment($treatment_id, true);
        wp_send_json_success([
            'treatment_id' => $treatment_id,
            'currency'     => self::get_store_currency(),
            'plans'        => $plans ?: [],
        ]);
    }

    /**
     * Admin Save Plan AJAX
     */
    public function handle_admin_save_plan() {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'caretochina_booking_nonce')) {
            wp_send_json_error(['message' => __('Security verification failed.', 'caretochina-medical')]);
        }

        if (!current_user_can('edit_posts') && !current_user_can('caretochina_manage_bookings')) {
            wp_send_json_error(['message' => __('Permission denied.', 'caretochina-medical')]);
        }

        $res = $this->save_plan($_POST);
        if (is_wp_error($res)) {
            wp_send_json_error(['message' => $res->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('Pricing plan saved successfully.', 'caretochina-medical'),
            'plan'    => $res,
        ]);
    }

    /**
     * Admin Delete Plan AJAX
     */
    public function handle_admin_delete_plan() {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'caretochina_booking_nonce')) {
            wp_send_json_error(['message' => __('Security verification failed.', 'caretochina-medical')]);
        }

        if (!current_user_can('edit_posts') && !current_user_can('caretochina_manage_bookings')) {
            wp_send_json_error(['message' => __('Permission denied.', 'caretochina-medical')]);
        }

        $plan_id = intval($_POST['plan_id'] ?? 0);
        $res = $this->delete_plan($plan_id);

        if (is_wp_error($res)) {
            wp_send_json_error([
                'message'   => $res->get_error_message(),
                'code'      => $res->get_error_code(),
                'can_deact' => ($res->get_error_code() === 'plan_referenced'),
            ]);
        }

        wp_send_json_success(['message' => __('Pricing plan deleted successfully.', 'caretochina-medical')]);
    }

    /**
     * Admin Toggle Status AJAX
     */
    public function handle_admin_toggle_plan() {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_staff_nonce') && !wp_verify_nonce($nonce, 'caretochina_booking_nonce')) {
            wp_send_json_error(['message' => __('Security verification failed.', 'caretochina-medical')]);
        }

        if (!current_user_can('edit_posts') && !current_user_can('caretochina_manage_bookings')) {
            wp_send_json_error(['message' => __('Permission denied.', 'caretochina-medical')]);
        }

        $plan_id = intval($_POST['plan_id'] ?? 0);
        $res = $this->toggle_plan_status($plan_id);

        if (is_wp_error($res)) {
            wp_send_json_error(['message' => $res->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('Status updated.', 'caretochina-medical'),
            'plan'    => $res,
        ]);
    }

    /* =========================================================================
     * ADMIN MANAGEMENT UI
     * ========================================================================= */

    public function register_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=hospital',
            __('Treatment Pricing Plans', 'caretochina-medical'),
            __('Pricing Plans', 'caretochina-medical'),
            'edit_posts',
            'caretochina-pricing-plans',
            [$this, 'render_admin_pricing_plans_page']
        );
    }

    public function render_taxonomy_term_pricing_section($term, $taxonomy) {
        $currency = self::get_store_currency();
        $plans = $this->get_plans_for_treatment($term->term_id, false);
        ?>
        <tr class="form-field">
            <th scope="row"><label><?php _e('Pricing Packages & Tiers', 'caretochina-medical'); ?></label></th>
            <td>
                <div style="background:#FFF; border:1px solid #CBD5E1; border-radius:12px; padding:16px; max-width:650px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <h4 style="margin:0; font-size:14px; color:#0F172A; font-weight:700;"><?php _e('Configured Plans for this Specialty', 'caretochina-medical'); ?></h4>
                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=hospital&page=caretochina-pricing-plans&treatment_id=' . $term->term_id)); ?>" class="button button-primary" style="background:#0F766E; border-color:#0F766E;">
                            <?php _e('Manage All Plans', 'caretochina-medical'); ?> &rarr;
                        </a>
                    </div>
                    <?php if (empty($plans)) : ?>
                        <div style="background:#FEF3C7; color:#B45309; padding:10px 14px; border-radius:8px; font-size:12px; font-weight:600;">
                            <i class="fa-solid fa-triangle-exclamation"></i> <?php _e('Warning: No active pricing plans found. This treatment will not be bookable in the public wizard until at least one plan is added.', 'caretochina-medical'); ?>
                        </div>
                    <?php else : ?>
                        <table style="width:100%; border-collapse:collapse; font-size:12px;">
                            <thead>
                                <tr style="border-bottom:1px solid #E2E8F0; text-align:left;">
                                    <th style="padding:6px;"><?php _e('Plan Name', 'caretochina-medical'); ?></th>
                                    <th style="padding:6px;"><?php _e('Price', 'caretochina-medical'); ?></th>
                                    <th style="padding:6px;"><?php _e('Status', 'caretochina-medical'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($plans as $p) : ?>
                                    <tr style="border-bottom:1px solid #F1F5F9;">
                                        <td style="padding:6px; font-weight:600;"><?php echo esc_html($p->name); ?></td>
                                        <td style="padding:6px; font-weight:700; color:#0F766E;">$<?php echo number_format((float)$p->price, 2); ?> <?php echo esc_html($p->currency); ?></td>
                                        <td style="padding:6px;">
                                            <span style="padding:2px 8px; border-radius:6px; font-size:10px; font-weight:700; <?php echo $p->is_active ? 'background:#D1FAE5; color:#065F46;' : 'background:#FEE2E2; color:#991B1B;'; ?>">
                                                <?php echo $p->is_active ? __('Active', 'caretochina-medical') : __('Inactive', 'caretochina-medical'); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php
    }

    public function render_admin_pricing_plans_page() {
        $currency = self::get_store_currency();
        $specialties = get_terms([
            'taxonomy'   => 'hospital_specialty',
            'hide_empty' => false,
        ]);

        $selected_treatment_id = intval($_GET['treatment_id'] ?? (count($specialties) > 0 ? $specialties[0]->term_id : 0));
        $plans = $selected_treatment_id > 0 ? $this->get_plans_for_treatment($selected_treatment_id, false) : [];

        // Check for any treatments with 0 active plans
        $zero_plan_treatments = [];
        if (!empty($specialties) && !is_wp_error($specialties)) {
            foreach ($specialties as $s) {
                if (!$this->has_active_plans($s->term_id)) {
                    $zero_plan_treatments[] = $s->name;
                }
            }
        }
        ?>
        <div class="wrap" style="font-family:'Inter', sans-serif; max-width:1100px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h1 class="wp-heading-inline" style="font-weight:800; color:#0F172A; display:flex; align-items:center; gap:10px;">
                    <i class="fa-solid fa-tags" style="color:#0F766E;"></i> <?php _e('Treatment Pricing Plans & Packages', 'caretochina-medical'); ?>
                </h1>
                <button type="button" class="button button-primary button-hero" onclick="openPlanModal()" style="background:#0F766E; border-color:#0F766E; font-weight:700; border-radius:8px;">
                    <i class="fa-solid fa-plus-circle"></i> + <?php _e('Add New Pricing Plan', 'caretochina-medical'); ?>
                </button>
            </div>
            <hr class="wp-header-end">

            <?php if (!empty($zero_plan_treatments)) : ?>
                <div class="notice notice-warning" style="border-left-color:#F59E0B; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-size:13px;">
                    <p style="margin:0; font-weight:600; color:#B45309;">
                        <i class="fa-solid fa-triangle-exclamation"></i> 
                        <strong><?php _e('Action Required:', 'caretochina-medical'); ?></strong> 
                        <?php printf(__('The following treatment(s) have <strong>zero active pricing plans</strong> and cannot be booked in the public widget: %s. Add at least one active plan for each.', 'caretochina-medical'), esc_html(implode(', ', $zero_plan_treatments))); ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- SPECIALTY FILTER BAR -->
            <div style="background:#FFF; border:1px solid #E2E8F0; border-radius:12px; padding:16px; margin-bottom:24px; display:flex; align-items:center; justify-content:space-between; gap:16px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                <div style="display:flex; align-items:center; gap:12px;">
                    <label style="font-weight:700; color:#334155; font-size:13px;"><?php _e('Select Medical Specialty / Treatment:', 'caretochina-medical'); ?></label>
                    <select id="admin_treatment_filter" onchange="window.location.href='edit.php?post_type=hospital&page=caretochina-pricing-plans&treatment_id=' + this.value" style="padding:6px 12px; border-radius:8px; border:1px solid #CBD5E1; font-weight:600; font-size:13px;">
                        <?php if (!empty($specialties) && !is_wp_error($specialties)) : ?>
                            <?php foreach ($specialties as $s) : ?>
                                <option value="<?php echo esc_attr($s->term_id); ?>" <?php selected($selected_treatment_id, $s->term_id); ?>>
                                    <?php echo esc_html($s->name); ?> (<?php echo count($this->get_plans_for_treatment($s->term_id, false)); ?> plans)
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div>
                    <span style="background:#F1F5F9; color:#475569; padding:6px 12px; border-radius:8px; font-size:12px; font-weight:700;">
                        <i class="fa-solid fa-coins"></i> <?php _e('Store Currency:', 'caretochina-medical'); ?> <strong><?php echo esc_html($currency); ?></strong>
                    </span>
                </div>
            </div>

            <!-- PLANS TABLE -->
            <div style="background:#FFF; border:1px solid #E2E8F0; border-radius:12px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                <table class="wp-list-table widefat fixed striped" style="border:none;">
                    <thead>
                        <tr style="background:#0F172A; color:#FFF;">
                            <th style="color:#FFF; font-weight:700; width:50px; text-align:center;">#</th>
                            <th style="color:#FFF; font-weight:700;"><?php _e('Plan Name / Package', 'caretochina-medical'); ?></th>
                            <th style="color:#FFF; font-weight:700; width:140px;"><?php _e('Locked Price', 'caretochina-medical'); ?></th>
                            <th style="color:#FFF; font-weight:700;"><?php _e('Description / Scope', 'caretochina-medical'); ?></th>
                            <th style="color:#FFF; font-weight:700; width:90px; text-align:center;"><?php _e('Status', 'caretochina-medical'); ?></th>
                            <th style="color:#FFF; font-weight:700; width:100px; text-align:center;"><?php _e('Usage', 'caretochina-medical'); ?></th>
                            <th style="color:#FFF; font-weight:700; width:160px; text-align:right;"><?php _e('Actions', 'caretochina-medical'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($plans)) : ?>
                            <tr>
                                <td colspan="7" style="text-align:center; padding:32px; color:#64748B;">
                                    <i class="fa-solid fa-tags" style="font-size:32px; color:#CBD5E1; margin-bottom:10px; display:block;"></i>
                                    <?php _e('No pricing plans found for this treatment. Click "+ Add New Pricing Plan" to create one.', 'caretochina-medical'); ?>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($plans as $p) : 
                                $ref_count = $this->get_plan_reference_count($p->id);
                                ?>
                                <tr id="plan-row-<?php echo esc_attr($p->id); ?>">
                                    <td style="text-align:center; font-weight:700; color:#64748B;"><?php echo esc_html($p->display_order); ?></td>
                                    <td>
                                        <strong style="color:#0F172A; font-size:14px;"><?php echo esc_html($p->name); ?></strong>
                                    </td>
                                    <td>
                                        <span style="font-size:15px; font-weight:800; color:#0F766E;">
                                            $<?php echo number_format((float)$p->price, 2); ?>
                                        </span>
                                        <span style="font-size:11px; color:#64748B; font-weight:700;"><?php echo esc_html($p->currency); ?></span>
                                    </td>
                                    <td style="color:#475569; font-size:13px;">
                                        <?php echo esc_html($p->description ?: '—'); ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <button type="button" onclick="togglePlanStatus(<?php echo esc_attr($p->id); ?>)" class="button button-small" style="font-weight:700; border-radius:6px; <?php echo $p->is_active ? 'background:#D1FAE5; color:#065F46; border-color:#A7F3D0;' : 'background:#FEE2E2; color:#991B1B; border-color:#FECACA;'; ?>">
                                            <?php echo $p->is_active ? __('Active', 'caretochina-medical') : __('Inactive', 'caretochina-medical'); ?>
                                        </button>
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="badge" style="background:#F1F5F9; color:#475569; padding:3px 8px; border-radius:6px; font-size:11px; font-weight:700;" title="<?php _e('Total bookings and payment requests referencing this plan', 'caretochina-medical'); ?>">
                                            <?php echo $ref_count; ?> refs
                                        </span>
                                    </td>
                                    <td style="text-align:right;">
                                        <button type="button" class="button button-small" onclick='editPlan(<?php echo esc_attr(json_encode($p)); ?>)' style="margin-right:4px;">
                                            <i class="fa-solid fa-pen"></i> <?php _e('Edit', 'caretochina-medical'); ?>
                                        </button>
                                        <button type="button" class="button button-small button-link-delete" onclick="deletePlan(<?php echo esc_attr($p->id); ?>, <?php echo $ref_count; ?>)" style="color:#EF4444;">
                                            <i class="fa-solid fa-trash-can"></i> <?php echo $ref_count > 0 ? __('Deactivate', 'caretochina-medical') : __('Delete', 'caretochina-medical'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- MODAL: ADD / EDIT PRICING PLAN -->
            <div id="plan-editor-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.65); z-index:99999; align-items:center; justify-content:center; padding:20px; box-sizing:border-box;">
                <div style="background:#FFF; border-radius:16px; max-width:500px; width:100%; padding:24px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); font-family:'Inter', sans-serif;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid #E2E8F0; padding-bottom:12px;">
                        <h3 id="modal-title" style="margin:0; font-size:16px; font-weight:800; color:#0F172A;"><?php _e('Add New Pricing Plan', 'caretochina-medical'); ?></h3>
                        <button type="button" onclick="jQuery('#plan-editor-modal').hide()" style="background:none; border:none; font-size:18px; color:#94A3B8; cursor:pointer;">&times;</button>
                    </div>

                    <form id="plan-editor-form">
                        <input type="hidden" name="id" id="plan_id" value="0">
                        <input type="hidden" name="treatment_id" id="plan_treatment_id" value="<?php echo esc_attr($selected_treatment_id); ?>">

                        <div style="margin-bottom:14px;">
                            <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;"><?php _e('Plan Name / Tier Title *', 'caretochina-medical'); ?></label>
                            <input type="text" name="name" id="plan_name" class="regular-text" style="width:100%; padding:8px; border-radius:6px; border:1px solid #CBD5E1;" placeholder="e.g. Standard Consultation or VIP Surgery Package" required>
                        </div>

                        <div style="display:grid; grid-template-columns:2fr 1fr; gap:12px; margin-bottom:14px;">
                            <div>
                                <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;"><?php _e('Package Price *', 'caretochina-medical'); ?></label>
                                <input type="number" step="0.01" name="price" id="plan_price" style="width:100%; padding:8px; border-radius:6px; border:1px solid #CBD5E1;" placeholder="150.00" required>
                            </div>
                            <div>
                                <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;"><?php _e('Store Currency (Locked)', 'caretochina-medical'); ?></label>
                                <input type="text" value="<?php echo esc_attr($currency); ?>" readonly style="width:100%; padding:8px; border-radius:6px; border:1px solid #E2E8F0; background:#F8FAFC; color:#64748B; font-weight:700; cursor:not-allowed;">
                            </div>
                        </div>

                        <div style="margin-bottom:14px;">
                            <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;"><?php _e('Description / Inclusions (Optional)', 'caretochina-medical'); ?></label>
                            <textarea name="description" id="plan_description" rows="3" style="width:100%; padding:8px; border-radius:6px; border:1px solid #CBD5E1;" placeholder="Includes doctor fee, follow-up consultation, hospital admission..."></textarea>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:20px;">
                            <div>
                                <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;"><?php _e('Display Order', 'caretochina-medical'); ?></label>
                                <input type="number" name="display_order" id="plan_order" value="0" style="width:100%; padding:8px; border-radius:6px; border:1px solid #CBD5E1;">
                            </div>
                            <div>
                                <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;"><?php _e('Active Status', 'caretochina-medical'); ?></label>
                                <select name="is_active" id="plan_active" style="width:100%; padding:8px; border-radius:6px; border:1px solid #CBD5E1;">
                                    <option value="1"><?php _e('Active (Bookable)', 'caretochina-medical'); ?></option>
                                    <option value="0"><?php _e('Inactive (Hidden)', 'caretochina-medical'); ?></option>
                                </select>
                            </div>
                        </div>

                        <div style="display:flex; justify-content:flex-end; gap:10px;">
                            <button type="button" onclick="jQuery('#plan-editor-modal').hide()" class="button"><?php _e('Cancel', 'caretochina-medical'); ?></button>
                            <button type="submit" id="btn-save-plan" class="button button-primary" style="background:#0F766E; border-color:#0F766E;">
                                <?php _e('Save Pricing Plan', 'caretochina-medical'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        var staffNonce = '<?php echo esc_js(wp_create_nonce("caretochina_staff_nonce")); ?>';

        function openPlanModal() {
            jQuery('#plan-editor-form')[0].reset();
            jQuery('#plan_id').val(0);
            jQuery('#plan_treatment_id').val('<?php echo esc_js($selected_treatment_id); ?>');
            jQuery('#modal-title').text('<?php _e("Add New Pricing Plan", "caretochina-medical"); ?>');
            jQuery('#plan-editor-modal').css('display', 'flex');
        }

        function editPlan(plan) {
            jQuery('#plan_id').val(plan.id);
            jQuery('#plan_treatment_id').val(plan.treatment_id);
            jQuery('#plan_name').val(plan.name);
            jQuery('#plan_price').val(plan.price);
            jQuery('#plan_description').val(plan.description);
            jQuery('#plan_order').val(plan.display_order);
            jQuery('#plan_active').val(plan.is_active);
            jQuery('#modal-title').text('<?php _e("Edit Pricing Plan", "caretochina-medical"); ?>');
            jQuery('#plan-editor-modal').css('display', 'flex');
        }

        function togglePlanStatus(planId) {
            jQuery.post(ajaxurl, {
                action: 'ctc_admin_toggle_pricing_plan',
                nonce: staffNonce,
                plan_id: planId
            }, function(res) {
                if (res.success) {
                    window.location.reload();
                } else {
                    alert((res.data && res.data.message) || 'Failed to toggle status.');
                }
            });
        }

        function deletePlan(planId, refCount) {
            if (refCount > 0) {
                if (confirm('<?php _e("This plan has existing bookings/requests and cannot be permanently deleted. Would you like to deactivate it instead?", "caretochina-medical"); ?>')) {
                    togglePlanStatus(planId);
                }
                return;
            }

            if (!confirm('<?php _e("Are you sure you want to permanently delete this pricing plan?", "caretochina-medical"); ?>')) {
                return;
            }

            jQuery.post(ajaxurl, {
                action: 'ctc_admin_delete_pricing_plan',
                nonce: staffNonce,
                plan_id: planId
            }, function(res) {
                if (res.success) {
                    window.location.reload();
                } else {
                    alert((res.data && res.data.message) || 'Failed to delete plan.');
                }
            });
        }

        jQuery('#plan-editor-form').on('submit', function(e) {
            e.preventDefault();
            var $btn = jQuery('#btn-save-plan');
            $btn.prop('disabled', true).text('<?php _e("Saving...", "caretochina-medical"); ?>');

            var postData = jQuery(this).serializeArray();
            postData.push({ name: 'action', value: 'ctc_admin_save_pricing_plan' });
            postData.push({ name: 'nonce', value: staffNonce });

            jQuery.post(ajaxurl, postData, function(res) {
                $btn.prop('disabled', false).text('<?php _e("Save Pricing Plan", "caretochina-medical"); ?>');
                if (res.success) {
                    jQuery('#plan-editor-modal').hide();
                    window.location.reload();
                } else {
                    alert((res.data && res.data.message) || 'Failed to save pricing plan.');
                }
            }).fail(function() {
                $btn.prop('disabled', false).text('<?php _e("Save Pricing Plan", "caretochina-medical"); ?>');
                alert('Server error saving plan.');
            });
        });
        </script>
        <?php
    }
}
