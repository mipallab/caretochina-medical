<?php
if (!defined('ABSPATH')) exit;

class CareToChina_Country_Helper {

    private static $countries = [
        ['code' => 'US', 'name' => 'United States', 'dial_code' => '+1', 'flag' => '🇺🇸'],
        ['code' => 'CN', 'name' => 'China', 'dial_code' => '+86', 'flag' => '🇨🇳'],
        ['code' => 'GB', 'name' => 'United Kingdom', 'dial_code' => '+44', 'flag' => '🇬🇧'],
        ['code' => 'CA', 'name' => 'Canada', 'dial_code' => '+1', 'flag' => '🇨🇦'],
        ['code' => 'AU', 'name' => 'Australia', 'dial_code' => '+61', 'flag' => '🇦🇺'],
        ['code' => 'DZ', 'name' => 'Algeria', 'dial_code' => '+213', 'flag' => '🇩🇿'],
        ['code' => 'BD', 'name' => 'Bangladesh', 'dial_code' => '+880', 'flag' => '🇧🇩'],
        ['code' => 'IN', 'name' => 'India', 'dial_code' => '+91', 'flag' => '🇮🇳'],
        ['code' => 'AE', 'name' => 'United Arab Emirates', 'dial_code' => '+971', 'flag' => '🇦🇪'],
        ['code' => 'SA', 'name' => 'Saudi Arabia', 'dial_code' => '+966', 'flag' => '🇸🇦'],
        ['code' => 'QA', 'name' => 'Qatar', 'dial_code' => '+974', 'flag' => '🇶🇦'],
        ['code' => 'KW', 'name' => 'Kuwait', 'dial_code' => '+965', 'flag' => '🇰🇼'],
        ['code' => 'OM', 'name' => 'Oman', 'dial_code' => '+968', 'flag' => '🇴🇲'],
        ['code' => 'DE', 'name' => 'Germany', 'dial_code' => '+49', 'flag' => '🇩🇪'],
        ['code' => 'FR', 'name' => 'France', 'dial_code' => '+33', 'flag' => '🇫🇷'],
        ['code' => 'IT', 'name' => 'Italy', 'dial_code' => '+39', 'flag' => '🇮🇹'],
        ['code' => 'ES', 'name' => 'Spain', 'dial_code' => '+34', 'flag' => '🇪🇸'],
        ['code' => 'NL', 'name' => 'Netherlands', 'dial_code' => '+31', 'flag' => '🇳🇱'],
        ['code' => 'CH', 'name' => 'Switzerland', 'dial_code' => '+41', 'flag' => '🇨🇭'],
        ['code' => 'SE', 'name' => 'Sweden', 'dial_code' => '+46', 'flag' => '🇸🇪'],
        ['code' => 'NO', 'name' => 'Norway', 'dial_code' => '+47', 'flag' => '🇳🇴'],
        ['code' => 'NZ', 'name' => 'New Zealand', 'dial_code' => '+64', 'flag' => '🇳🇿'],
        ['code' => 'SG', 'name' => 'Singapore', 'dial_code' => '+65', 'flag' => '🇸🇬'],
        ['code' => 'MY', 'name' => 'Malaysia', 'dial_code' => '+60', 'flag' => '🇲🇾'],
        ['code' => 'PH', 'name' => 'Philippines', 'dial_code' => '+63', 'flag' => '🇵🇭'],
        ['code' => 'ID', 'name' => 'Indonesia', 'dial_code' => '+62', 'flag' => '🇮🇩'],
        ['code' => 'TH', 'name' => 'Thailand', 'dial_code' => '+66', 'flag' => '🇹🇭'],
        ['code' => 'VN', 'name' => 'Vietnam', 'dial_code' => '+84', 'flag' => '🇻🇳'],
        ['code' => 'JP', 'name' => 'Japan', 'dial_code' => '+81', 'flag' => '🇯🇵'],
        ['code' => 'KR', 'name' => 'South Korea', 'dial_code' => '+82', 'flag' => '🇰🇷'],
        ['code' => 'PK', 'name' => 'Pakistan', 'dial_code' => '+92', 'flag' => '🇵🇰'],
        ['code' => 'EG', 'name' => 'Egypt', 'dial_code' => '+20', 'flag' => '🇪🇬'],
        ['code' => 'TR', 'name' => 'Turkey', 'dial_code' => '+90', 'flag' => '🇹🇷'],
        ['code' => 'RU', 'name' => 'Russia', 'dial_code' => '+7', 'flag' => '🇷🇺'],
        ['code' => 'BR', 'name' => 'Brazil', 'dial_code' => '+55', 'flag' => '🇧🇷'],
        ['code' => 'MX', 'name' => 'Mexico', 'dial_code' => '+52', 'flag' => '🇲🇽'],
        ['code' => 'ZA', 'name' => 'South Africa', 'dial_code' => '+27', 'flag' => '🇿🇦'],
        ['code' => 'NG', 'name' => 'Nigeria', 'dial_code' => '+234', 'flag' => '🇳🇬'],
        ['code' => 'KE', 'name' => 'Kenya', 'dial_code' => '+254', 'flag' => '🇰🇪'],
        ['code' => 'GH', 'name' => 'Ghana', 'dial_code' => '+233', 'flag' => '🇬🇭'],
        ['code' => 'IE', 'name' => 'Ireland', 'dial_code' => '+353', 'flag' => '🇮🇪'],
        ['code' => 'BE', 'name' => 'Belgium', 'dial_code' => '+32', 'flag' => '🇧🇪'],
        ['code' => 'AT', 'name' => 'Austria', 'dial_code' => '+43', 'flag' => '🇦🇹'],
        ['code' => 'DK', 'name' => 'Denmark', 'dial_code' => '+45', 'flag' => '🇩🇰'],
        ['code' => 'FI', 'name' => 'Finland', 'dial_code' => '+358', 'flag' => '🇫🇮'],
        ['code' => 'PL', 'name' => 'Poland', 'dial_code' => '+48', 'flag' => '🇵🇱'],
        ['code' => 'PT', 'name' => 'Portugal', 'dial_code' => '+351', 'flag' => '🇵🇹'],
        ['code' => 'GR', 'name' => 'Greece', 'dial_code' => '+30', 'flag' => '🇬🇷'],
        ['code' => 'LK', 'name' => 'Sri Lanka', 'dial_code' => '+94', 'flag' => '🇱🇰'],
        ['code' => 'NP', 'name' => 'Nepal', 'dial_code' => '+977', 'flag' => '🇳🇵'],
    ];

