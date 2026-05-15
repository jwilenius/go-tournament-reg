<?php
/**
 * Validation for registration data.
 *
 * Stateless. Shared by the public form handler and admin inline-edit AJAX so
 * both apply the same rules.
 */

if (!defined('ABSPATH')) {
    exit;
}

class GTR_Registration_Validator {

    /**
     * Validate registration input.
     *
     * @param array $data    Normalized input. `rounds` (when present) must be an array of ints.
     * @param array $options [
     *     'tournament_slug'   => string,       // for duplicate-email scoping (defaults to 'default')
     *     'tournament_rounds' => int,          // 0 = no rounds validation
     *     'exclude_id'        => int|null,     // ignore this row when checking email uniqueness
     * ]
     * @return array Errors map (field => message). Empty array = valid.
     */
    public static function validate(array $data, array $options = array()) {
        $errors = array();

        $first_name = isset($data['first_name']) ? (string) $data['first_name'] : '';
        if ($first_name === '') {
            $errors['first_name'] = 'First name is required.';
        } elseif (strlen($first_name) > 30) {
            $errors['first_name'] = 'First name must not exceed 30 characters.';
        }

        $last_name = isset($data['last_name']) ? (string) $data['last_name'] : '';
        if ($last_name === '') {
            $errors['last_name'] = 'Last name is required.';
        } elseif (strlen($last_name) > 30) {
            $errors['last_name'] = 'Last name must not exceed 30 characters.';
        }

        $strength = isset($data['player_strength']) ? (string) $data['player_strength'] : '';
        if ($strength === '') {
            $errors['player_strength'] = 'Player strength is required.';
        } elseif (!self::validate_player_strength($strength)) {
            $errors['player_strength'] = 'Invalid player strength. Use format like 5k, 15k, 3d, etc. (30k-1k or 1d-9d).';
        }

        $country = isset($data['country']) ? (string) $data['country'] : '';
        if ($country === '') {
            $errors['country'] = 'Country is required.';
        } elseif (!array_key_exists($country, GTR_Form_Handler::get_country_list())) {
            $errors['country'] = 'Country is invalid.';
        }

        $email = isset($data['email']) ? (string) $data['email'] : '';
        if ($email === '') {
            $errors['email'] = 'Email is required.';
        } elseif (!is_email($email)) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        $phone = isset($data['phone_number']) ? (string) $data['phone_number'] : '';
        if ($phone === '') {
            $errors['phone_number'] = 'Phone number is required.';
        } elseif (strlen($phone) > 20) {
            $errors['phone_number'] = 'Phone number must not exceed 20 characters.';
        }

        $egd_number = isset($data['egd_number']) ? (string) $data['egd_number'] : '';
        if ($egd_number !== '' && strlen($egd_number) > 20) {
            $errors['egd_number'] = 'EGD number must be 20 characters or less.';
        }

        if (!isset($errors['email']) && $email !== '') {
            $tournament_slug = isset($options['tournament_slug']) ? (string) $options['tournament_slug'] : 'default';
            $exclude_id = isset($options['exclude_id']) ? $options['exclude_id'] : null;
            if (GTR_Database::email_exists($email, $tournament_slug, $exclude_id)) {
                $errors['email'] = 'This email is already registered for this tournament.';
            }
        }

        $tournament_rounds = isset($options['tournament_rounds']) ? (int) $options['tournament_rounds'] : 0;
        if ($tournament_rounds > 0) {
            $rounds = isset($data['rounds']) && is_array($data['rounds']) ? $data['rounds'] : array();
            if (empty($rounds)) {
                $errors['rounds'] = 'Please select at least one round to participate in.';
            } else {
                foreach ($rounds as $round) {
                    $round = (int) $round;
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
     * Validate player strength format. Valid: 30k-1k, 1d-9d.
     */
    public static function validate_player_strength($strength) {
        if (!preg_match('/^(\d+)([kd])$/i', (string) $strength, $matches)) {
            return false;
        }

        $number = (int) $matches[1];
        $type = strtolower($matches[2]);

        if ($type === 'k') {
            return $number >= 1 && $number <= 30;
        }
        if ($type === 'd') {
            return $number >= 1 && $number <= 9;
        }

        return false;
    }

    /**
     * Parse a comma-separated rounds string like "1,2,4" into a sorted array of ints.
     *
     * Whitespace around values is tolerated. Empty/non-numeric tokens are skipped.
     * Returns an empty array if the input has no valid tokens.
     *
     * @param string $csv
     * @return int[]
     */
    public static function parse_rounds_csv($csv) {
        if (!is_string($csv) || trim($csv) === '') {
            return array();
        }

        $tokens = explode(',', $csv);
        $result = array();
        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token === '' || !preg_match('/^\d+$/', $token)) {
                continue;
            }
            $result[] = (int) $token;
        }

        $result = array_values(array_unique($result));
        sort($result);
        return $result;
    }
}
