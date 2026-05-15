<?php
/**
 * Admin-only AJAX handlers for Go Tournament Registration.
 */

if (!defined('ABSPATH')) {
    exit;
}

class GTR_Admin_Ajax {

    public function __construct() {
        add_action('wp_ajax_gtr_update_registration', array($this, 'handle_update_registration'));
    }

    /**
     * Handle an inline-edit save from the admin registrations table.
     *
     * Expects POST: id, nonce, first_name, last_name, player_strength, gor, country,
     * email, egd_number, phone_number, rounds (CSV string like "1,2,4").
     */
    public function handle_update_registration() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions.'), 403);
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gtr_update_registration')) {
            wp_send_json_error(array('message' => 'Security check failed.'), 403);
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id <= 0) {
            wp_send_json_error(array('message' => 'Missing registration id.'), 400);
        }

        $existing = GTR_Database::get_registration($id);
        if (!$existing) {
            wp_send_json_error(array('message' => 'Registration not found.'), 404);
        }

        $tournament_slug = (string) $existing->tournament_slug;
        $tournament_rounds = GTR_Database::get_tournament_rounds($tournament_slug);

        $rounds_csv = isset($_POST['rounds']) ? (string) $_POST['rounds'] : '';
        $rounds = GTR_Registration_Validator::parse_rounds_csv($rounds_csv);

        $input = array(
            'first_name' => isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '',
            'last_name' => isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '',
            'player_strength' => isset($_POST['player_strength']) ? sanitize_text_field($_POST['player_strength']) : '',
            'country' => isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '',
            'email' => isset($_POST['email']) ? sanitize_email($_POST['email']) : '',
            'egd_number' => isset($_POST['egd_number']) ? sanitize_text_field($_POST['egd_number']) : '',
            'gor' => isset($_POST['gor']) ? sanitize_text_field($_POST['gor']) : '',
            'phone_number' => isset($_POST['phone_number']) ? sanitize_text_field($_POST['phone_number']) : '',
            'rounds' => $rounds,
        );

        $errors = GTR_Registration_Validator::validate($input, array(
            'tournament_slug' => $tournament_slug,
            'tournament_rounds' => $tournament_rounds,
            'exclude_id' => $id,
        ));

        if (!empty($errors)) {
            wp_send_json_error(array('errors' => $errors), 422);
        }

        $success = GTR_Database::update_registration($id, $input);
        if (!$success) {
            wp_send_json_error(array('message' => 'Failed to update registration.'), 500);
        }

        $row = GTR_Database::get_registration($id);
        wp_send_json_success(array('registration' => $row));
    }
}
