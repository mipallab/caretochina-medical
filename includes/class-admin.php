<?php
if (!defined('ABSPATH')) {
    exit;
}

class CareToChina_Booking_Admin {
    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('wp_ajax_caretochina_admin_update_status', [$this, 'handle_update_status']);
        add_action('wp_ajax_caretochina_admin_add_booking', [$this, 'handle_add_booking']);
        add_action('wp_ajax_caretochina_admin_get_bookings', [$this, 'handle_get_admin_bookings']);
        add_action('wp_ajax_caretochina_admin_delete_booking', [$this, 'handle_delete_booking']);
        add_action('wp_ajax_caretochina_admin_get_booking_details', [$this, 'handle_get_booking_details']);

        // Legacy aliases
        add_action('wp_ajax_careyou_admin_update_status', [$this, 'handle_update_status']);
        add_action('wp_ajax_careyou_admin_add_booking', [$this, 'handle_add_booking']);
        add_action('wp_ajax_careyou_admin_get_bookings', [$this, 'handle_get_admin_bookings']);
    }

    public function register_admin_menu() {
        add_menu_page(
            __('Medical Bookings', 'caretochina-medical'),
            __('Bookings Manager', 'caretochina-medical'),
            'manage_options',
            'caretochina-bookings',
            [$this, 'render_admin_page'],
            'dashicons-clipboard',
            25
        );

        // Alias redirection menu page
        add_submenu_page(
            null,
            'CareYou Bookings Alias',
            'CareYou Bookings Alias',
            'manage_options',
            'careyou-bookings',
            [$this, 'render_admin_page']
        );
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized user access.', 'caretochina-medical'));
        }

        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        if (!empty($search)) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $bookings = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}caretochina_bookings WHERE full_name LIKE %s OR booking_code LIKE %s OR email LIKE %s OR phone LIKE %s ORDER BY id DESC",
                $like, $like, $like, $like
            ));
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $bookings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}caretochina_bookings ORDER BY id DESC");
        }
        ?>
        <style>
        .ctc-admin-dashboard {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 20px 20px 0 2px;
            max-width: 100%;
            box-sizing: border-box;
        }
        .ctc-admin-table-container {
            overflow-x: auto;
            width: 100%;
            background: #FFFFFF;
            border-radius: 14px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #E2E8F0;
            margin-top: 20px;
            box-sizing: border-box;
        }
        .ctc-admin-custom-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            background: #FFFFFF;
        }
        .ctc-admin-custom-table th {
            background: #0F172A !important;
            color: #FFFFFF !important;
            font-family: 'Manrope', sans-serif !important;
            font-weight: 700 !important;
            font-size: 13px !important;
            padding: 14px 16px !important;
            text-align: left !important;
            vertical-align: middle !important;
            border: none !important;
            white-space: nowrap !important;
        }
        .ctc-admin-custom-table td {
            padding: 14px 16px !important;
            vertical-align: middle !important;
            font-size: 13.5px !important;
            color: #334155 !important;
            border-bottom: 1px solid #F1F5F9 !important;
            border-top: none !important;
        }
        .ctc-admin-custom-table tbody tr:hover {
            background-color: #F8FAFC !important;
        }
        .ctc-admin-status-select {
            appearance: none !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            background: #FFFFFF url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%230F766E' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e") no-repeat right 10px center / 12px !important;
            padding: 6px 30px 6px 12px !important;
            border-radius: 8px !important;
            font-size: 12.5px !important;
            font-weight: 700 !important;
            border: 1.5px solid #CBD5E1 !important;
            cursor: pointer !important;
            vertical-align: middle !important;
            line-height: 1.4 !important;
            outline: none !important;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05) !important;
            transition: all 0.2s ease !important;
            min-width: 110px !important;
        }
        .ctc-admin-status-select.status-pending {
            border-color: #F59E0B !important;
            color: #B45309 !important;
            background-color: #FEF3C7 !important;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23B45309' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e") !important;
        }
        .ctc-admin-status-select.status-confirmed {
            border-color: #10B981 !important;
            color: #047857 !important;
            background-color: #D1FAE5 !important;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23047857' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e") !important;
        }
        .ctc-admin-status-select.status-completed {
            border-color: #0F766E !important;
            color: #0F766E !important;
            background-color: #CCFBF1 !important;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%230F766E' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e") !important;
        }
        .ctc-admin-status-select.status-cancelled {
            border-color: #EF4444 !important;
            color: #B91C1C !important;
            background-color: #FEE2E2 !important;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23B91C1C' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e") !important;
        }
        .ctc-btn-action {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 32px !important;
            height: 32px !important;
            border-radius: 8px !important;
            border: 1px solid #CBD5E1 !important;
            background: #FFFFFF !important;
            color: #475569 !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            font-size: 13px !important;
            text-decoration: none !important;
            vertical-align: middle !important;
        }
        .ctc-btn-action:hover {
            background: #F1F5F9 !important;
            color: #0F172A !important;
        }
        .ctc-btn-action.action-view:hover {
            border-color: #3B82F6 !important;
            color: #2563EB !important;
            background: #EFF6FF !important;
        }
        .ctc-btn-action.action-desk {
            border-color: #0F766E !important;
            color: #FFFFFF !important;
            background: #0F766E !important;
        }
        .ctc-btn-action.action-desk:hover {
            background: #0D645C !important;
        }
        .ctc-btn-action.action-delete:hover {
            border-color: #EF4444 !important;
            color: #DC2626 !important;
            background: #FEF2F2 !important;
        }
        </style>

        <div class="wrap ctc-admin-dashboard careyou-admin-dashboard caretochina-admin-dashboard">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
                <h1 class="wp-heading-inline" style="margin:0; font-family:'Manrope', sans-serif; font-size:22px; font-weight:800; color:#0F172A;">
                    <i class="fa-solid fa-heart-pulse" style="color:#0F766E;"></i> <?php esc_html_e('CareToChina Medical Booking Manager', 'caretochina-medical'); ?>
                </h1>
                <button type="button" class="button button-primary button-hero" onclick="openAdminAddBookingModal()" style="background:#0F766E; border-color:#0F766E; font-weight:700; border-radius:10px; padding:8px 20px; display:inline-flex; align-items:center; gap:8px;">
                    <i class="fa-solid fa-plus-circle"></i> <?php esc_html_e('Add New Booking', 'caretochina-medical'); ?>
                </button>
            </div>
            <hr class="wp-header-end" style="margin-bottom:16px;">

            <!-- SEARCH BAR -->
            <form method="GET" id="ctc-admin-search-form" style="margin-bottom:16px; display:flex; gap:10px; flex-wrap:wrap;">
                <input type="hidden" name="page" value="caretochina-bookings">
                <input type="text" name="s" id="ctc-admin-search-input" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_html_e('Search by patient name, email, phone or code...', 'caretochina-medical'); ?>" style="width:340px; max-width:100%; padding:9px 14px; border-radius:8px; border:1px solid #cbd5e1; font-size:13.5px;" onkeyup="handleAdminSearchKeyup(this.value)">
                <button type="submit" class="button button-secondary" style="padding:4px 16px; font-weight:600;"><?php esc_html_e('Search Bookings', 'caretochina-medical'); ?></button>
                <?php if (!empty($search)) : ?>
                    <a href="admin.php?page=caretochina-bookings" class="button" style="padding:4px 14px;"><?php esc_html_e('Reset', 'caretochina-medical'); ?></a>
                <?php endif; ?>
            </form>

            <!-- BOOKINGS LIST TABLE -->
            <div class="ctc-admin-table-container">
                <table class="wp-list-table widefat striped table-view-list ctc-admin-custom-table">
                    <thead>
                        <tr>
                            <th style="width:115px;"><?php esc_html_e('Code', 'caretochina-medical'); ?></th>
                            <th style="width:160px;"><?php esc_html_e('Patient Name', 'caretochina-medical'); ?></th>
                            <th style="width:200px;"><?php esc_html_e('Email & Phone', 'caretochina-medical'); ?></th>
                            <th style="width:170px;"><?php esc_html_e('Specialty', 'caretochina-medical'); ?></th>
                            <th style="width:180px;"><?php esc_html_e('Hospital & Doctor', 'caretochina-medical'); ?></th>
                            <th style="width:130px;"><?php esc_html_e('Preferred Date', 'caretochina-medical'); ?></th>
                            <th style="width:120px;"><?php esc_html_e('Cost', 'caretochina-medical'); ?></th>
                            <th style="width:140px;"><?php esc_html_e('Status', 'caretochina-medical'); ?></th>
                            <th style="width:140px; text-align:center;"><?php esc_html_e('Actions', 'caretochina-medical'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="caretochina-admin-bookings-tbody">
                        <?php echo wp_kses_post($this->generate_admin_table_rows($bookings)); ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ADD NEW BOOKING ADMIN MODAL -->
        <div id="admin-add-booking-modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(15, 23, 42, 0.65); backdrop-filter:blur(6px); z-index:100000; align-items:center; justify-content:center; padding:20px; box-sizing:border-box;">
            <div style="background:#FFFFFF; border-radius:24px; width:650px; max-width:100%; padding:32px; box-shadow:0 20px 40px rgba(0,0,0,0.3); font-family:'Inter', sans-serif; max-height:90vh; overflow-y:auto; box-sizing:border-box;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #E2E8F0; padding-bottom:12px;">
                    <h2 style="margin:0; font-family:'Manrope'; color:#0F172A; font-size:20px; font-weight:800;"><i class="fa-solid fa-plus-circle" style="color:#0F766E;"></i> <?php esc_html_e('Add New Patient Booking', 'caretochina-medical'); ?></h2>
                    <button type="button" onclick="closeAdminAddBookingModal()" style="background:none; border:none; font-size:22px; cursor:pointer; color:#64748B;">&times;</button>
                </div>

                <form id="careyou-admin-add-form" onsubmit="submitAdminBooking(event)">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                        <div>
                            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:6px;"><?php esc_html_e('Patient Name *', 'caretochina-medical'); ?></label>
                            <input type="text" id="adm_name" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:6px;"><?php esc_html_e('Patient Email *', 'caretochina-medical'); ?></label>
                            <input type="email" id="adm_email" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1; box-sizing:border-box;">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                        <div>
                            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:6px;"><?php esc_html_e('Phone Number *', 'caretochina-medical'); ?></label>
                            <input type="tel" id="adm_phone" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:6px;"><?php esc_html_e('Specialty *', 'caretochina-medical'); ?></label>
                            <select id="adm_specialty" style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1; box-sizing:border-box;">
                                <option value="Cardiology"><?php esc_html_e('Cardiology & Heart Surgery', 'caretochina-medical'); ?></option>
                                <option value="Orthopedics"><?php esc_html_e('Orthopedics & Joint Care', 'caretochina-medical'); ?></option>
                                <option value="Oncology"><?php esc_html_e('Oncology & Cancer Care', 'caretochina-medical'); ?></option>
                                <option value="Neurosurgery"><?php esc_html_e('Neurosurgery & Spine Care', 'caretochina-medical'); ?></option>
                                <option value="Dental"><?php esc_html_e('Dental Implants', 'caretochina-medical'); ?></option>
                                <option value="Fertility"><?php esc_html_e('Fertility & IVF', 'caretochina-medical'); ?></option>
                            </select>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                        <div>
                            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:6px;"><?php esc_html_e('Hospital', 'caretochina-medical'); ?></label>
                            <input type="text" id="adm_hospital" value="Shenzhen International Medical Center" style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:6px;"><?php esc_html_e('Doctor', 'caretochina-medical'); ?></label>
                            <input type="text" id="adm_doctor" value="Dr. Zhang Wei" style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1; box-sizing:border-box;">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:20px;">
                        <div>
                            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:6px;"><?php esc_html_e('Preferred Date', 'caretochina-medical'); ?></label>
                            <input type="date" id="adm_date" value="<?php echo esc_attr(gmdate('Y-m-d', strtotime('+10 days'))); ?>" style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:6px;"><?php esc_html_e('Estimated Package Cost', 'caretochina-medical'); ?></label>
                            <input type="text" id="adm_cost" value="$14,500.00" style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1; box-sizing:border-box;">
                        </div>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:12px; border-top:1px solid #E2E8F0; padding-top:16px;">
                        <button type="button" onclick="closeAdminAddBookingModal()" class="button button-secondary"><?php esc_html_e('Cancel', 'caretochina-medical'); ?></button>
                        <button type="submit" id="adm_submit_btn" class="button button-primary" style="background:#0F766E; border-color:#0F766E; font-weight:700; padding:6px 20px;"><?php esc_html_e('Save Patient Booking', 'caretochina-medical'); ?></button>
                    </div>
                </form>
            </div>
        </div>

        <!-- VIEW BOOKING DETAILS ADMIN MODAL -->
        <div id="admin-view-booking-modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(15, 23, 42, 0.65); backdrop-filter:blur(6px); z-index:100000; align-items:center; justify-content:center; padding:20px; box-sizing:border-box;">
            <div style="background:#FFFFFF; border-radius:24px; width:620px; max-width:100%; padding:32px; box-shadow:0 20px 40px rgba(0,0,0,0.3); font-family:'Inter', sans-serif; max-height:90vh; overflow-y:auto; box-sizing:border-box;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #E2E8F0; padding-bottom:12px;">
                    <h2 style="margin:0; font-family:'Manrope'; color:#0F172A; font-size:20px; font-weight:800;"><i class="fa-solid fa-file-lines" style="color:#0F766E;"></i> <?php esc_html_e('Case Details', 'caretochina-medical'); ?> <span id="admin-view-code" style="color:#0F766E;"></span></h2>
                    <button type="button" onclick="closeAdminViewModal()" style="background:none; border:none; font-size:22px; cursor:pointer; color:#64748B;">&times;</button>
                </div>
                <div id="admin-view-content" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; font-size:13px; color:#334155; line-height:1.5;">
                    <!-- Populated dynamically -->
                </div>
                <div style="display:flex; justify-content:flex-end; margin-top:24px; border-top:1px solid #E2E8F0; padding-top:16px;">
                    <button type="button" class="button button-secondary" onclick="closeAdminViewModal()"><?php esc_html_e('Close', 'caretochina-medical'); ?></button>
                </div>
            </div>
        </div>

        <script>
        var adminAjaxUrl = '/wp-admin/admin-ajax.php';
        try {
            if (typeof ajaxurl !== 'undefined') {
                adminAjaxUrl = new URL(ajaxurl).pathname;
            }
        } catch(e) {}

        var adminNonce = '<?php echo esc_js(wp_create_nonce("caretochina_admin_nonce")); ?>';

        function openAdminAddBookingModal() {
            document.getElementById('admin-add-booking-modal').style.display = 'flex';
        }
        function closeAdminAddBookingModal() {
            document.getElementById('admin-add-booking-modal').style.display = 'none';
        }

        function closeAdminViewModal() {
            document.getElementById('admin-view-booking-modal').style.display = 'none';
        }

        function viewAdminBookingDetails(id) {
            jQuery.post(adminAjaxUrl, {
                action: 'caretochina_admin_get_booking_details',
                booking_id: id,
                _wpnonce: adminNonce
            }, function(res) {
                if (res.success && res.data) {
                    var d = res.data;
                    jQuery('#admin-view-code').text('#' + d.booking_code);
                    var html = `
                        <div><strong><?php echo esc_js(__('Patient Name:', 'caretochina-medical')); ?></strong><br>${d.full_name}</div>
                        <div><strong><?php echo esc_js(__('Email Address:', 'caretochina-medical')); ?></strong><br>${d.email}</div>
                        <div><strong><?php echo esc_js(__('Phone Number:', 'caretochina-medical')); ?></strong><br>${d.phone || '—'}</div>
                        <div><strong><?php echo esc_js(__('Specialty:', 'caretochina-medical')); ?></strong><br>${d.specialty || '—'}</div>
                        <div><strong><?php echo esc_js(__('Assigned Hospital:', 'caretochina-medical')); ?></strong><br>${d.hospital_name || '—'}</div>
                        <div><strong><?php echo esc_js(__('Preferred Date / Timing:', 'caretochina-medical')); ?></strong><br>${d.treatment_timing || '—'}</div>
                        <div><strong><?php echo esc_js(__('Booking Amount / Cost:', 'caretochina-medical')); ?></strong><br>$${parseFloat(d.amount || 0).toFixed(2)} ${d.currency || 'USD'}</div>
                        <div><strong><?php echo esc_js(__('Payment / Invoice Status:', 'caretochina-medical')); ?></strong><br>${d.invoice_status || '—'}</div>
                        <div style="grid-column: 1 / -1; margin-top:8px;"><strong><?php echo esc_js(__('Quote & Details:', 'caretochina-medical')); ?></strong><br><div style="background:#F8FAFC; border:1px solid #E2E8F0; padding:10px 14px; border-radius:8px; margin-top:4px;">${d.quote_details || '—'}</div></div>
                    `;
                    jQuery('#admin-view-content').html(html);
                    document.getElementById('admin-view-booking-modal').style.display = 'flex';
                } else {
                    alert('Could not load case details.');
                }
            });
        }

        function deleteAdminBooking(id) {
            if (!confirm('<?php echo esc_js(__('Are you sure you want to permanently delete this booking case?', 'caretochina-medical')); ?>')) {
                return;
            }
            jQuery.post(adminAjaxUrl, {
                action: 'caretochina_admin_delete_booking',
                booking_id: id,
                _wpnonce: adminNonce
            }, function(res) {
                if (res.success) {
                    refreshAdminBookingsTable();
                } else {
                    alert(res.data && res.data.message ? res.data.message : 'Error deleting booking.');
                }
            });
        }

        var searchTimeout = null;
        function handleAdminSearchKeyup(val) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                refreshAdminBookingsTable();
            }, 350);
        }

        function refreshAdminBookingsTable() {
            var searchVal = jQuery('#ctc-admin-search-input').val() || '';
            jQuery.post(adminAjaxUrl, {
                action: 'caretochina_admin_get_bookings',
                s: searchVal,
                _wpnonce: adminNonce
            }, function(res) {
                if (res.success && res.data && res.data.html) {
                    jQuery('#caretochina-admin-bookings-tbody').html(res.data.html);
                }
            });
        }

        function updateCareToChinaStatus(id, newStatus, selectEl) {
            var spinner = document.getElementById('status-spinner-' + id);
            if (spinner) {
                spinner.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
                spinner.style.display = 'inline';
            }

            if (selectEl) {
                jQuery(selectEl).removeClass('status-pending status-confirmed status-completed status-cancelled').addClass('status-' + newStatus);
            }

            jQuery.post(adminAjaxUrl, {
                action: 'caretochina_admin_update_status',
                booking_id: id,
                status: newStatus,
                _wpnonce: adminNonce
            }, function(res) {
                if (spinner) {
                    spinner.innerHTML = '<i class="fa-solid fa-check" style="color:#10B981;"></i>';
                    setTimeout(function() { spinner.style.display = 'none'; }, 1500);
                }
            }).fail(function() {
                if (spinner) {
                    spinner.innerHTML = '<i class="fa-solid fa-xmark" style="color:#EF4444;"></i>';
                }
            });
        }

        function submitAdminBooking(e) {
            e.preventDefault();
            var btn = jQuery('#adm_submit_btn');
            btn.prop('disabled', true).text('<?php echo esc_js(__('Saving...', 'caretochina-medical')); ?>');

            jQuery.post(adminAjaxUrl, {
                action: 'caretochina_admin_add_booking',
                name: jQuery('#adm_name').val(),
                email: jQuery('#adm_email').val(),
                phone: jQuery('#adm_phone').val(),
                specialty: jQuery('#adm_specialty').val(),
                hospital: jQuery('#adm_hospital').val(),
                doctor: jQuery('#adm_doctor').val(),
                date: jQuery('#adm_date').val(),
                cost: jQuery('#adm_cost').val(),
                _wpnonce: adminNonce
            }, function(res) {
                btn.prop('disabled', false).text('<?php echo esc_js(__('Save Patient Booking', 'caretochina-medical')); ?>');
                if (res.success) {
                    closeAdminAddBookingModal();
                    document.getElementById('careyou-admin-add-form').reset();
                    refreshAdminBookingsTable();
                } else {
                    alert(res.data && res.data.message ? res.data.message : 'Error adding booking');
                }
            }).fail(function() {
                btn.prop('disabled', false).text('<?php echo esc_js(__('Save Patient Booking', 'caretochina-medical')); ?>');
                alert('Server communication error.');
            });
        }

        // Live Auto-Refresh every 8 seconds (if user is not actively typing in search)
        setInterval(function() {
            if (!jQuery('#ctc-admin-search-input').is(':focus')) {
                refreshAdminBookingsTable();
            }
        }, 8000);
        </script>
        <?php
    }

    public function generate_admin_table_rows($bookings) {
        if (empty($bookings)) {
            return '<tr><td colspan="9" style="padding:32px 20px; text-align:center; color:#94A3B8; font-size:14px;"><i class="fa-solid fa-folder-open" style="font-size:28px; margin-bottom:8px; display:block; color:#CBD5E1;"></i>' . esc_html__('No medical bookings found in database.', 'caretochina-medical') . '</td></tr>';
        }

        $staff_desk_url = admin_url('admin.php?page=caretochina-staff-desk');

        $html = '';
        foreach ($bookings as $b) {
            $is_guest = (intval($b->patient_id ?? 0) === 0 || intval($b->is_guest ?? 0) === 1);
            $guest_badge = $is_guest ? '<span style="background:#FEF3C7; color:#92400E; font-size:10px; font-weight:700; padding:1px 6px; border-radius:4px; margin-left:4px;">' . esc_html__('Guest', 'caretochina-medical') . '</span>' : '';
            
            // Format cost
            $cost_display = '—';
            if (floatval($b->amount) > 0) {
                $b_curr = $b->currency ?: 'USD';
                $b_sym = class_exists('CareToChina_Packages') ? CareToChina_Packages::get_currency_symbol($b_curr) : '$';
                $cost_display = $b_sym . number_format((float)$b->amount, 2) . ' ' . esc_html($b_curr);
            } elseif (!empty($b->quote_details)) {
                // If quote details contains a cost snippet
                if (preg_match('/Cost:\s*([^|]+)/i', $b->quote_details, $matches)) {
                    $cost_display = esc_html(trim($matches[1]));
                } else {
                    $cost_display = esc_html($b->quote_details);
                }
            }

            $current_status = strtolower($b->status ?: 'pending');

            $html .= sprintf('
                <tr data-row-booking-id="%d">
                    <td>
                        <strong style="color:#0F766E; font-family:monospace; font-size:13px;">#%s</strong>%s
                    </td>
                    <td>
                        <strong style="color:#0F172A;">%s</strong>
                    </td>
                    <td>
                        <span style="color:#0F172A; font-size:13px;">%s</span><br>
                        <span style="color:#64748B; font-size:11.5px;"><i class="fa-solid fa-phone" style="font-size:10px;"></i> %s</span>
                    </td>
                    <td>
                        <span style="background:#CCFBF1; color:#0F766E; padding:4px 10px; border-radius:12px; font-weight:700; font-size:12px; display:inline-block;">%s</span>
                    </td>
                    <td>
                        <div style="font-weight:600; color:#0F172A;">%s</div>
                    </td>
                    <td>
                        <span style="color:#475569; font-size:12.5px;">%s</span>
                    </td>
                    <td>
                        <strong style="color:#0F766E; font-size:13px;">%s</strong>
                    </td>
                    <td>
                        <div style="position:relative; display:inline-block; vertical-align:middle;">
                            <select class="ctc-admin-status-select status-%s" onchange="updateCareToChinaStatus(%d, this.value, this)">
                                <option value="pending" %s>%s</option>
                                <option value="confirmed" %s>%s</option>
                                <option value="completed" %s>%s</option>
                                <option value="cancelled" %s>%s</option>
                            </select>
                        </div>
                    </td>
                    <td style="text-align:center;">
                        <div style="display:flex; align-items:center; justify-content:center; gap:6px;">
                            <button type="button" class="ctc-btn-action action-view" onclick="viewAdminBookingDetails(%d)" title="%s"><i class="fa-solid fa-eye"></i></button>
                            <a href="%s" class="ctc-btn-action action-desk" title="%s"><i class="fa-solid fa-headset"></i></a>
                            <button type="button" class="ctc-btn-action action-delete" onclick="deleteAdminBooking(%d)" title="%s"><i class="fa-solid fa-trash"></i></button>
                            <span id="status-spinner-%d" style="display:none; font-size:12px;"></span>
                        </div>
                    </td>
                </tr>',
                intval($b->id),
                esc_html($b->booking_code),
                $guest_badge,
                esc_html($b->full_name),
                esc_html($b->email),
                esc_html($b->phone ?: '—'),
                esc_html($b->specialty),
                esc_html($b->hospital_name ?: 'CareToChina Partner Clinic'),
                esc_html($b->treatment_timing ?: '—'),
                $cost_display,
                esc_attr($current_status),
                intval($b->id),
                selected($current_status, 'pending', false), esc_html__('Pending', 'caretochina-medical'),
                selected($current_status, 'confirmed', false), esc_html__('Confirmed', 'caretochina-medical'),
                selected($current_status, 'completed', false), esc_html__('Completed', 'caretochina-medical'),
                selected($current_status, 'cancelled', false), esc_html__('Cancelled', 'caretochina-medical'),
                intval($b->id),
                esc_attr__('View Case Details', 'caretochina-medical'),
                esc_url($staff_desk_url),
                esc_attr__('Open in Care Staff Desk', 'caretochina-medical'),
                intval($b->id),
                esc_attr__('Delete Booking', 'caretochina-medical'),
                intval($b->id)
            );
        }
        return $html;
    }

    public function handle_get_admin_bookings() {
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'caretochina_admin_nonce') && !wp_verify_nonce($nonce, 'careyou_admin_nonce')) {
            wp_send_json_error(['message' => __('Unauthorized security token.', 'caretochina-medical')]);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized user capability.', 'caretochina-medical')]);
        }

        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';

        $search = isset($_POST['s']) ? sanitize_text_field(wp_unslash($_POST['s'])) : '';
        if (!empty($search)) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $bookings = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}caretochina_bookings WHERE full_name LIKE %s OR email LIKE %s OR booking_code LIKE %s OR phone LIKE %s ORDER BY id DESC",
                $like, $like, $like, $like
            ));
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $bookings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}caretochina_bookings ORDER BY id DESC");
        }
        $html = $this->generate_admin_table_rows($bookings);

        wp_send_json_success(['html' => $html, 'count' => count($bookings)]);
    }

    public function handle_update_status() {
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'caretochina_admin_nonce') && !wp_verify_nonce($nonce, 'careyou_admin_nonce')) {
            wp_send_json_error(['message' => __('Unauthorized', 'caretochina-medical')]);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'caretochina-medical')]);
        }

        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';

        $id = isset($_POST['booking_id']) ? absint(wp_unslash($_POST['booking_id'])) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : 'pending';

        if ($id > 0) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update($table_bookings, ['status' => $status], ['id' => $id]);
            wp_send_json_success(['message' => __('Status updated successfully.', 'caretochina-medical')]);
        }
        wp_send_json_error(['message' => __('Invalid booking ID.', 'caretochina-medical')]);
    }

    public function handle_delete_booking() {
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'caretochina_admin_nonce') && !wp_verify_nonce($nonce, 'careyou_admin_nonce')) {
            wp_send_json_error(['message' => __('Unauthorized', 'caretochina-medical')]);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'caretochina-medical')]);
        }

        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';
        $id = isset($_POST['booking_id']) ? absint(wp_unslash($_POST['booking_id'])) : 0;

        if ($id > 0) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->delete($table_bookings, ['id' => $id]);
            wp_send_json_success(['message' => __('Booking deleted successfully.', 'caretochina-medical')]);
        }
        wp_send_json_error(['message' => __('Invalid booking ID.', 'caretochina-medical')]);
    }

    public function handle_get_booking_details() {
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'caretochina_admin_nonce') && !wp_verify_nonce($nonce, 'careyou_admin_nonce')) {
            wp_send_json_error(['message' => __('Unauthorized', 'caretochina-medical')]);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'caretochina-medical')]);
        }

        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';
        $id = isset($_POST['booking_id']) ? absint(wp_unslash($_POST['booking_id'])) : 0;

        if ($id > 0) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}caretochina_bookings WHERE id = %d", $id));
            if ($booking) {
                wp_send_json_success($booking);
            }
        }
        wp_send_json_error(['message' => __('Booking not found.', 'caretochina-medical')]);
    }

    public function handle_add_booking() {
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'caretochina_admin_nonce') && !wp_verify_nonce($nonce, 'careyou_admin_nonce')) {
            wp_send_json_error(['message' => __('Unauthorized user capability.', 'caretochina-medical')]);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized user capability.', 'caretochina-medical')]);
        }

        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';

        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        $specialty = isset($_POST['specialty']) ? sanitize_text_field(wp_unslash($_POST['specialty'])) : '';
        $hospital = isset($_POST['hospital']) ? sanitize_text_field(wp_unslash($_POST['hospital'])) : '';
        $doctor = isset($_POST['doctor']) ? sanitize_text_field(wp_unslash($_POST['doctor'])) : '';
        $date = isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : gmdate('Y-m-d');
        $cost = isset($_POST['cost']) ? sanitize_text_field(wp_unslash($_POST['cost'])) : '$14,500.00';

        if (empty($name) || empty($email) || empty($phone)) {
            wp_send_json_error(['message' => __('Please fill in Name, Email, and Phone fields.', 'caretochina-medical')]);
        }

        $booking_code = 'CTC-' . strtoupper(substr(md5(uniqid(wp_rand(), true)), 0, 6));
        $user = get_user_by('email', $email);
        $patient_id = $user ? $user->ID : 0;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $inserted = $wpdb->insert($table_bookings, [
            'booking_code'     => $booking_code,
            'patient_id'       => $patient_id,
            'full_name'        => $name,
            'email'            => $email,
            'phone'            => $phone,
            'specialty'        => $specialty,
            'hospital_name'    => $hospital,
            'treatment_timing' => $date,
            'quote_details'    => sprintf('Doctor: %s | Cost: %s | Notes: Created via WP Admin Booking Manager', $doctor, $cost),
            'status'           => 'confirmed',
            'timeline_stage'   => 1
        ]);

        if ($inserted) {
            wp_send_json_success(['booking_code' => $booking_code]);
        } else {
            wp_send_json_error(['message' => __('Database error. Could not add booking.', 'caretochina-medical')]);
        }
    }
}