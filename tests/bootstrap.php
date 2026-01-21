<?php
/**
 * PHPUnit Bootstrap for Go Tournament Registration tests
 *
 * Provides mock WordPress functions for unit testing without full WordPress environment
 */

// Mock ABSPATH constant
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

// Mock GTR_TABLE_NAME constant
if (!defined('GTR_TABLE_NAME')) {
    define('GTR_TABLE_NAME', 'go_tournament_registrations');
}

/**
 * Mock WordPress sanitize_text_field function
 */
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags($str));
    }
}

/**
 * Mock WordPress sanitize_email function
 */
if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        $email = trim($email);
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }
}

/**
 * Mock WordPress is_email function
 */
if (!function_exists('is_email')) {
    function is_email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

/**
 * Transient storage for testing
 */
global $mock_transients;
$mock_transients = [];

/**
 * Mock WordPress get_transient function
 */
if (!function_exists('get_transient')) {
    function get_transient($key) {
        global $mock_transients;
        return $mock_transients[$key] ?? false;
    }
}

/**
 * Mock WordPress set_transient function
 */
if (!function_exists('set_transient')) {
    function set_transient($key, $value, $expiration = 0) {
        global $mock_transients;
        $mock_transients[$key] = $value;
        return true;
    }
}

/**
 * Mock WordPress delete_transient function
 */
if (!function_exists('delete_transient')) {
    function delete_transient($key) {
        global $mock_transients;
        unset($mock_transients[$key]);
        return true;
    }
}

/**
 * Clear all mock transients (for test isolation)
 */
function clear_mock_transients() {
    global $mock_transients;
    $mock_transients = [];
}

/**
 * Mock WordPress home_url function
 */
if (!function_exists('home_url')) {
    function home_url($path = '') {
        return 'http://example.com' . $path;
    }
}

/**
 * Mock WordPress wp_validate_redirect function
 */
if (!function_exists('wp_validate_redirect')) {
    function wp_validate_redirect($location, $default = '') {
        // Simple validation: only allow same-origin URLs
        $home = parse_url(home_url());
        $redirect = parse_url($location);

        if (isset($redirect['host']) && $redirect['host'] !== $home['host']) {
            return $default;
        }

        return $location;
    }
}

/**
 * Mock WordPress wp_get_referer function
 */
if (!function_exists('wp_get_referer')) {
    function wp_get_referer() {
        return $_SERVER['HTTP_REFERER'] ?? false;
    }
}

// Autoload test helpers
require_once __DIR__ . '/TestHelpers.php';
