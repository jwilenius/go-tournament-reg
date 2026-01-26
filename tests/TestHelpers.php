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
    $sanitized = preg_replace('/[^\p{L}\s\-\']/u', '', $sanitized);
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
