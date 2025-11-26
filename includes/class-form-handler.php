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
     * Handle form submission
     */
    public function handle_form_submission() {
        if (!isset($_POST['gtr_submit']) || !isset($_POST['gtr_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['gtr_nonce'], 'gtr_registration_form')) {
            wp_die('Security check failed');
        }

        $errors = $this->validate_form_data($_POST);

        if (!empty($errors)) {
            set_transient('gtr_form_errors', $errors, 45);
            set_transient('gtr_form_data', $_POST, 45);
            return;
        }

        // Insert registration
        $success = GTR_Database::insert_registration($_POST);

        if ($success) {
            set_transient('gtr_form_success', 'Registration successful!', 45);
            // Redirect to prevent form resubmission
            wp_redirect(add_query_arg('registered', '1', wp_get_referer()));
            exit;
        } else {
            set_transient('gtr_form_errors', array('database' => 'Failed to save registration. Please try again.'), 45);
            set_transient('gtr_form_data', $_POST, 45);
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

        // EGD number is optional, no validation needed if empty

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
