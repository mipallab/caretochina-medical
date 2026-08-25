<?php
if (!defined('ABSPATH')) {
    exit;
}

class CareToChina_Setup_Wizard {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this, 'handle_form_submissions']);

        // AJAX handlers for WooCommerce Auto-Install & Data Export
        add_action('wp_ajax_ctc_wizard_install_woocommerce', [$this, 'handle_ajax_install_woocommerce']);
        add_action('wp_ajax_ctc_export_plugin_data', [$this, 'handle_ajax_export_data']);
        add_action('admin_post_ctc_export_plugin_data', [$this, 'handle_ajax_export_data']);
    }

    public function register_admin_menu() {
        add_submenu_page(
            'caretochina-staff-desk',
            __('CareToChina Setup Wizard', 'caretochina-medical'),
            __('Setup Wizard', 'caretochina-medical'),
            'manage_options',
            'caretochina-setup-wizard',
            [$this, 'render_wizard_page']
        );
    }

    public function get_current_step() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $step = isset($_GET['step']) ? absint(wp_unslash($_GET['step'])) : 1;
        if ($step < 1 || $step > 6) {
            $step = 1;
        }
        return $step;
    }

    public function get_step_url($step) {
        return admin_url('admin.php?page=caretochina-setup-wizard&step=' . intval($step));
    }

    public function handle_form_submissions() {
        if (!isset($_POST['ctc_wizard_nonce']) || !isset($_POST['ctc_wizard_step'])) {
            return;
        }

        $nonce = isset($_POST['ctc_wizard_nonce']) ? sanitize_text_field(wp_unslash($_POST['ctc_wizard_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'ctc_setup_wizard_action')) {
            wp_die(esc_html__('Security verification failed.', 'caretochina-medical'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'caretochina-medical'));
        }

        $step = isset($_POST['ctc_wizard_step']) ? absint(wp_unslash($_POST['ctc_wizard_step'])) : 1;

        // STEP 3: DEDICATED PAGES
        if ($step === 3) {
            $types = ['patient_dashboard', 'staff_portal', 'privacy_policy', 'terms'];
            foreach ($types as $type) {
                $action_val = isset($_POST['page_action_' . $type]) ? sanitize_text_field(wp_unslash($_POST['page_action_' . $type])) : 'create';
                $existing_id = isset($_POST['page_select_' . $type]) ? absint(wp_unslash($_POST['page_select_' . $type])) : 0;

                if ($action_val === 'assign' && $existing_id > 0) {
                    CareToChina_Page_Manager::create_or_assign_page($type, $existing_id);
                } elseif ($action_val === 'create') {
                    CareToChina_Page_Manager::create_or_assign_page($type, 0);
                }
            }
            wp_safe_redirect($this->get_step_url(4));
            exit;
        }

        // STEP 4: GOOGLE RECAPTCHA
        if ($step === 4) {
            $version = isset($_POST['ctc_recaptcha_version']) ? sanitize_text_field(wp_unslash($_POST['ctc_recaptcha_version'])) : 'v2';
            update_option('ctc_recaptcha_version', $version);

            if (isset($_POST['ctc_recaptcha_v2_site_key'])) {
                update_option('ctc_recaptcha_v2_site_key', sanitize_text_field(wp_unslash($_POST['ctc_recaptcha_v2_site_key'])));
            }
            if (!empty(sanitize_text_field(wp_unslash($_POST['ctc_recaptcha_v2_secret_key']))) && strpos(sanitize_text_field(wp_unslash($_POST['ctc_recaptcha_v2_secret_key'])), '••••') === false) {
                $enc = CareToChina_Payment_Security::encrypt_secret(sanitize_text_field(wp_unslash(sanitize_text_field(wp_unslash($_POST['ctc_recaptcha_v2_secret_key'])))));
                update_option('ctc_recaptcha_v2_secret_key', $enc);
            }

            if (isset($_POST['ctc_recaptcha_v3_site_key'])) {
                update_option('ctc_recaptcha_v3_site_key', sanitize_text_field(wp_unslash($_POST['ctc_recaptcha_v3_site_key'])));
            }
            if (!empty(sanitize_text_field(wp_unslash($_POST['ctc_recaptcha_v3_secret_key']))) && strpos(sanitize_text_field(wp_unslash($_POST['ctc_recaptcha_v3_secret_key'])), '••••') === false) {
                $enc = CareToChina_Payment_Security::encrypt_secret(sanitize_text_field(wp_unslash(sanitize_text_field(wp_unslash($_POST['ctc_recaptcha_v3_secret_key'])))));
                update_option('ctc_recaptcha_v3_secret_key', $enc);
            }

            $threshold = isset($_POST['ctc_recaptcha_v3_threshold']) ? floatval(wp_unslash($_POST['ctc_recaptcha_v3_threshold'])) : 0.5;
            update_option('ctc_recaptcha_v3_threshold', $threshold);

            update_option('ctc_recaptcha_enable_login', isset($_POST['ctc_recaptcha_enable_login']) ? 1 : 0);
            update_option('ctc_recaptcha_enable_register', isset($_POST['ctc_recaptcha_enable_register']) ? 1 : 0);
            update_option('ctc_recaptcha_enable_booking', isset($_POST['ctc_recaptcha_enable_booking']) ? 1 : 0);
            update_option('ctc_recaptcha_hide_badge', isset($_POST['ctc_recaptcha_hide_badge']) ? 1 : 0);

            wp_safe_redirect($this->get_step_url(5));
            exit;
        }

        // STEP 5: GOOGLE LOGIN
        if ($step === 5) {
            if (isset($_POST['ctc_google_client_id'])) {
                update_option('ctc_google_client_id', sanitize_text_field(wp_unslash($_POST['ctc_google_client_id'])));
            }
            if (!empty(sanitize_text_field(wp_unslash($_POST['ctc_google_client_secret']))) && strpos(sanitize_text_field(wp_unslash($_POST['ctc_google_client_secret'])), '••••') === false) {
                $enc = CareToChina_Payment_Security::encrypt_secret(sanitize_text_field(wp_unslash(sanitize_text_field(wp_unslash($_POST['ctc_google_client_secret'])))));
                update_option('ctc_google_client_secret', $enc);
            }

            wp_safe_redirect($this->get_step_url(6));
            exit;
        }

        // STEP 6: FINISH
        if ($step === 6) {
            $delete_on_uninstall = isset($_POST['ctc_delete_data_on_uninstall']) ? 1 : 0;
            update_option('ctc_delete_data_on_uninstall', $delete_on_uninstall);

            wp_safe_redirect(admin_url('admin.php?page=caretochina-staff-desk&setup_completed=1'));
            exit;
        }
    }

    /**
     * AJAX WooCommerce Auto-Install / Activation Handler
     */
    public function handle_ajax_install_woocommerce() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'ctc_setup_wizard_action')) {
            wp_send_json_error(['message' => __('Security verification failed.', 'caretochina-medical')]);
        }

        if (!current_user_can('install_plugins') || !current_user_can('activate_plugins')) {
            wp_send_json_error(['message' => __('You do not have permission to install or activate plugins.', 'caretochina-medical')]);
        }

        include_once ABSPATH . 'wp-admin/includes/plugin.php';
        include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        include_once ABSPATH . 'wp-admin/includes/file.php';
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        $wc_plugin_file = 'woocommerce/woocommerce.php';

        // 1. Check if already active
        if (is_plugin_active($wc_plugin_file)) {
            wp_send_json_success(['status' => 'active', 'message' => __('WooCommerce is already installed and active.', 'caretochina-medical')]);
        }

        // 2. Check if installed but inactive
        if (file_exists(WP_PLUGIN_DIR . '/' . $wc_plugin_file)) {
            $result = activate_plugin($wc_plugin_file);
            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
            }
            wp_send_json_success(['status' => 'activated', 'message' => __('WooCommerce has been activated successfully!', 'caretochina-medical')]);
        }

        // 3. Check Filesystem credentials for host restrictions
        $url = wp_nonce_url(admin_url('admin-ajax.php?action=ctc_wizard_install_woocommerce'), 'ctc_setup_wizard_action');
        if (false === ($creds = request_filesystem_credentials($url, '', false, false, null))) {
            wp_send_json_error([
                'fallback' => true,
                'message'  => __('Automatic filesystem access is restricted on this server. Please use the manual install link below.', 'caretochina-medical')
            ]);
        }

        if (!WP_Filesystem($creds)) {
            wp_send_json_error([
                'fallback' => true,
                'message'  => __('Unable to access server filesystem. Please install WooCommerce manually.', 'caretochina-medical')
            ]);
        }

        // 4. Fetch Plugin Information from WordPress.org API
        $api = plugins_api('plugin_information', ['slug' => 'woocommerce', 'fields' => ['sections' => false]]);
        if (is_wp_error($api)) {
            wp_send_json_error(['message' => __('Failed to fetch WooCommerce package from WordPress.org API: ', 'caretochina-medical') . $api->get_error_message()]);
        }

        // 5. Silent Skin Upgrader
        $skin     = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader($skin);
        $install  = $upgrader->install($api->download_link);

        if (is_wp_error($install)) {
            wp_send_json_error(['message' => $install->get_error_message()]);
        }

        if (!$install) {
            wp_send_json_error(['message' => __('Failed to install WooCommerce. Please install manually.', 'caretochina-medical')]);
        }

        // 6. Activate newly installed plugin
        $activate = activate_plugin($wc_plugin_file);
        if (is_wp_error($activate)) {
            wp_send_json_error(['message' => __('Installed, but activation failed: ', 'caretochina-medical') . $activate->get_error_message()]);
        }

        wp_send_json_success([
            'status'  => 'installed_activated',
            'message' => __('WooCommerce was installed and activated successfully!', 'caretochina-medical')
        ]);
    }

    /**
     * AJAX / Admin-Post Export Data Handler
     */
    public function handle_ajax_export_data() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'caretochina-medical'));
        }

        $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])) : (isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '');
        if (!wp_verify_nonce($nonce, 'ctc_export_data_nonce') && !wp_verify_nonce($nonce, 'ctc_setup_wizard_action')) {
            wp_die(esc_html__('Security verification failed.', 'caretochina-medical'));
        }

        require_once dirname(__FILE__) . '/class-data-exporter.php';
        CareToChina_Data_Exporter::stream_download();
    }

    /**
     * Render the multi-step setup wizard UI
     */
    public function render_wizard_page() {
        $step = $this->get_current_step();
        $steps_labels = [
            1 => __('1. Welcome', 'caretochina-medical'),
            2 => __('2. WooCommerce', 'caretochina-medical'),
            3 => __('3. Pages', 'caretochina-medical'),
            4 => __('4. reCAPTCHA', 'caretochina-medical'),
            5 => __('5. Google Login', 'caretochina-medical'),
            6 => __('6. Finish', 'caretochina-medical'),
        ];
        ?>
        <div class="wrap" style="max-width:850px; margin:30px auto; font-family:'Manrope', sans-serif;">
            <!-- WIZARD HEADER -->
            <div style="text-align:center; margin-bottom:28px;">
                <div style="display:inline-flex; align-items:center; gap:10px; background:#F0FDFA; border:1px solid #CCFBF1; padding:8px 18px; border-radius:999px; color:#0F766E; font-weight:700; font-size:13px; margin-bottom:12px;">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> <?php esc_html_e('CareToChina Medical Suite Setup', 'caretochina-medical'); ?>
                </div>
                <h1 style="margin:0 0 8px 0; font-size:26px; font-weight:800; color:#0F172A;"><?php esc_html_e('Platform Onboarding Wizard', 'caretochina-medical'); ?></h1>
                <p style="margin:0; color:#64748B; font-size:14px;"><?php esc_html_e('Complete these quick configuration steps to get your medical booking platform ready.', 'caretochina-medical'); ?></p>
            </div>

            <!-- STEP INDICATOR PILLS -->
            <div style="display:flex; justify-content:space-between; background:#FFF; border:1px solid #E2E8F0; border-radius:14px; padding:12px 18px; margin-bottom:24px; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
                <?php foreach ($steps_labels as $num => $label) : 
                    $is_active = ($num === $step);
                    $is_done = ($num < $step);
                    $color = $is_active ? '#0F766E' : ($is_done ? '#10B981' : '#94A3B8');
                    $bg = $is_active ? '#CCFBF1' : ($is_done ? '#D1FAE5' : 'transparent');
                ?>
                    <div style="display:flex; align-items:center; gap:6px; font-size:12px; font-weight:700; color:<?php echo esc_attr($color); ?>; background:<?php echo esc_attr($bg); ?>; padding:6px 10px; border-radius:8px;">
                        <?php if ($is_done) : ?><i class="fa-solid fa-circle-check"></i><?php endif; ?>
                        <?php echo esc_html($label); ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- WIZARD STEP CARD -->
            <div style="background:#FFF; border:1px solid #E2E8F0; border-radius:20px; padding:32px; box-shadow:0 10px 25px -5px rgba(0,0,0,0.05); margin-bottom:20px;">
                <?php
                switch ($step) {
                    case 1:
                        $this->render_step_1_welcome();
                        break;
                    case 2:
                        $this->render_step_2_woocommerce();
                        break;
                    case 3:
                        $this->render_step_3_pages();
                        break;
                    case 4:
                        $this->render_step_4_recaptcha();
                        break;
                    case 5:
                        $this->render_step_5_google();
                        break;
                    case 6:
                        $this->render_step_6_finish();
                        break;
                }
                ?>
            </div>

            <!-- GLOBAL FOOTER NAVIGATION -->
            <div style="display:flex; justify-content:space-between; align-items:center; font-size:13px; color:#64748B;">
                <?php if ($step > 1 && $step < 6) : ?>
                    <a href="<?php echo esc_url($this->get_step_url($step - 1)); ?>" style="color:#64748B; text-decoration:none; font-weight:600;">
                        &larr; <?php esc_html_e('Previous Step', 'caretochina-medical'); ?>
                    </a>
                <?php else : ?>
                    <div></div>
                <?php endif; ?>

                <?php if ($step < 6) : ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=caretochina-staff-desk')); ?>" style="color:#94A3B8; text-decoration:underline;">
                        <?php esc_html_e('Skip setup entirely & go to Staff Desk &rarr;', 'caretochina-medical'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * STEP 1: WELCOME
     */
    private function render_step_1_welcome() {
        ?>
        <div style="text-align:center; padding:20px 10px;">
            <div style="width:72px; height:72px; background:#CCFBF1; color:#0F766E; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:32px; margin-bottom:20px;">
                <i class="fa-solid fa-hospital-user"></i>
            </div>
            <h2 style="font-size:22px; font-weight:800; color:#0F172A; margin:0 0 12px 0;"><?php esc_html_e('Welcome to CareToChina Medical Suite', 'caretochina-medical'); ?></h2>
            <p style="color:#475569; font-size:15px; line-height:1.6; max-width:600px; margin:0 auto 28px auto;">
                <?php esc_html_e('Thank you for choosing CareToChina. This onboarding wizard will configure your WooCommerce payment engine, create your patient and staff portals, protect forms with Google reCAPTCHA, and set up Google Sign-In in just a few minutes.', 'caretochina-medical'); ?>
            </p>

            <div style="display:flex; justify-content:center; gap:16px;">
                <a href="<?php echo esc_url($this->get_step_url(2)); ?>" class="button button-primary button-hero" style="background:#0F766E; border-color:#0F766E; font-weight:700; border-radius:10px; padding:8px 28px;">
                    <?php esc_html_e('Get Started &rarr;', 'caretochina-medical'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=caretochina-staff-desk')); ?>" class="button button-hero" style="border-radius:10px; font-weight:600; padding:8px 20px;">
                    <?php esc_html_e('Skip for Now', 'caretochina-medical'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * STEP 2: WOOCOMMERCE CHECK & INSTALL
     */
    private function render_step_2_woocommerce() {
        $wc_file = 'woocommerce/woocommerce.php';
        $is_active = is_plugin_active($wc_file);
        $is_installed = file_exists(WP_PLUGIN_DIR . '/' . $wc_file);
        $nonce = wp_create_nonce('ctc_setup_wizard_action');
        ?>
        <div>
            <h2 style="font-size:20px; font-weight:800; color:#0F172A; margin:0 0 8px 0; display:flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-cart-shopping" style="color:#0F766E;"></i> <?php esc_html_e('WooCommerce Payment Engine Check', 'caretochina-medical'); ?>
            </h2>
            <p style="color:#64748B; font-size:14px; margin-bottom:24px;">
                <?php esc_html_e('CareToChina utilizes a headless WooCommerce payment engine for order creation, price snapshotting, and transaction reporting.', 'caretochina-medical'); ?>
            </p>

            <div id="wc-status-box" style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:14px; padding:20px; margin-bottom:24px;">
                <?php if ($is_active) : ?>
                    <div style="display:flex; align-items:center; gap:12px; color:#065F46;">
                        <i class="fa-solid fa-circle-check" style="font-size:28px; color:#10B981;"></i>
                        <div>
                            <strong style="font-size:15px; display:block;"><?php esc_html_e('WooCommerce is Installed & Active', 'caretochina-medical'); ?></strong>
                            <span style="font-size:13px; color:#047857;"><?php esc_html_e('Your payment engine backend is fully operational.', 'caretochina-medical'); ?></span>
                        </div>
                    </div>
                <?php elseif ($is_installed) : ?>
                    <div style="display:flex; align-items:center; justify-content:space-between;">
                        <div style="display:flex; align-items:center; gap:12px; color:#B45309;">
                            <i class="fa-solid fa-triangle-exclamation" style="font-size:28px; color:#F59E0B;"></i>
                            <div>
                                <strong style="font-size:15px; display:block;"><?php esc_html_e('WooCommerce is Installed but Inactive', 'caretochina-medical'); ?></strong>
                                <span style="font-size:13px; color:#78350F;"><?php esc_html_e('Activate WooCommerce to enable payment processing.', 'caretochina-medical'); ?></span>
                            </div>
                        </div>
                        <button type="button" onclick="installOrActivateWC()" id="btn-wc-action" class="button button-primary" style="background:#0F766E; border-color:#0F766E; font-weight:700; border-radius:8px;">
                            <i class="fa-solid fa-bolt"></i> <?php esc_html_e('Activate WooCommerce', 'caretochina-medical'); ?>
                        </button>
                    </div>
                <?php else : ?>
                    <div style="display:flex; align-items:center; justify-content:space-between;">
                        <div style="display:flex; align-items:center; gap:12px; color:#475569;">
                            <i class="fa-solid fa-download" style="font-size:28px; color:#0F766E;"></i>
                            <div>
                                <strong style="font-size:15px; display:block;"><?php esc_html_e('WooCommerce Not Found', 'caretochina-medical'); ?></strong>
                                <span style="font-size:13px; color:#64748B;"><?php esc_html_e('Click below for automatic 1-click installation from WordPress.org.', 'caretochina-medical'); ?></span>
                            </div>
                        </div>
                        <button type="button" onclick="installOrActivateWC()" id="btn-wc-action" class="button button-primary" style="background:#0F766E; border-color:#0F766E; font-weight:700; border-radius:8px;">
                            <i class="fa-solid fa-cloud-arrow-down"></i> <?php esc_html_e('Install & Activate WooCommerce', 'caretochina-medical'); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <div id="wc-ajax-message" style="display:none; padding:14px; border-radius:10px; margin-bottom:20px; font-size:13px;"></div>

            <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #E2E8F0; padding-top:20px;">
                <a href="<?php echo esc_url($this->get_step_url(3)); ?>" style="color:#64748B; font-weight:600; text-decoration:none;">
                    <?php esc_html_e('Skip this step &rarr;', 'caretochina-medical'); ?>
                </a>
                <a href="<?php echo esc_url($this->get_step_url(3)); ?>" class="button button-primary" style="background:#0F766E; border-color:#0F766E; font-weight:700; border-radius:8px; padding:6px 20px;">
                    <?php esc_html_e('Continue to Pages Setup &rarr;', 'caretochina-medical'); ?>
                </a>
            </div>
        </div>

        <script>
        function installOrActivateWC() {
            var $btn = jQuery('#btn-wc-action');
            var $msg = jQuery('#wc-ajax-message');
            $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> <?php esc_html_e("Processing...", "caretochina-medical"); ?>');
            $msg.hide().empty();

            jQuery.post(ajaxurl, {
                action: 'ctc_wizard_install_woocommerce',
                nonce: '<?php echo esc_js($nonce); ?>'
            }, function(res) {
                if (res.success) {
                    $msg.css({ background: '#D1FAE5', color: '#065F46', border: '1px solid #A7F3D0' }).html('<i class="fa-solid fa-circle-check"></i> ' + res.data.message).show();
                    setTimeout(function() { window.location.reload(); }, 1200);
                } else {
                    $btn.prop('disabled', false).html('<i class="fa-solid fa-rotate-right"></i> <?php esc_html_e("Retry", "caretochina-medical"); ?>');
                    var errMsg = (res.data && res.data.message) || '<?php esc_html_e("Installation failed.", "caretochina-medical"); ?>';
                    if (res.data && res.data.fallback) {
                        errMsg += '<br><a href="<?php echo esc_url(admin_url("plugin-install.php?s=woocommerce&tab=search&type=term")); ?>" target="_blank" class="button button-small" style="margin-top:8px;">Open Plugins Search &rarr;</a>';
                    }
                    $msg.css({ background: '#FEE2E2', color: '#991B1B', border: '1px solid #FECACA' }).html('<i class="fa-solid fa-triangle-exclamation"></i> ' + errMsg).show();
                }
            }).fail(function() {
                $btn.prop('disabled', false).html('<i class="fa-solid fa-rotate-right"></i> <?php esc_html_e("Retry", "caretochina-medical"); ?>');
                $msg.css({ background: '#FEE2E2', color: '#991B1B', border: '1px solid #FECACA' }).html('Server error communicating with WordPress.org.').show();
            });
        }
        </script>
        <?php
    }

    /**
     * STEP 3: DEDICATED PAGES SETUP
     */
    private function render_step_3_pages() {
        $pages_status = CareToChina_Page_Manager::get_all_pages_status();
        $all_pages = get_pages(['post_status' => 'publish']);
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('ctc_setup_wizard_action', 'ctc_wizard_nonce'); ?>
            <input type="hidden" name="ctc_wizard_step" value="3">

            <h2 style="font-size:20px; font-weight:800; color:#0F172A; margin:0 0 8px 0; display:flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-file-lines" style="color:#0F766E;"></i> <?php esc_html_e('Dedicated Pages Setup', 'caretochina-medical'); ?>
            </h2>
            <p style="color:#64748B; font-size:14px; margin-bottom:20px;">
                <?php esc_html_e('CareToChina requires dedicated pages for patient/staff portals and legal compliance. You can automatically create them or link existing pages.', 'caretochina-medical'); ?>
            </p>

            <div style="display:flex; flex-direction:column; gap:14px; margin-bottom:24px;">
                <?php foreach ($pages_status as $type => $info) : ?>
                    <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:12px; padding:16px;">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                            <div>
                                <strong style="font-size:14px; color:#0F172A;"><?php echo esc_html($info['title']); ?></strong>
                                <p style="margin:2px 0 0 0; font-size:12px; color:#64748B;"><?php echo esc_html($info['desc']); ?></p>
                            </div>
                            <?php if ($info['is_configured']) : ?>
                                <span style="background:#D1FAE5; color:#065F46; font-size:11px; font-weight:700; padding:4px 8px; border-radius:6px;">
                                    <?php
                                    /* translators: %s: Page title */
                                    printf(esc_html__('Configured: %s', 'caretochina-medical'), esc_html($info['post']->post_title));
                                    ?>
                                </span>
                            <?php else : ?>
                                <span style="background:#FEF3C7; color:#92400E; font-size:11px; font-weight:700; padding:4px 8px; border-radius:6px;">
                                    <?php esc_html_e('Not Configured', 'caretochina-medical'); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div style="display:flex; gap:16px; align-items:center; font-size:13px;">
                            <label style="display:flex; align-items:center; gap:6px; font-weight:600; cursor:pointer;">
                                <input type="radio" name="page_action_<?php echo esc_attr($type); ?>" value="create" <?php checked(!$info['is_configured']); ?> onchange="jQuery('#select-box-<?php echo esc_attr($type); ?>').hide();">
                                <?php echo esc_html($info['is_configured'] ? __('Keep / Re-create New Page', 'caretochina-medical') : __('Create New Page Automatically', 'caretochina-medical')); ?>
                            </label>

                            <label style="display:flex; align-items:center; gap:6px; font-weight:600; cursor:pointer;">
                                <input type="radio" name="page_action_<?php echo esc_attr($type); ?>" value="assign" <?php checked($info['is_configured']); ?> onchange="jQuery('#select-box-<?php echo esc_attr($type); ?>').show();">
                                <?php esc_html_e('Select Existing Page', 'caretochina-medical'); ?>
                            </label>
                        </div>

                        <div id="select-box-<?php echo esc_attr($type); ?>" style="margin-top:10px; <?php echo $info['is_configured'] ? '' : 'display:none;'; ?>">
                            <select name="page_select_<?php echo esc_attr($type); ?>" style="width:100%; max-width:400px; padding:6px; border-radius:6px; border:1px solid #CBD5E1; font-size:13px;">
                                <option value="0"><?php esc_html_e('-- Choose Existing Page --', 'caretochina-medical'); ?></option>
                                <?php foreach ($all_pages as $p) : ?>
                                    <option value="<?php echo esc_attr($p->ID); ?>" <?php selected($info['page_id'], $p->ID); ?>>
                                        <?php echo esc_html($p->post_title); ?> (#<?php echo esc_html($p->ID); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #E2E8F0; padding-top:20px;">
                <a href="<?php echo esc_url($this->get_step_url(4)); ?>" style="color:#64748B; font-weight:600; text-decoration:none;">
                    <?php esc_html_e('Skip this step &rarr;', 'caretochina-medical'); ?>
                </a>
                <button type="submit" class="button button-primary" style="background:#0F766E; border-color:#0F766E; font-weight:700; border-radius:8px; padding:6px 20px;">
                    <?php esc_html_e('Save & Continue to Security &rarr;', 'caretochina-medical'); ?>
                </button>
            </div>
        </form>
        <?php
    }

    /**
     * STEP 4: GOOGLE RECAPTCHA SETUP
     */
    private function render_step_4_recaptcha() {
        $version = CareToChina_Recaptcha::get_version();
        $v2_site = get_option('ctc_recaptcha_v2_site_key', '');
        $v2_sec_masked = CareToChina_Payment_Security::mask_secret(get_option('ctc_recaptcha_v2_secret_key', ''));
        $v3_site = get_option('ctc_recaptcha_v3_site_key', '');
        $v3_sec_masked = CareToChina_Payment_Security::mask_secret(get_option('ctc_recaptcha_v3_secret_key', ''));
        $threshold = CareToChina_Recaptcha::get_threshold();

        $en_login = intval(get_option('ctc_recaptcha_enable_login', 0));
        $en_reg   = intval(get_option('ctc_recaptcha_enable_register', 0));
        $en_book  = intval(get_option('ctc_recaptcha_enable_booking', 0));
        $hide_badge = CareToChina_Recaptcha::is_badge_hidden();
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('ctc_setup_wizard_action', 'ctc_wizard_nonce'); ?>
            <input type="hidden" name="ctc_wizard_step" value="4">

            <h2 style="font-size:20px; font-weight:800; color:#0F172A; margin:0 0 8px 0; display:flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-shield-halved" style="color:#0F766E;"></i> <?php esc_html_e('Google reCAPTCHA Security', 'caretochina-medical'); ?>
            </h2>
            <p style="color:#64748B; font-size:14px; margin-bottom:20px;">
                <?php esc_html_e('Protect patient authentication and booking requests from bots and automated spam. Secrets are automatically encrypted at rest.', 'caretochina-medical'); ?>
            </p>

            <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:12px; padding:20px; margin-bottom:20px;">
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:13px; font-weight:700; color:#334155; margin-bottom:6px;"><?php esc_html_e('Select reCAPTCHA Version', 'caretochina-medical'); ?></label>
                    <div style="display:flex; gap:20px;">
                        <label style="font-weight:600; cursor:pointer;">
                            <input type="radio" name="ctc_recaptcha_version" value="v2" <?php checked($version, 'v2'); ?> onchange="jQuery('#recaptcha-v2-fields').show(); jQuery('#recaptcha-v3-fields').hide();">
                            <?php esc_html_e('reCAPTCHA v2 (Checkbox "I am not a robot")', 'caretochina-medical'); ?>
                        </label>
                        <label style="font-weight:600; cursor:pointer;">
                            <input type="radio" name="ctc_recaptcha_version" value="v3" <?php checked($version, 'v3'); ?> onchange="jQuery('#recaptcha-v3-fields').show(); jQuery('#recaptcha-v2-fields').hide();">
                            <?php esc_html_e('reCAPTCHA v3 (Invisible / Score-based)', 'caretochina-medical'); ?>
                        </label>
                    </div>
                </div>

                <!-- V2 FIELDS -->
                <div id="recaptcha-v2-fields" style="<?php echo $version === 'v2' ? '' : 'display:none;'; ?>">
                    <div style="margin-bottom:12px;">
                        <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;"><?php esc_html_e('reCAPTCHA v2 Site Key', 'caretochina-medical'); ?></label>
                        <input type="text" name="ctc_recaptcha_v2_site_key" value="<?php echo esc_attr($v2_site); ?>" class="regular-text" style="width:100%; max-width:500px; padding:8px; border-radius:6px; border:1px solid #CBD5E1;">
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;"><?php esc_html_e('reCAPTCHA v2 Secret Key', 'caretochina-medical'); ?></label>
                        <input type="password" name="ctc_recaptcha_v2_secret_key" value="<?php echo esc_attr($v2_sec_masked); ?>" class="regular-text" style="width:100%; max-width:500px; padding:8px; border-radius:6px; border:1px solid #CBD5E1;">
                    </div>
                </div>

                <!-- V3 FIELDS -->
                <div id="recaptcha-v3-fields" style="<?php echo $version === 'v3' ? '' : 'display:none;'; ?>">
                    <div style="margin-bottom:12px;">
                        <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;"><?php esc_html_e('reCAPTCHA v3 Site Key', 'caretochina-medical'); ?></label>
                        <input type="text" name="ctc_recaptcha_v3_site_key" value="<?php echo esc_attr($v3_site); ?>" class="regular-text" style="width:100%; max-width:500px; padding:8px; border-radius:6px; border:1px solid #CBD5E1;">
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;"><?php esc_html_e('reCAPTCHA v3 Secret Key', 'caretochina-medical'); ?></label>
                        <input type="password" name="ctc_recaptcha_v3_secret_key" value="<?php echo esc_attr($v3_sec_masked); ?>" class="regular-text" style="width:100%; max-width:500px; padding:8px; border-radius:6px; border:1px solid #CBD5E1;">
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;"><?php esc_html_e('v3 Score Pass Threshold (0.1 - 1.0, Default: 0.5)', 'caretochina-medical'); ?></label>
                        <input type="number" step="0.05" min="0.1" max="1.0" name="ctc_recaptcha_v3_threshold" value="<?php echo esc_attr($threshold); ?>" style="width:120px; padding:8px; border-radius:6px; border:1px solid #CBD5E1;">
                    </div>
                </div>
            </div>

            <!-- LOCATION TOGGLES -->
            <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:12px; padding:20px; margin-bottom:20px;">
                <strong style="display:block; font-size:13px; color:#334155; margin-bottom:10px;"><?php esc_html_e('Enable reCAPTCHA on Forms:', 'caretochina-medical'); ?></strong>
                <div style="display:flex; flex-direction:column; gap:10px; font-size:13px;">
                    <label style="cursor:pointer; font-weight:600;">
                        <input type="checkbox" name="ctc_recaptcha_enable_login" value="1" <?php checked($en_login, 1); ?>>
                        <?php esc_html_e('Patient Login Form', 'caretochina-medical'); ?>
                    </label>
                    <label style="cursor:pointer; font-weight:600;">
                        <input type="checkbox" name="ctc_recaptcha_enable_register" value="1" <?php checked($en_reg, 1); ?>>
                        <?php esc_html_e('Patient Registration Form', 'caretochina-medical'); ?>
                    </label>
                    <label style="cursor:pointer; font-weight:600;">
                        <input type="checkbox" name="ctc_recaptcha_enable_booking" value="1" <?php checked($en_book, 1); ?>>
                        <?php esc_html_e('Booking Wizard Final Submission', 'caretochina-medical'); ?>
                    </label>
                </div>
                <p style="margin:10px 0 0 0; font-size:11px; color:#64748B;">
                    <i class="fa-solid fa-circle-info"></i> <?php esc_html_e('Note: "Continue with Google" OAuth is authenticated directly through Google and is automatically excluded from reCAPTCHA challenges.', 'caretochina-medical'); ?>
                </p>
            </div>

            <!-- BADGE HIDE / TERMS COMPLIANCE -->
            <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:12px; padding:20px; margin-bottom:24px;">
                <strong style="display:block; font-size:13px; color:#334155; margin-bottom:10px;"><?php esc_html_e('Badge Display & Google Attribution:', 'caretochina-medical'); ?></strong>
                <label style="cursor:pointer; font-weight:600; display:flex; align-items:flex-start; gap:8px;">
                    <input type="checkbox" name="ctc_recaptcha_hide_badge" value="1" <?php checked($hide_badge, true); ?> style="margin-top:2px;">
                    <span>
                        <?php esc_html_e('Hide floating reCAPTCHA badge', 'caretochina-medical'); ?>
                        <span style="display:block; margin-top:4px; font-weight:normal; font-size:12px; color:#64748B; line-height:1.4;">
                            <?php esc_html_e('Hides the floating bottom-right badge via CSS and automatically displays Google\'s required attribution text ("This site is protected by reCAPTCHA and the Google Privacy Policy and Terms of Service apply") below active protected forms to comply with Google Terms of Service.', 'caretochina-medical'); ?>
                        </span>
                    </span>
                </label>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #E2E8F0; padding-top:20px;">
                <a href="<?php echo esc_url($this->get_step_url(5)); ?>" style="color:#64748B; font-weight:600; text-decoration:none;">
                    <?php esc_html_e('Skip this step &rarr;', 'caretochina-medical'); ?>
                </a>
                <button type="submit" class="button button-primary" style="background:#0F766E; border-color:#0F766E; font-weight:700; border-radius:8px; padding:6px 20px;">
                    <?php esc_html_e('Save & Continue to Google Login &rarr;', 'caretochina-medical'); ?>
                </button>
            </div>
        </form>
        <?php
    }

    /**
     * STEP 5: GOOGLE LOGIN SETUP
     */
    private function render_step_5_google() {
        $client_id = get_option('ctc_google_client_id', '');
        $client_sec_masked = CareToChina_Payment_Security::mask_secret(get_option('ctc_google_client_secret', ''));
        $redirect_uri = home_url('/?ctc_google_callback=1');
        $is_configured = !empty($client_id) && !empty($client_sec_masked);
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('ctc_setup_wizard_action', 'ctc_wizard_nonce'); ?>
            <input type="hidden" name="ctc_wizard_step" value="5">

            <h2 style="font-size:20px; font-weight:800; color:#0F172A; margin:0 0 8px 0; display:flex; align-items:center; gap:8px;">
                <i class="fa-brands fa-google" style="color:#EA4335;"></i> <?php esc_html_e('Google OAuth 2.0 Patient Sign-In', 'caretochina-medical'); ?>
            </h2>
            <p style="color:#64748B; font-size:14px; margin-bottom:20px;">
                <?php esc_html_e('Allow patients to sign in with 1-click using their verified Google account. Secrets are encrypted at rest.', 'caretochina-medical'); ?>
            </p>

            <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:12px; padding:20px; margin-bottom:24px;">
                <div style="margin-bottom:14px;">
                    <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;"><?php esc_html_e('Google OAuth Client ID', 'caretochina-medical'); ?></label>
                    <input type="text" name="ctc_google_client_id" value="<?php echo esc_attr($client_id); ?>" class="regular-text" style="width:100%; max-width:550px; padding:8px; border-radius:6px; border:1px solid #CBD5E1;" placeholder="1234567890-example.clientid">
                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;"><?php esc_html_e('Google OAuth Client Secret', 'caretochina-medical'); ?></label>
                    <input type="password" name="ctc_google_client_secret" value="<?php echo esc_attr($client_sec_masked); ?>" class="regular-text" style="width:100%; max-width:550px; padding:8px; border-radius:6px; border:1px solid #CBD5E1;" placeholder="GOCSPX-xxxx">
                </div>

                <div style="background:#FFF; border:1px dashed #CBD5E1; border-radius:8px; padding:12px;">
                    <strong style="display:block; font-size:12px; color:#334155; margin-bottom:4px;"><?php esc_html_e('Authorized Redirect URI (Paste into Google Cloud Console):', 'caretochina-medical'); ?></strong>
                    <code style="font-size:13px; color:#0F766E; font-weight:700;"><?php echo esc_html($redirect_uri); ?></code>
                </div>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #E2E8F0; padding-top:20px;">
                <a href="<?php echo esc_url($this->get_step_url(6)); ?>" style="color:#64748B; font-weight:600; text-decoration:none;">
                    <?php esc_html_e('Skip this step &rarr;', 'caretochina-medical'); ?>
                </a>
                <button type="submit" class="button button-primary" style="background:#0F766E; border-color:#0F766E; font-weight:700; border-radius:8px; padding:6px 20px;">
                    <?php esc_html_e('Save & Finish Setup &rarr;', 'caretochina-medical'); ?>
                </button>
            </div>
        </form>
        <?php
    }

    /**
     * STEP 6: FINISH & SUMMARY
     */
    private function render_step_6_finish() {
        $delete_on_uninstall = intval(get_option('ctc_delete_data_on_uninstall', 0));
        $export_nonce = wp_create_nonce('ctc_export_data_nonce');
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('ctc_setup_wizard_action', 'ctc_wizard_nonce'); ?>
            <input type="hidden" name="ctc_wizard_step" value="6">

            <div style="text-align:center; margin-bottom:24px;">
                <div style="width:64px; height:64px; background:#D1FAE5; color:#065F46; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:28px; margin-bottom:14px;">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <h2 style="font-size:22px; font-weight:800; color:#0F172A; margin:0 0 6px 0;"><?php esc_html_e('Setup Complete!', 'caretochina-medical'); ?></h2>
                <p style="color:#64748B; font-size:14px; margin:0;"><?php esc_html_e('Your CareToChina medical platform is configured and ready.', 'caretochina-medical'); ?></p>
            </div>

            <!-- UNINSTALL & EXPORT SECTION -->
            <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:14px; padding:20px; margin-bottom:24px;">
                <h4 style="margin:0 0 10px 0; font-size:14px; color:#0F172A; font-weight:800; display:flex; align-items:center; gap:8px;">
                    <i class="fa-solid fa-database" style="color:#0F766E;"></i> <?php esc_html_e('Data Safety & Backup Management', 'caretochina-medical'); ?>
                </h4>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                    <div>
                        <strong style="font-size:13px; color:#334155; display:block;"><?php esc_html_e('Delete all plugin data on uninstall', 'caretochina-medical'); ?></strong>
                        <span style="font-size:12px; color:#64748B;"><?php esc_html_e('Default is OFF (data preserved). If enabled, a safety-net backup is created before table cleanup.', 'caretochina-medical'); ?></span>
                    </div>
                    <label class="switch" style="position:relative; display:inline-block; width:44px; height:24px;">
                        <input type="checkbox" name="ctc_delete_data_on_uninstall" value="1" <?php checked($delete_on_uninstall, 1); ?>>
                        <span class="slider round" style="position:absolute; cursor:pointer; top:0; left:0; right:0; bottom:0; background-color:#CBD5E1; border-radius:24px; transition:.3s;"></span>
                    </label>
                </div>

                <div style="border-top:1px dashed #E2E8F0; padding-top:14px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <strong style="font-size:13px; color:#334155; display:block;"><?php esc_html_e('Export Database Dump On-Demand', 'caretochina-medical'); ?></strong>
                        <span style="font-size:12px; color:#64748B;"><?php esc_html_e('Download full SQL export of all patient bookings, plans, and audit logs anytime.', 'caretochina-medical'); ?></span>
                    </div>
                    <a href="<?php echo esc_url(admin_url('admin-post.php?action=ctc_export_plugin_data&_wpnonce=' . $export_nonce)); ?>" class="button" style="font-weight:700; border-radius:8px;">
                        <i class="fa-solid fa-download"></i> <?php esc_html_e('Export Data Now', 'caretochina-medical'); ?>
                    </a>
                </div>
            </div>

            <div style="display:flex; justify-content:center; gap:16px; border-top:1px solid #E2E8F0; padding-top:20px;">
                <button type="submit" class="button button-primary button-hero" style="background:#0F766E; border-color:#0F766E; font-weight:700; border-radius:10px; padding:8px 32px;">
                    <i class="fa-solid fa-check"></i> <?php esc_html_e('Go to Staff Desk &rarr;', 'caretochina-medical'); ?>
                </button>
            </div>
        </form>
        <?php
    }
}
