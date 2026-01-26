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
        add_action('wp_ajax_gtr_egd_lookup', array($this, 'handle_egd_lookup'));
        add_action('wp_ajax_nopriv_gtr_egd_lookup', array($this, 'handle_egd_lookup'));
    }

    /**
     * Check rate limit for form submissions
     * @return bool True if within limit, false if rate limited
     */
    private function check_rate_limit() {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
        $transient_key = 'gtr_rate_' . md5($ip);
        $attempts = get_transient($transient_key);

        if ($attempts !== false && $attempts >= 20) {
            return false; // Rate limited: 20 submissions per 10 minutes
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
                'rounds' => isset($_POST['rounds']) ? array_map('intval', (array) $_POST['rounds']) : array(),
            );
            set_transient('gtr_form_data', $sanitized_data, 45);
            return;
        }

        // Insert registration
        $success = GTR_Database::insert_registration($_POST);

        if ($success) {
            set_transient('gtr_form_success', 'Registration successful!', 45);
            // Redirect to prevent form resubmission (use hidden field, fallback to referer)
            $redirect_url = '';
            if (!empty($_POST['gtr_redirect_url'])) {
                $redirect_url = esc_url_raw($_POST['gtr_redirect_url']);
            }
            if (empty($redirect_url) || !wp_validate_redirect($redirect_url, false)) {
                $referer = wp_get_referer();
                $redirect_url = ($referer && wp_validate_redirect($referer, false)) ? $referer : home_url();
            }
            // Add anchor to scroll to participant list
            $redirect_url = add_query_arg('registered', '1', $redirect_url) . '#gtr-participants';
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
                'rounds' => isset($_POST['rounds']) ? array_map('intval', (array) $_POST['rounds']) : array(),
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

        // Rounds validation (only if tournament has rounds configured)
        $tournament_rounds = isset($data['tournament_rounds']) ? intval($data['tournament_rounds']) : 0;
        if ($tournament_rounds > 0) {
            $selected_rounds = isset($data['rounds']) ? array_filter(array_map('intval', (array) $data['rounds'])) : array();
            if (empty($selected_rounds)) {
                $errors['rounds'] = 'Please select at least one round to participate in.';
            } else {
                // Validate that selected rounds are within valid range
                foreach ($selected_rounds as $round) {
                    if ($round < 1 || $round > $tournament_rounds) {
                        $errors['rounds'] = 'Invalid round selection.';
                        break;
                    }
                }
            }
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
     * Handle EGD lookup AJAX request
     */
    public function handle_egd_lookup() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gtr_egd_lookup')) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
        }

        if (!$this->check_egd_rate_limit()) {
            wp_send_json_error(array('message' => 'Too many requests. Please wait a moment.'), 429);
        }

        $first_name = isset($_POST['first_name']) ? $this->sanitize_egd_input($_POST['first_name']) : '';
        $last_name = isset($_POST['last_name']) ? $this->sanitize_egd_input($_POST['last_name']) : '';
        $country = isset($_POST['country']) ? $this->sanitize_country_code($_POST['country']) : '';

        if (empty($first_name) && empty($last_name) && empty($country)) {
            wp_send_json_error(array('message' => 'Please enter at least a name or select a country.'), 400);
        }

        $result = $this->fetch_egd_players($last_name, $first_name, $country);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 500);
        }

        wp_send_json_success($result);
    }

    /**
     * Check rate limit for EGD lookups (10 per minute per IP)
     */
    private function check_egd_rate_limit() {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
        $transient_key = 'gtr_egd_rate_' . md5($ip);
        $attempts = get_transient($transient_key);

        if ($attempts !== false && $attempts >= 10) {
            return false;
        }

        set_transient($transient_key, ($attempts ?: 0) + 1, 60);
        return true;
    }

    /**
     * Sanitize input for EGD API (allow Unicode letters, spaces, hyphens, apostrophes)
     */
    private function sanitize_egd_input($input) {
        $sanitized = sanitize_text_field($input);
        $sanitized = preg_replace('/[\r\n\t\x00-\x1F\x7F]/u', ' ', $sanitized);
        $sanitized = preg_replace('/[^\p{L}\s\-\']/u', '', $sanitized);
        $sanitized = preg_replace('/\s+/', ' ', $sanitized);
        return trim($sanitized);
    }

    /**
     * Sanitize and validate country code
     */
    private function sanitize_country_code($code) {
        $code = strtoupper(sanitize_text_field($code));
        $valid_countries = array_keys(self::get_country_list());
        return in_array($code, $valid_countries, true) ? $code : '';
    }

    /**
     * Fetch players from EGD API
     */
    private function fetch_egd_players($last_name, $first_name, $country) {
        $base_url = 'https://www.europeangodatabase.eu/EGD/GetPlayerDataByData.php';

        $params = array();
        if (!empty($last_name)) {
            $params['lastname'] = $last_name;
        }
        if (!empty($first_name)) {
            $params['name'] = $first_name;
        }
        if (!empty($country)) {
            $params['country_code'] = $country;
        }

        $url = add_query_arg($params, $base_url);

        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'sslverify' => true,
        ));

        if (is_wp_error($response)) {
            return new WP_Error('egd_request_failed', 'Failed to connect to EGD database.');
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return new WP_Error('egd_invalid_response', 'Invalid response from EGD database.');
        }

        if (isset($data['retcode']) && $data['retcode'] === 'Ok' && empty($data['players'])) {
            return array(
                'players' => array(),
                'total' => 0,
                'search_url' => $this->get_egd_search_url($last_name, $first_name, $country),
            );
        }

        $players = isset($data['players']) ? $data['players'] : array();
        $total = count($players);
        $processed = array();
        $limit = ($total >= 10) ? 9 : $total;

        for ($i = 0; $i < $limit; $i++) {
            $player = $players[$i];
            $processed[] = array(
                'pin' => isset($player['Pin_Player']) ? sanitize_text_field($player['Pin_Player']) : '',
                'first_name' => isset($player['Name']) ? sanitize_text_field($player['Name']) : '',
                'last_name' => isset($player['Last_Name']) ? sanitize_text_field($player['Last_Name']) : '',
                'country' => isset($player['Country_Code']) ? sanitize_text_field($player['Country_Code']) : '',
                'club' => isset($player['Club_Name']) ? sanitize_text_field($player['Club_Name']) : '',
                'gor' => isset($player['Gor']) ? intval($player['Gor']) : 0,
                'strength' => isset($player['Grade']) ? sanitize_text_field($player['Grade']) : $this->gor_to_strength(isset($player['Gor']) ? intval($player['Gor']) : 0),
            );
        }

        return array(
            'players' => $processed,
            'total' => $total,
            'has_more' => $total >= 10,
            'search_url' => ($total >= 10) ? $this->get_egd_search_url($last_name, $first_name, $country) : '',
        );
    }

    /**
     * Convert GoR rating to kyu/dan strength format
     */
    private function gor_to_strength($gor) {
        if ($gor >= 2100) {
            $dan = floor(($gor - 2000) / 100);
            $dan = min(9, $dan);
            return $dan . 'd';
        } else {
            $kyu = floor((2100 - $gor) / 100);
            $kyu = max(1, min(30, $kyu));
            return $kyu . 'k';
        }
    }

    /**
     * Generate EGD website search URL for fallback
     */
    private function get_egd_search_url($last_name, $first_name, $country) {
        $base_url = 'https://www.europeangodatabase.eu/EGD/Find_Player.php';
        $params = array();

        if (!empty($last_name)) {
            $params['lastname'] = $last_name;
        }
        if (!empty($first_name)) {
            $params['name'] = $first_name;
        }
        if (!empty($country)) {
            $params['country_code'] = $country;
        }

        return add_query_arg($params, $base_url);
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
