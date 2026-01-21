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
