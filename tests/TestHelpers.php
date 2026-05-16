<?php
/**
 * Test helper functions and traits
 */

/**
 * Helper to extract private/protected methods for testing
 */
class TestHelpers {

    /**
     * Call a private or protected method on an object
     */
    public static function callMethod($object, $methodName, array $args = []) {
        $reflection = new ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }

    /**
     * Get a private or protected property value
     */
    public static function getProperty($object, $propertyName) {
        $reflection = new ReflectionClass(get_class($object));
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue($object);
    }

    /**
     * Set a private or protected property value
     */
    public static function setProperty($object, $propertyName, $value) {
        $reflection = new ReflectionClass(get_class($object));
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }
}

/**
 * Standalone implementation of CSV sanitization for testing
 * (Mirrors the method in GTR_Admin)
 */
function sanitize_csv_field($field) {
    $field = (string) $field;
    if (preg_match('/^[\t\r=+\-@]/', $field)) {
        $field = "'" . $field;
    }
    return $field;
}

/**
 * Standalone implementation of player strength validation for testing
 * (Mirrors the method in GTR_Form_Handler)
 */
function validate_player_strength($strength) {
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
 * Standalone implementation of EGD input sanitization for testing
 */
function sanitize_egd_input($input) {
    $sanitized = sanitize_text_field($input);
    $sanitized = preg_replace('/[\r\n\t\x00-\x1F\x7F]/u', ' ', $sanitized);
    $sanitized = preg_replace('/[^\p{L}\s\-\'_]/u', '', $sanitized);
    $sanitized = preg_replace('/\s+/', ' ', $sanitized);
    return trim($sanitized);
}

/**
 * Standalone implementation of GoR to strength conversion for testing
 */
function gor_to_strength($gor) {
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
 * Standalone implementation of country code validation for testing
 */
function sanitize_country_code($code) {
    $code = strtoupper(sanitize_text_field($code));
    $valid_countries = array_keys(get_country_list());
    return in_array($code, $valid_countries, true) ? $code : '';
}

/**
 * Standalone simple-fold transliteration for testing. The production code
 * delegates to WordPress's remove_accents(); here we use a small deterministic
 * map covering the characters our tests exercise.
 */
function transliterate_simple($name) {
    if ($name === '' || $name === null) {
        return $name;
    }
    $map = array(
        'Å' => 'A', 'å' => 'a',
        'Ä' => 'A', 'ä' => 'a',
        'Ö' => 'O', 'ö' => 'o',
        'Ø' => 'O', 'ø' => 'o',
        'É' => 'E', 'é' => 'e',
        'È' => 'E', 'è' => 'e',
        'Ê' => 'E', 'ê' => 'e',
        'Ü' => 'U', 'ü' => 'u',
        'Ú' => 'U', 'ú' => 'u',
        'Č' => 'C', 'č' => 'c',
        'Š' => 'S', 'š' => 's',
        'Ž' => 'Z', 'ž' => 'z',
        'Ñ' => 'N', 'ñ' => 'n',
        'ß' => 'ss',
    );
    return strtr($name, $map);
}

/**
 * Standalone double-letter transliteration for testing (Å→Aa, Ö→Oe, Ü→Ue, …).
 * Acute accents still fold to their plain letter — matches the convention EGD
 * stores for the "Thune" surname (originally "Thuné").
 */
function transliterate_double($name) {
    if ($name === '' || $name === null) {
        return $name;
    }
    $map = array(
        'Å' => 'Aa', 'å' => 'aa',
        'Ä' => 'Ae', 'ä' => 'ae',
        'Ö' => 'Oe', 'ö' => 'oe',
        'Ø' => 'Oe', 'ø' => 'oe',
        'Ü' => 'Ue', 'ü' => 'ue',
        'ß' => 'ss',
    );
    return transliterate_simple(strtr($name, $map));
}

/**
 * Standalone implementation of compound-name first-segment extraction for testing
 */
function first_name_segment($name) {
    if ($name === '' || $name === null) {
        return $name;
    }
    $parts = preg_split('/[\s\-]+/u', $name, 2);
    if (!is_array($parts) || $parts[0] === '') {
        return $name;
    }
    return $parts[0];
}

/**
 * Standalone mirror of the EGD result merger used by lookup_with_fallback().
 * Dedupes by pin, caps at 10, carries has_more / search_url from any source
 * that overflowed.
 */
function merge_egd_results($results) {
    $players = array();
    $seen_pins = array();
    $has_more = false;
    $search_url = '';
    $total = 0;

    foreach ($results as $result) {
        if (!is_array($result)) {
            continue;
        }
        $total += isset($result['total']) ? (int) $result['total'] : 0;
        if (!empty($result['has_more'])) {
            $has_more = true;
            if ($search_url === '' && !empty($result['search_url'])) {
                $search_url = $result['search_url'];
            }
        }
        $source_players = isset($result['players']) ? $result['players'] : array();
        foreach ($source_players as $player) {
            $pin = isset($player['pin']) ? $player['pin'] : '';
            if ($pin !== '' && isset($seen_pins[$pin])) {
                continue;
            }
            if ($pin !== '') {
                $seen_pins[$pin] = true;
            }
            $players[] = $player;
            if (count($players) >= 10) {
                break 2;
            }
        }
    }

    return array(
        'players' => $players,
        'total' => $total,
        'has_more' => $has_more,
        'search_url' => $search_url,
    );
}

/**
 * Get country list for testing
 */
function get_country_list() {
    return array(
        'DE' => 'Germany',
        'FR' => 'France',
        'GB' => 'United Kingdom',
        'US' => 'United States',
        'JP' => 'Japan',
        'CN' => 'China',
        'KR' => 'Korea, Republic of',
    );
}