    public static function get_countries() {
        return self::$countries;
    }

    public static function is_enabled() {
        return (bool) get_option('ctc_enable_intl_phone_flags', 1);
    }

    public static function get_format() {
        return get_option('ctc_phone_selector_format', 'both');
    }

    /**
     * Parse an existing phone string into dial code and pure number
     */
    public static function parse_phone($full_phone, $default_code = '+1') {
        $full_phone = trim((string)$full_phone);
        if (empty($full_phone)) {
            return ['code' => $default_code, 'number' => ''];
        }

        foreach (self::$countries as $c) {
            $dial = $c['dial_code'];
            if (strpos($full_phone, $dial) === 0) {
                $num = trim(substr($full_phone, strlen($dial)));
                return ['code' => $dial, 'number' => $num];
            }
        }

        if (preg_match('/^(\+\d{1,4})\s*(.*)$/', $full_phone, $m)) {
            return ['code' => $m[1], 'number' => trim($m[2])];
        }

        return ['code' => $default_code, 'number' => $full_phone];
    }

    /**
     * Render the dual-field Phone Input Group
     */
    public static function render_phone_input_group($field_name, $current_value = '', $required = false, $placeholder = 'e.g. 555-0199', $id = '') {
        $enabled = self::is_enabled();
        $format = self::get_format();
        $id_attr = !empty($id) ? 'id="' . esc_attr($id) . '"' : '';
        $req_attr = $required ? 'required' : '';

        if (!$enabled) {
            return '<input type="tel" name="' . esc_attr($field_name) . '" ' . $id_attr . ' value="' . esc_attr($current_value) . '" class="form-input" placeholder="' . esc_attr($placeholder) . '" ' . $req_attr . '>';
        }

        $parsed = self::parse_phone($current_value, '+1');
        $code_field = $field_name . '_country_code';

        $select_style = 'width: 136px; min-width: 136px; max-width: 145px;';
        if ($format === 'flag') {
            $select_style = 'width: 76px; min-width: 76px; max-width: 76px; font-size: 15px;';
        } elseif ($format === 'code') {
            $select_style = 'width: 96px; min-width: 96px; max-width: 96px;';
        }

        $html = '<div class="ctc-phone-group-wrapper" data-selector-format="' . esc_attr($format) . '">';
        $html .= '<select name="' . esc_attr($code_field) . '" class="ctc-country-select" data-format="' . esc_attr($format) . '" style="' . esc_attr($select_style) . '">';
        foreach (self::$countries as $c) {
            $sel = ($parsed['code'] === $c['dial_code']) ? 'selected' : '';
            if ($format === 'flag') {
                $label = $c['flag'] . ' (' . $c['code'] . ')';
            } elseif ($format === 'code') {
                $label = $c['dial_code'] . ' (' . $c['code'] . ')';
            } else {
                // both
                $label = $c['flag'] . ' ' . $c['dial_code'] . ' (' . $c['code'] . ')';
            }
            $html .= '<option value="' . esc_attr($c['dial_code']) . '" data-dial="' . esc_attr($c['dial_code']) . '" data-flag="' . esc_attr($c['flag']) . '" ' . $sel . '>' . esc_html($label) . '</option>';
        }
        $html .= '</select>';
        $html .= '<input type="tel" name="' . esc_attr($field_name) . '" ' . $id_attr . ' value="' . esc_attr($parsed['number']) . '" class="ctc-phone-input" placeholder="' . esc_attr($placeholder) . '" ' . $req_attr . ' autocomplete="tel-national">';
        $html .= '</div>';

        return $html;
    }

