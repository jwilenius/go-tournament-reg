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
                'gor' => !empty($_POST['gor']) && is_numeric($_POST['gor']) ? intval($_POST['gor']) : '',
                'phone_number' => sanitize_text_field($_POST['phone_number'] ?? ''),
                'rounds' => isset($_POST['rounds']) ? array_map('intval', (array) $_POST['rounds']) : array(),
            );
            set_transient('gtr_form_data', $sanitized_data, 45);
            return;
        }

        // Insert registration
        $success = GTR_Database::insert_registration($_POST);

        if ($success) {
            // Store tournament rounds configuration if provided
            $tournament_slug = sanitize_text_field($_POST['tournament_slug'] ?? 'default');
            $tournament_rounds = isset($_POST['tournament_rounds']) ? intval($_POST['tournament_rounds']) : 0;
            if ($tournament_rounds > 0) {
                GTR_Database::set_tournament_rounds($tournament_slug, $tournament_rounds);
            }

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
                'gor' => !empty($_POST['gor']) && is_numeric($_POST['gor']) ? intval($_POST['gor']) : '',
                'phone_number' => sanitize_text_field($_POST['phone_number'] ?? ''),
                'rounds' => isset($_POST['rounds']) ? array_map('intval', (array) $_POST['rounds']) : array(),
            );
            set_transient('gtr_form_data', $sanitized_data, 45);
        }
    }

    /**
     * Validate form data (public form submission).
     *
     * Normalizes $_POST into the shape the shared validator expects, then delegates.
     */
    private function validate_form_data($data) {
        $rounds = array();
        if (isset($data['rounds'])) {
            $rounds = array_values(array_filter(array_map('intval', (array) $data['rounds'])));
        }

        $normalized = array(
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
            'player_strength' => $data['player_strength'] ?? '',
            'country' => $data['country'] ?? '',
            'email' => $data['email'] ?? '',
            'phone_number' => $data['phone_number'] ?? '',
            'egd_number' => $data['egd_number'] ?? '',
            'rounds' => $rounds,
        );

        $options = array(
            'tournament_slug' => sanitize_text_field($data['tournament_slug'] ?? 'default'),
            'tournament_rounds' => isset($data['tournament_rounds']) ? intval($data['tournament_rounds']) : 0,
            'exclude_id' => null,
        );

        return GTR_Registration_Validator::validate($normalized, $options);
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
