<?php
if (!defined('ABSPATH')) {
    exit;
}

class CareToChina_Page_Manager {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Hooks can be added here if needed
    }

    /**
     * Get the configured page ID for a specific page type
     *
     * @param string $type patient_dashboard | staff_portal | privacy_policy | terms
     * @return int
     */
    public static function get_page_id($type) {
        $option_name = 'ctc_page_' . sanitize_key($type);
        $page_id = intval(get_option($option_name, 0));

        if ($type === 'privacy_policy' && $page_id <= 0) {
            $wp_privacy = intval(get_option('wp_page_for_privacy_policy', 0));
            if ($wp_privacy > 0) {
                return $wp_privacy;
            }
        }

        return $page_id;
    }

    /**
     * Get the public URL for a specific page type
     * Falls back to standard slug URL if page is not explicitly configured
     *
     * @param string $type
     * @return string
     */
    public static function get_page_url($type) {
        $page_id = self::get_page_id($type);

        if ($page_id > 0) {
            $post = get_post($page_id);
            if ($post && $post->post_status === 'publish') {
                return get_permalink($page_id);
            }
        }

        // Fallbacks based on default slugs
        switch ($type) {
            case 'patient_dashboard':
                return home_url('/patient-dashboard/');
            case 'staff_portal':
                return home_url('/staff-portal/');
            case 'patient_login':
                return home_url('/patient-login/');
            case 'patient_register':
                return home_url('/patient-login/?tab=register');
            case 'privacy_policy':
                return home_url('/privacy-policy/');
            case 'terms':
                return home_url('/terms-and-conditions/');
            default:
                return home_url('/patient-login/');
        }
    }

    /**
     * Get the default page definition
     */
    public static function get_default_page_definitions() {
        return [
            'patient_dashboard' => [
                'title'     => __('Patient Portal & Dashboard', 'caretochina-medical'),
                'shortcode' => '[caretochina_patient_dashboard]',
                'slug'      => 'patient-dashboard',
                'desc'      => __('Live patient portal with consultation timeline, chat, and invoices.', 'caretochina-medical'),
            ],
            'staff_portal' => [
                'title'     => __('Staff Coordinator Desk', 'caretochina-medical'),
                'shortcode' => '[caretochina_staff_portal]',
                'slug'      => 'staff-portal',
                'desc'      => __('Coordinator messaging desk, case management, and payment requests.', 'caretochina-medical'),
            ],
            'patient_login' => [
                'title'     => __('Patient Sign In & Register', 'caretochina-medical'),
                'shortcode' => '[caretochina_auth_portal]',
                'slug'      => 'patient-login',
                'desc'      => __('Patient login and account registration portal.', 'caretochina-medical'),
            ],
            'privacy_policy' => [
                'title'     => __('Privacy Policy', 'caretochina-medical'),
                'shortcode' => '<!-- wp:paragraph --><p>CareToChina Medical is committed to safeguarding the privacy and confidentiality of patient medical data in compliance with international data privacy standards.</p><!-- /wp:paragraph -->',
                'slug'      => 'privacy-policy',
                'desc'      => __('Medical data privacy and confidentiality policy.', 'caretochina-medical'),
            ],
            'terms' => [
                'title'     => __('Terms & Conditions', 'caretochina-medical'),
                'shortcode' => '<!-- wp:paragraph --><p>By using CareToChina Medical Concierge services, patients agree to our medical travel terms, payment policies, and hospital referral guidelines.</p><!-- /wp:paragraph -->',
                'slug'      => 'terms-and-conditions',
                'desc'      => __('Medical travel consultation terms and treatment conditions.', 'caretochina-medical'),
            ],
        ];
    }

    /**
     * Create a new page or assign an existing page
     *
     * @param string $type
     * @param int $existing_page_id (0 to create new)
     * @param string $custom_title
     * @return int|WP_Error Page ID
     */
    public static function create_or_assign_page($type, $existing_page_id = 0, $custom_title = '') {
        $defs = self::get_default_page_definitions();
        if (!isset($defs[$type])) {
            return new WP_Error('invalid_type', __('Invalid page type.', 'caretochina-medical'));
        }

        $def = $defs[$type];

        if ($existing_page_id > 0) {
            $post = get_post($existing_page_id);
            if (!$post) {
                return new WP_Error('not_found', __('Selected page does not exist.', 'caretochina-medical'));
            }
            update_option('ctc_page_' . $type, $existing_page_id);
            return $existing_page_id;
        }

        // Check if page with exact slug already exists to prevent duplicate titles/slugs
        $existing = get_page_by_path($def['slug']);
        if ($existing && $existing->post_status !== 'trash') {
            update_option('ctc_page_' . $type, $existing->ID);
            return $existing->ID;
        }

        $title = !empty($custom_title) ? $custom_title : $def['title'];
        $page_id = wp_insert_post([
            'post_title'     => $title,
            'post_name'      => $def['slug'],
            'post_content'   => $def['shortcode'],
            'post_status'    => 'publish',
            'post_type'      => 'page',
            'comment_status' => 'closed',
        ]);

        if (is_wp_error($page_id) || !$page_id) {
            return new WP_Error('insert_failed', __('Failed to create page.', 'caretochina-medical'));
        }

        update_option('ctc_page_' . $type, $page_id);
        return $page_id;
    }

    /**
     * Get configuration status for all 4 pages
     */
    public static function get_all_pages_status() {
        $defs = self::get_default_page_definitions();
        $statuses = [];

        foreach ($defs as $type => $def) {
            $page_id = self::get_page_id($type);
            $is_configured = false;
            $post = null;

            if ($page_id > 0) {
                $post = get_post($page_id);
                if ($post && $post->post_status === 'publish') {
                    $is_configured = true;
                }
            }

            $statuses[$type] = [
                'type'          => $type,
                'title'         => $def['title'],
                'desc'          => $def['desc'],
                'slug'          => $def['slug'],
                'page_id'       => $page_id,
                'is_configured' => $is_configured,
                'post'          => $post,
                'url'           => self::get_page_url($type),
            ];
        }

        return $statuses;
    }
}
