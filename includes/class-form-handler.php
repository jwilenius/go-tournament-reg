<?php
/**
 * Form handler for Go Tournament Registration
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class GTR_Form_Handler {

    public function __construct() {
        add_action('init', array($this, 'handle_form_submission'));
    }

    /**
     * Check rate limit for form submissions
     * @return bool True if within limit, false if rate limited
     */
    private function check_rate_limit() {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
        $transient_key = 'gtr_rate_' . md5($ip);
        $attempts = get_transient($transient_key);

        if ($attempts !== false && $attempts >= 5) {
            return false; // Rate limited: 5 submissions per 10 minutes
        }

        set_transient($transient_key, ($attempts ?: 0) + 1, 600);
        return true;
    }

    /**
     * Handle form submission
     */
    public function handle_form_submission() {
        if (!isset($_POST['gtr_submit']) || !isset($_POST['gtr_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['gtr_nonce'], 'gtr_registration_form')) {
            wp_die('Security check failed');
        }

        // Check rate limit
        if (!$this->check_rate_limit()) {
            set_transient('gtr_form_errors', array('rate_limit' => 'Too many submissions. Please try again later.'), 45);
            return;
        }

        $errors = $this->validate_form_data($_POST);

        if (!empty($errors)) {
            set_transient('gtr_form_errors', $errors, 45);
            // Store sanitized form data in transient (not raw $_POST)
            $sanitized_data = array(
                'tournament_slug' => sanitize_text_field($_POST['tournament_slug'] ?? ''),
                'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
                'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
                'player_strength' => sanitize_text_field($_POST['player_strength'] ?? ''),
                'country' => sanitize_text_field($_POST['country'] ?? ''),
                'email' => sanitize_email($_POST['email'] ?? ''),
                'egd_number' => sanitize_text_field($_POST['egd_number'] ?? ''),
                'phone_number' => sanitize_text_field($_POST['phone_number'] ?? ''),
            );
            set_transient('gtr_form_data', $sanitized_data, 45);
            return;
        }

        // Insert registration
        $success = GTR_Database::insert_registration($_POST);

        if ($success) {
            set_transient('gtr_form_success', 'Registration successful!', 45);
            // Redirect to prevent form resubmission (validate redirect URL to prevent open redirect)
            $referer = wp_get_referer();
            $redirect_url = ($referer && wp_validate_redirect($referer, false))
                ? add_query_arg('registered', '1', $referer)
                : add_query_arg('registered', '1', home_url());
            wp_redirect($redirect_url);
            exit;
        } else {
            set_transient('gtr_form_errors', array('database' => 'Failed to save registration. Please try again.'), 45);
            // Store sanitized form data in transient (not raw $_POST)
            $sanitized_data = array(
                'tournament_slug' => sanitize_text_field($_POST['tournament_slug'] ?? ''),
                'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
                'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
                'player_strength' => sanitize_text_field($_POST['player_strength'] ?? ''),
                'country' => sanitize_text_field($_POST['country'] ?? ''),
                'email' => sanitize_email($_POST['email'] ?? ''),
                'egd_number' => sanitize_text_field($_POST['egd_number'] ?? ''),
                'phone_number' => sanitize_text_field($_POST['phone_number'] ?? ''),
            );
            set_transient('gtr_form_data', $sanitized_data, 45);
        }
    }

    /**
     * Validate form data
     */
    private function validate_form_data($data) {
        $errors = array();

        // First name
        if (empty($data['first_name'])) {
            $errors['first_name'] = 'First name is required.';
        } elseif (strlen($data['first_name']) > 30) {
            $errors['first_name'] = 'First name must not exceed 30 characters.';
        }

        // Last name
        if (empty($data['last_name'])) {
            $errors['last_name'] = 'Last name is required.';
        } elseif (strlen($data['last_name']) > 30) {
            $errors['last_name'] = 'Last name must not exceed 30 characters.';
        }

        // Player strength
        if (empty($data['player_strength'])) {
            $errors['player_strength'] = 'Player strength is required.';
        } elseif (!$this->validate_player_strength($data['player_strength'])) {
            $errors['player_strength'] = 'Invalid player strength. Use format like 5k, 15k, 3d, etc. (30k-1k or 1d-9d).';
        }

        // Country
        if (empty($data['country'])) {
            $errors['country'] = 'Country is required.';
        }

        // Email
        if (empty($data['email'])) {
            $errors['email'] = 'Email is required.';
        } elseif (!is_email($data['email'])) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        // Phone number
        if (empty($data['phone_number'])) {
            $errors['phone_number'] = 'Phone number is required.';
        }

        // EGD number length validation (optional field, but enforce max length if provided)
        if (!empty($data['egd_number']) && strlen($data['egd_number']) > 20) {
            $errors['egd_number'] = 'EGD number must be 20 characters or less.';
        }

        // Check for duplicate email in the same tournament
        $tournament_slug = sanitize_text_field($data['tournament_slug'] ?? 'default');
        if (!isset($errors['email']) && GTR_Database::email_exists($data['email'], $tournament_slug)) {
            $errors['email'] = 'This email is already registered for this tournament.';
        }

        return $errors;
    }

    /**
     * Validate player strength format
     * Valid: 30k-1k, 1d-9d
     */
    private function validate_player_strength($strength) {
        if (!preg_match('/^(\d+)([kd])$/i', $strength, $matches)) {
            return false;
        }

        $number = (int)$matches[1];
        $type = strtolower($matches[2]);

        if ($type === 'k') {
            return $number >= 1 && $number <= 30;
        } elseif ($type === 'd') {
            return $number >= 1 && $number <= 9;
        }

        return false;
    }

    /**
     * Get ISO country list
     */
    public static function get_country_list() {
        return array(
            'AF' => 'Afghanistan',
            'AL' => 'Albania',
            'DZ' => 'Algeria',
            'AR' => 'Argentina',
            'AM' => 'Armenia',
            'AU' => 'Australia',
            'AT' => 'Austria',
            'AZ' => 'Azerbaijan',
            'BD' => 'Bangladesh',
            'BY' => 'Belarus',
            'BE' => 'Belgium',
            'BA' => 'Bosnia and Herzegovina',
            'BR' => 'Brazil',
            'BG' => 'Bulgaria',
            'CA' => 'Canada',
            'CL' => 'Chile',
            'CN' => 'China',
            'CO' => 'Colombia',
            'HR' => 'Croatia',
            'CU' => 'Cuba',
            'CY' => 'Cyprus',
            'CZ' => 'Czech Republic',
            'DK' => 'Denmark',
            'EG' => 'Egypt',
            'EE' => 'Estonia',
            'FI' => 'Finland',
            'FR' => 'France',
            'GE' => 'Georgia',
            'DE' => 'Germany',
            'GR' => 'Greece',
            'HK' => 'Hong Kong',
            'HU' => 'Hungary',
            'IS' => 'Iceland',
            'IN' => 'India',
            'ID' => 'Indonesia',
            'IR' => 'Iran',
            'IQ' => 'Iraq',
            'IE' => 'Ireland',
            'IL' => 'Israel',
            'IT' => 'Italy',
            'JP' => 'Japan',
            'KZ' => 'Kazakhstan',
            'KR' => 'Korea, Republic of',
            'KP' => 'Korea, Democratic People\'s Republic of',
            'LV' => 'Latvia',
            'LT' => 'Lithuania',
            'LU' => 'Luxembourg',
            'MY' => 'Malaysia',
            'MX' => 'Mexico',
            'MD' => 'Moldova',
            'MN' => 'Mongolia',
            'NL' => 'Netherlands',
            'NZ' => 'New Zealand',
            'NO' => 'Norway',
            'PK' => 'Pakistan',
            'PH' => 'Philippines',
            'PL' => 'Poland',
            'PT' => 'Portugal',
            'RO' => 'Romania',
            'RU' => 'Russian Federation',
            'RS' => 'Serbia',
            'SG' => 'Singapore',
            'SK' => 'Slovakia',
            'SI' => 'Slovenia',
            'ZA' => 'South Africa',
            'ES' => 'Spain',
            'SE' => 'Sweden',
            'CH' => 'Switzerland',
            'TW' => 'Taiwan',
            'TH' => 'Thailand',
            'TR' => 'Turkey',
            'UA' => 'Ukraine',
            'GB' => 'United Kingdom',
            'US' => 'United States',
            'UZ' => 'Uzbekistan',
            'VN' => 'Vietnam',
        );
    }
}
