<?php
if (!defined('ABSPATH')) {
    exit;
}

class CareToChina_Google_Login {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('init', [$this, 'handle_google_callback']);
        add_action('init', [$this, 'handle_link_account_submission']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    public static function get_client_id() {
        if (defined('CARETOCHINA_GOOGLE_CLIENT_ID')) {
            return CARETOCHINA_GOOGLE_CLIENT_ID;
        }
        return get_option('ctc_google_client_id', '');
    }

    public static function get_client_secret() {
        if (defined('CARETOCHINA_GOOGLE_CLIENT_SECRET')) {
            return CARETOCHINA_GOOGLE_CLIENT_SECRET;
        }
        $encrypted = get_option('ctc_google_client_secret', '');
        return CareToChina_Payment_Security::decrypt_secret($encrypted);
    }

    public static function get_redirect_uri() {
        return home_url('/?ctc_google_callback=1');
    }

    public static function is_enabled() {
        $client_id = self::get_client_id();
        $client_secret = self::get_client_secret();
        return !empty($client_id) && !empty($client_secret);
    }

    /**
     * Generate OAuth 2.0 authorization URL
     */
    public static function get_auth_url() {
        if (!self::is_enabled()) {
            return '#';
        }

        $client_id = self::get_client_id();
        $redirect_uri = self::get_redirect_uri();

        // Generate and store CSRF state token in transient (valid for 15 minutes)
        $state = wp_generate_password(32, false);
        $state_key = 'ctc_g_state_' . md5($state);
        set_transient($state_key, time(), 900);

        $params = [
            'client_id'             => $client_id,
            'redirect_uri'          => $redirect_uri,
            'response_type'         => 'code',
            'scope'                 => 'openid email profile',
            'state'                 => $state,
            'access_type'           => 'online',
            'prompt'                => 'select_account',
            'include_granted_scopes'=> 'true',
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    public function register_rest_routes() {
        register_rest_route('caretochina/v1', '/auth/google/callback', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handle_rest_callback'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Handle OAuth callback from Google
     */
    public function handle_google_callback() {
        if (!isset($_GET['ctc_google_callback'])) {
            return;
        }

        // Check for OAuth error response
        if (!empty($_GET['error'])) {
            $error_desc = sanitize_text_field($_GET['error_description'] ?? $_GET['error']);
            $this->redirect_with_error(sprintf(__('Google authentication failed: %s', 'caretochina-medical'), $error_desc));
            return;
        }

        $code = sanitize_text_field($_GET['code'] ?? '');
        $state = sanitize_text_field($_GET['state'] ?? '');

        if (empty($code) || empty($state)) {
            $this->redirect_with_error(__('Invalid Google authentication response.', 'caretochina-medical'));
            return;
        }

        // CSRF State validation
        $state_key = 'ctc_g_state_' . md5($state);
        if (!get_transient($state_key)) {
            $this->redirect_with_error(__('Security verification expired. Please try logging in again.', 'caretochina-medical'));
            return;
        }
        delete_transient($state_key);

        // Exchange Authorization Code for Tokens
        $client_id = self::get_client_id();
        $client_secret = self::get_client_secret();
        $redirect_uri = self::get_redirect_uri();

        $token_response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'code'          => $code,
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri'  => $redirect_uri,
                'grant_type'    => 'authorization_code',
            ],
            'timeout' => 20,
        ]);

        if (is_wp_error($token_response)) {
            $this->redirect_with_error(__('Unable to communicate with Google authentication servers.', 'caretochina-medical'));
            return;
        }

        $token_body = json_decode(wp_remote_retrieve_body($token_response), true);
        if (empty($token_body['access_token'])) {
            $this->redirect_with_error(__('Failed to retrieve authentication token from Google.', 'caretochina-medical'));
            return;
        }

        // Fetch User Info from Google
        $userinfo_response = wp_remote_get('https://www.googleapis.com/oauth2/v3/userinfo', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token_body['access_token'],
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($userinfo_response)) {
            $this->redirect_with_error(__('Failed to fetch Google profile info.', 'caretochina-medical'));
            return;
        }

        $userinfo = json_decode(wp_remote_retrieve_body($userinfo_response), true);
        if (empty($userinfo['email']) || empty($userinfo['sub'])) {
            $this->redirect_with_error(__('Incomplete Google account profile received.', 'caretochina-medical'));
            return;
        }

        // SECURITY CHECK 1: email_verified claim verification
        $email_verified = isset($userinfo['email_verified']) && ($userinfo['email_verified'] === true || $userinfo['email_verified'] === 'true');
        if (!$email_verified) {
            $this->redirect_with_error(__('Your Google email address is unverified. Please verify your email with Google before signing in.', 'caretochina-medical'));
            return;
        }

        $google_email = sanitize_email($userinfo['email']);
        $google_sub   = sanitize_text_field($userinfo['sub']);
        $given_name   = sanitize_text_field($userinfo['given_name'] ?? '');
        $family_name  = sanitize_text_field($userinfo['family_name'] ?? '');
        $full_name    = sanitize_text_field($userinfo['name'] ?? ($given_name . ' ' . $family_name));

        // SECURITY CHECK 2: Staff / Admin Denial (Patient-only)
        $existing_user = get_user_by('email', $google_email);
        if ($existing_user) {
            $staff_roles = ['administrator', 'editor', 'medical_staff'];
            $user_roles = (array)$existing_user->roles;
            if (array_intersect($staff_roles, $user_roles)) {
                $this->redirect_with_error(__('Google Sign-In is restricted to patients only. Staff and administrator accounts must sign in using the official staff portal.', 'caretochina-medical'));
                return;
            }
        }

        // 1. Check if user exists by Google Sub meta (already linked)
        $sub_query = get_users([
            'meta_key'   => '_ctc_google_sub',
            'meta_value' => $google_sub,
            'number'     => 1,
        ]);

        if (!empty($sub_query)) {
            $user = $sub_query[0];
            $this->login_user_and_redirect($user);
            return;
        }

        // 2. Check if user exists by Email (registered via password) -> REQUIRE EXPLICIT ACCOUNT LINKING / PASSWORD CONFIRMATION
        if ($existing_user) {
            $linked_sub = get_user_meta($existing_user->ID, '_ctc_google_sub', true);
            if (!empty($linked_sub) && $linked_sub !== $google_sub) {
                $this->redirect_with_error(__('This email is already associated with a different Google account.', 'caretochina-medical'));
                return;
            }

            // Generate one-time account linking token (15 minute validity)
            $link_token = wp_generate_password(32, false);
            $pending_key = 'ctc_link_pending_' . md5($link_token);
            set_transient($pending_key, [
                'user_id'      => $existing_user->ID,
                'google_sub'   => $google_sub,
                'google_email' => $google_email,
                'full_name'    => $full_name,
            ], 900);

            // Redirect to explicit password confirmation linking screen
            $link_url = add_query_arg([
                'ctc_link_account' => 1,
                'token'            => urlencode($link_token),
                'email'            => urlencode($google_email),
            ], home_url('/patient-login/'));

            wp_safe_redirect($link_url);
            exit;
        }

        // 3. Auto-Register New Patient Account
        $username = sanitize_user(explode('@', $google_email)[0], true);
        if (username_exists($username)) {
            $username .= '_' . wp_rand(100, 999);
        }

        $random_password = wp_generate_password(24, true, true);
        $new_user_id = wp_insert_user([
            'user_login'   => $username,
            'user_pass'    => $random_password,
            'user_email'   => $google_email,
            'display_name' => $full_name ?: $username,
            'first_name'   => $given_name,
            'last_name'    => $family_name,
            'role'         => 'patient',
        ]);

        if (is_wp_error($new_user_id)) {
            $this->redirect_with_error(__('Failed to create patient account. Please try again or use standard registration.', 'caretochina-medical'));
            return;
        }

        // Set Google sub and profile meta
        update_user_meta($new_user_id, '_ctc_google_sub', $google_sub);
        update_user_meta($new_user_id, 'patient_registered_via', 'google_oauth');

        $new_user = get_user_by('id', $new_user_id);
        $this->login_user_and_redirect($new_user);
    }

    /**
     * Handle explicit account linking password verification POST
     */
    public function handle_link_account_submission() {
        if (!isset($_POST['ctc_submit_link_account'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['ctc_link_nonce'] ?? '', 'ctc_link_account_action')) {
            $this->redirect_with_error(__('Security verification failed. Please try linking again.', 'caretochina-medical'));
            return;
        }

        $token = sanitize_text_field($_POST['link_token'] ?? '');
        $password = $_POST['account_password'] ?? '';

        if (empty($token) || empty($password)) {
            $this->redirect_with_error(__('Please enter your account password to confirm account linking.', 'caretochina-medical'));
            return;
        }

        $pending_key = 'ctc_link_pending_' . md5($token);
        $pending_data = get_transient($pending_key);

        if (!$pending_data || empty($pending_data['user_id'])) {
            $this->redirect_with_error(__('Account linking session expired. Please sign in with Google again.', 'caretochina-medical'));
            return;
        }

        $user = get_userdata($pending_data['user_id']);
        if (!$user) {
            $this->redirect_with_error(__('Patient account not found.', 'caretochina-medical'));
            return;
        }

        // Verify existing password
        if (!wp_check_password($password, $user->user_pass, $user->ID)) {
            $link_url = add_query_arg([
                'ctc_link_account' => 1,
                'token'            => urlencode($token),
                'email'            => urlencode($pending_data['google_email']),
                'ctc_link_error'   => urlencode(__('Incorrect password. Please enter the valid password for your existing patient account.', 'caretochina-medical')),
            ], home_url('/patient-login/'));

            wp_safe_redirect($link_url);
            exit;
        }

        // Password verified! Link Google sub claim to user meta
        update_user_meta($user->ID, '_ctc_google_sub', sanitize_text_field($pending_data['google_sub']));
        delete_transient($pending_key);

        $this->login_user_and_redirect($user);
    }

    private function login_user_and_redirect($user) {
        wp_clear_auth_cookie();
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);
        do_action('wp_login', $user->user_login, $user);

        wp_safe_redirect(home_url('/patient-dashboard/'));
        exit;
    }

    private function redirect_with_error($message) {
        $login_url = home_url('/patient-login/');
        $url = add_query_arg(['ctc_auth_error' => urlencode($message)], $login_url);
        wp_safe_redirect($url);
        exit;
    }
}
