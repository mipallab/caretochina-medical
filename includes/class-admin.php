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

        // Legacy aliases
        add_action('wp_ajax_careyou_admin_update_status', [$this, 'handle_update_status']);
        add_action('wp_ajax_careyou_admin_add_booking', [$this, 'handle_add_booking']);
    }

    public function register_admin_menu() {
        add_menu_page(
            __('Medical Bookings', 'caretochina-booking'),
            __('Bookings Manager', 'caretochina-booking'),
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
            wp_die(__('Unauthorized user access.', 'caretochina-booking'));
        }

        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';

        $search = sanitize_text_field($_GET['s'] ?? '');
        $where = '';
        if (!empty($search)) {
            $where = $wpdb->prepare(" WHERE full_name LIKE %s OR booking_code LIKE %s OR email LIKE %s ", "%$search%", "%$search%", "%$search%");
        }

        $bookings = $wpdb->get_results("SELECT * FROM $table_bookings $where ORDER BY id DESC");
        ?>
        <div class="wrap careyou-admin-dashboard caretochina-admin-dashboard" style="font-family:'Inter', sans-serif;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h1 class="wp-heading-inline"><i class="fa-solid fa-heart-pulse" style="color:#0F766E;"></i> <?php _e('CareToChina Medical Booking Manager', 'caretochina-booking'); ?></h1>
                <button type="button" class="button button-primary button-hero" onclick="openAdminAddBookingModal()" style="background:#0F766E; border-color:#0F766E; font-weight:700;">
                    <i class="fa-solid fa-plus-circle"></i> + <?php _e('Add New Booking', 'caretochina-booking'); ?>
                </button>
            </div>
            <hr class="wp-header-end">

            <!-- SEARCH BAR -->
            <form method="GET" style="margin-bottom:20px; display:flex; gap:10px;">
                <input type="hidden" name="page" value="caretochina-bookings">
                <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php _e('Search by patient name, email or code...', 'caretochina-booking'); ?>" style="width:320px; padding:8px 14px; border-radius:8px; border:1px solid #cbd5e1;">
                <button type="submit" class="button button-secondary"><?php _e('Search Bookings', 'caretochina-booking'); ?></button>
            </form>

            <!-- BOOKINGS LIST TABLE -->
            <table class="wp-list-table widefat fixed striped table-view-list" style="border-radius:12px; overflow:hidden; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);">
                <thead>
                    <tr style="background:#0F172A; color:#FFFFFF;">
                        <th style="width:110px; color:#FFF; font-weight:700;"><?php _e('Code', 'caretochina-booking'); ?></th>
                        <th style="color:#FFF; font-weight:700;"><?php _e('Patient Name', 'caretochina-booking'); ?></th>
                        <th style="color:#FFF; font-weight:700;"><?php _e('Email & Phone', 'caretochina-booking'); ?></th>
                        <th style="color:#FFF; font-weight:700;"><?php _e('Specialty', 'caretochina-booking'); ?></th>
                        <th style="color:#FFF; font-weight:700;"><?php _e('Hospital & Doctor', 'caretochina-booking'); ?></th>
                        <th style="color:#FFF; font-weight:700;"><?php _e('Preferred Date', 'caretochina-booking'); ?></th>
                        <th style="color:#FFF; font-weight:700;"><?php _e('Cost', 'caretochina-booking'); ?></th>
                        <th style="color:#FFF; font-weight:700;"><?php _e('Status', 'caretochina-booking'); ?></th>
                        <th style="width:120px; color:#FFF; font-weight:700;"><?php _e('Actions', 'caretochina-booking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                        <tr><td colspan="9" style="padding:20px; text-align:center; color:#64748b;"><?php _e('No medical bookings found in database.', 'caretochina-booking'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($bookings as $b): ?>
                            <tr>
                                <td><strong style="color:#0F766E;">#<?php echo esc_html($b->booking_code); ?></strong></td>
                                <td><strong><?php echo esc_html($b->full_name); ?></strong></td>
                                <td><?php echo esc_html($b->email); ?><br><span style="color:#64748b; font-size:12px;"><?php echo esc_html($b->phone); ?></span></td>
                                <td><span style="background:#CCFBF1; color:#0F766E; padding:4px 10px; border-radius:12px; font-weight:700; font-size:12px;"><?php echo esc_html($b->specialty); ?></span></td>
                                <td><?php echo esc_html($b->hospital_name); ?></td>
                                <td><?php echo esc_html($b->treatment_timing); ?></td>
                                <td><div style="font-size:11px; color:#64748b; max-width:200px; white-space:normal; line-height:1.3;"><?php echo esc_html($b->quote_details); ?></div></td>
                                <td>
                                    <select onchange="updateCareToChinaStatus(<?php echo $b->id; ?>, this.value)" style="border-radius:6px; padding:4px 8px; font-weight:600;">
                                        <option value="pending" <?php selected($b->status, 'pending'); ?>><?php _e('Pending', 'caretochina-booking'); ?></option>
                                        <option value="confirmed" <?php selected($b->status, 'confirmed'); ?>><?php _e('Confirmed', 'caretochina-booking'); ?></option>
                                        <option value="completed" <?php selected($b->status, 'completed'); ?>><?php _e('Completed', 'caretochina-booking'); ?></option>
                                        <option value="cancelled" <?php selected($b->status, 'cancelled'); ?>><?php _e('Cancelled', 'caretochina-booking'); ?></option>
                                    </select>
                                </td>
                                <td>
                                    <span id="status-spinner-<?php echo $b->id; ?>" style="color:#0F766E; font-weight:600; display:none;"><?php _e('Updating...', 'caretochina-booking'); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ADD NEW BOOKING ADMIN MODAL -->
        <div id="admin-add-booking-modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(15, 23, 42, 0.65); backdrop-filter:blur(6px); z-index:100000; align-items:center; justify-content:center;">
            <div style="background:#FFFFFF; border-radius:24px; width:650px; max-width:90%; padding:32px; box-shadow:0 20px 40px rgba(0,0,0,0.3); font-family:'Inter', sans-serif; max-height:90vh; overflow-y:auto;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #E2E8F0; padding-bottom:12px;">
                    <h2 style="margin:0; font-family:'Manrope'; color:#0F172A; font-size:22px;"><i class="fa-solid fa-plus-circle" style="color:#0F766E;"></i> <?php _e('Add New Patient Booking', 'caretochina-booking'); ?></h2>
                    <button type="button" onclick="closeAdminAddBookingModal()" style="background:none; border:none; font-size:20px; cursor:pointer; color:#64748B;">&times;</button>
                </div>

                <form id="careyou-admin-add-form" onsubmit="submitAdminBooking(event)">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                        <div>
                            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:6px;"><?php _e('Patient Name *', 'caretochina-booking'); ?></label>
                            <input type="text" id="adm_name" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1;">
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:6px;"><?php _e('Patient Email *', 'caretochina-booking'); ?></label>
                            <input type="email" id="adm_email" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1;">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                        <div>
                            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:6px;"><?php _e('Phone Number *', 'caretochina-booking'); ?></label>
                            <input type="tel" id="adm_phone" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1;">
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:6px;"><?php _e('Specialty *', 'caretochina-booking'); ?></label>
                            <select id="adm_specialty" style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1;">
                                <option value="Cardiology"><?php _e('Cardiology & Heart Surgery', 'caretochina-booking'); ?></option>
                                <option value="Orthopedics"><?php _e('Orthopedics & Joint Care', 'caretochina-booking'); ?></option>
                                <option value="Oncology"><?php _e('Oncology & Cancer Care', 'caretochina-booking'); ?></option>
                                <option value="Neurosurgery"><?php _e('Neurosurgery & Spine Care', 'caretochina-booking'); ?></option>
                                <option value="Dental"><?php _e('Dental Implants', 'caretochina-booking'); ?></option>
                                <option value="Fertility"><?php _e('Fertility & IVF', 'caretochina-booking'); ?></option>
                            </select>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                        <div>
                            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:6px;"><?php _e('Hospital', 'caretochina-booking'); ?></label>
                            <input type="text" id="adm_hospital" value="Charité Universitätsmedizin (Germany)" style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1;">
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:6px;"><?php _e('Doctor', 'caretochina-booking'); ?></label>
                            <input type="text" id="adm_doctor" value="Dr. Klaus Mueller" style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1;">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:20px;">
                        <div>
                            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:6px;"><?php _e('Preferred Date', 'caretochina-booking'); ?></label>
                            <input type="date" id="adm_date" value="<?php echo date('Y-m-d', strtotime('+10 days')); ?>" style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1;">
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:6px;"><?php _e('Estimated Package Cost', 'caretochina-booking'); ?></label>
                            <input type="text" id="adm_cost" value="$14,500.00" style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1;">
                        </div>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:12px; border-top:1px solid #E2E8F0; padding-top:16px;">
                        <button type="button" onclick="closeAdminAddBookingModal()" class="button button-secondary"><?php _e('Cancel', 'caretochina-booking'); ?></button>
                        <button type="submit" id="adm_submit_btn" class="button button-primary" style="background:#0F766E; border-color:#0F766E; font-weight:700; padding:6px 20px;"><?php _e('Save Patient Booking', 'caretochina-booking'); ?></button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        function openAdminAddBookingModal() {
            document.getElementById('admin-add-booking-modal').style.display = 'flex';
        }
        function closeAdminAddBookingModal() {
            document.getElementById('admin-add-booking-modal').style.display = 'none';
        }

        function updateCareToChinaStatus(id, newStatus) {
            var spinner = document.getElementById('status-spinner-' + id);
            spinner.style.display = 'inline';
            
            var nonce = '<?php echo wp_create_nonce("caretochina_admin_nonce"); ?>';
            var ajax_url = '/wp-admin/admin-ajax.php';
            try {
                if (typeof ajaxurl !== 'undefined') {
                    ajax_url = new URL(ajaxurl).pathname;
                }
            } catch(e) {}

            jQuery.post(ajax_url, {
                action: 'caretochina_admin_update_status',
                booking_id: id,
                status: newStatus,
                _wpnonce: nonce
            }, function(res) {
                spinner.innerHTML = '<i class="fas fa-check"></i> <?php echo esc_js(__('Updated', 'caretochina-booking')); ?>';
                setTimeout(function() { spinner.style.display = 'none'; }, 1500);
            });
        }

        function submitAdminBooking(e) {
            e.preventDefault();
            var btn = jQuery('#adm_submit_btn');
            btn.prop('disabled', true).text('<?php echo esc_js(__('Saving...', 'caretochina-booking')); ?>');
            var nonce = '<?php echo wp_create_nonce("caretochina_admin_nonce"); ?>';
            var ajax_url = '/wp-admin/admin-ajax.php';
            try {
                if (typeof ajaxurl !== 'undefined') {
                    ajax_url = new URL(ajaxurl).pathname;
                }
            } catch(e) {}

            jQuery.post(ajax_url, {
                action: 'caretochina_admin_add_booking',
                name: jQuery('#adm_name').val(),
                email: jQuery('#adm_email').val(),
                phone: jQuery('#adm_phone').val(),
                specialty: jQuery('#adm_specialty').val(),
                hospital: jQuery('#adm_hospital').val(),
                doctor: jQuery('#adm_doctor').val(),
                date: jQuery('#adm_date').val(),
                cost: jQuery('#adm_cost').val(),
                _wpnonce: nonce
            }, function(res) {
                if (res.success) {
                    alert('<?php echo esc_js(__('Booking added successfully!', 'caretochina-booking')); ?> Code: ' + res.data.booking_code);
                    location.reload();
                } else {
                    alert(res.data.message);
                    btn.prop('disabled', false).text('<?php echo esc_js(__('Save Patient Booking', 'caretochina-booking')); ?>');
                }
            });
        }
        </script>
        <?php
    }

    public function handle_update_status() {
        $nonce = $_POST['_wpnonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_admin_nonce') && !wp_verify_nonce($nonce, 'careyou_admin_nonce')) {
            wp_send_json_error(['message' => __('Unauthorized', 'caretochina-booking')]);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'caretochina-booking')]);
        }

        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';

        $id = intval($_POST['booking_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? 'pending');

        if ($id > 0) {
            $wpdb->update($table_bookings, ['status' => $status], ['id' => $id]);
            wp_send_json_success(['message' => __('Status updated successfully.', 'caretochina-booking')]);
        }
        wp_send_json_error(['message' => __('Invalid booking ID.', 'caretochina-booking')]);
    }

    public function handle_add_booking() {
        $nonce = $_POST['_wpnonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'caretochina_admin_nonce') && !wp_verify_nonce($nonce, 'careyou_admin_nonce')) {
            wp_send_json_error(['message' => __('Unauthorized user capability.', 'caretochina-booking')]);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized user capability.', 'caretochina-booking')]);
        }

        global $wpdb;
        $table_bookings = $wpdb->prefix . 'caretochina_bookings';

        $name = sanitize_text_field($_POST['name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $specialty = sanitize_text_field($_POST['specialty'] ?? '');
        $hospital = sanitize_text_field($_POST['hospital'] ?? '');
        $doctor = sanitize_text_field($_POST['doctor'] ?? '');
        $date = sanitize_text_field($_POST['date'] ?? date('Y-m-d'));
        $cost = sanitize_text_field($_POST['cost'] ?? '$14,500.00');

        if (empty($name) || empty($email) || empty($phone)) {
            wp_send_json_error(['message' => __('Please fill in Name, Email, and Phone fields.', 'caretochina-booking')]);
        }

        $booking_code = 'CTC-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
        $user = get_user_by('email', $email);
        $patient_id = $user ? $user->ID : 0;

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
            wp_send_json_error(['message' => __('Database error. Could not add booking.', 'caretochina-booking')]);
        }
    }
}