    /**
     * Combine submitted country code and number on POST with auto-strip of duplicate country codes
     */
    public static function extract_submitted_phone($post_data, $field_name) {
        $raw_number = sanitize_text_field($post_data[$field_name] ?? '');
        $code_field = $field_name . '_country_code';
        $country_code = sanitize_text_field($post_data[$code_field] ?? '');

        if (empty($raw_number)) {
            return '';
        }

        $clean_num = trim($raw_number);
        $clean_code = trim($country_code);

        // If number begins with + or country code, normalize it
        if (!empty($clean_code)) {
            $code_digits = ltrim($clean_code, '+');
            
            // If user typed e.g. +8801749949010 or +880 1749949010
            if (strpos($clean_num, $clean_code) === 0) {
                $clean_num = trim(substr($clean_num, strlen($clean_code)));
            } elseif (strpos($clean_num, '+' . $code_digits) === 0) {
                $clean_num = trim(substr($clean_num, strlen('+' . $code_digits)));
            } elseif (strpos($clean_num, $code_digits) === 0 && strlen($clean_num) > strlen($code_digits) + 5) {
                // If typed 8801749949010
                $clean_num = trim(substr($clean_num, strlen($code_digits)));
            }
        }

        if (strpos($clean_num, '+') === 0) {
            return $clean_num;
        }

        if (!empty($clean_code)) {
            return $clean_code . ' ' . $clean_num;
        }

        return $clean_num;
    }
}
