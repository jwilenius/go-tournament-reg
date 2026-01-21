<?php
/**
 * Plugin Name: Go Tournament Registration
 * Plugin URI: https://github.com/jwilenius/go-tournament-reg
 * Description: A WordPress plugin for managing Go tournament player registrations with player strength sorting and participant display.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: Jim Wilenius
 * Author URI: https://github.com/jwilenius
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: go-tournament-reg
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('GTR_VERSION', '1.0.0');
define('GTR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GTR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GTR_TABLE_NAME', 'go_tournament_registrations');

// Include required files
require_once GTR_PLUGIN_DIR . 'includes/class-database.php';
require_once GTR_PLUGIN_DIR . 'includes/class-form-handler.php';
require_once GTR_PLUGIN_DIR . 'includes/class-display.php';
require_once GTR_PLUGIN_DIR . 'includes/class-admin.php';

// Activation hook
register_activation_hook(__FILE__, 'gtr_activate_plugin');

function gtr_activate_plugin() {
    GTR_Database::create_table();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'gtr_deactivate_plugin');

function gtr_deactivate_plugin() {
    // Clean up if needed
}

// Initialize plugin
add_action('plugins_loaded', 'gtr_init');

function gtr_init() {
    // Initialize form handler
    new GTR_Form_Handler();

    // Initialize admin
    if (is_admin()) {
        new GTR_Admin();
    }
}

// Register shortcode
add_shortcode('go_tournament_registration', 'gtr_registration_shortcode');

function gtr_registration_shortcode($atts) {
    $atts = shortcode_atts(array(
        'tournament' => 'default',
        'title' => '',
    ), $atts, 'go_tournament_registration');

    // Sanitize tournament slug (lowercase, alphanumeric with hyphens only)
    $tournament_slug = sanitize_title($atts['tournament']);
    $title = sanitize_text_field($atts['title']);

    ob_start();
    GTR_Display::render_registration_page($tournament_slug, $title);
    return ob_get_clean();
}

// Enqueue styles
add_action('wp_enqueue_scripts', 'gtr_enqueue_styles');

function gtr_enqueue_styles() {
    wp_enqueue_style('gtr-styles', GTR_PLUGIN_URL . 'assets/css/styles.css', array(), GTR_VERSION);
}

// Enqueue admin styles
add_action('admin_enqueue_scripts', 'gtr_enqueue_admin_styles');

function gtr_enqueue_admin_styles($hook) {
    if (strpos($hook, 'go-tournament-registration') !== false) {
        wp_enqueue_style('gtr-admin-styles', GTR_PLUGIN_URL . 'assets/css/admin-styles.css', array(), GTR_VERSION);
    }
}
