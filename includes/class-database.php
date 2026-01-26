<?php
/**
 * Database operations for Go Tournament Registration
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class GTR_Database {

    /**
     * Create the registrations table
     */
    public static function create_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . GTR_TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            tournament_slug varchar(50) NOT NULL DEFAULT 'default',
            first_name varchar(30) NOT NULL,
            last_name varchar(30) NOT NULL,
            player_strength varchar(4) NOT NULL,
            country varchar(2) NOT NULL,
            email varchar(100) NOT NULL,
            egd_number varchar(20) DEFAULT NULL,
            phone_number varchar(20) NOT NULL,
            rounds varchar(100) DEFAULT NULL,
            registration_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY tournament_slug (tournament_slug)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Insert a new registration
     */
    public static function insert_registration($data) {
        global $wpdb;

        $table_name = $wpdb->prefix . GTR_TABLE_NAME;

        // Sanitize rounds - only allow comma-separated numbers
        $rounds = null;
        if (!empty($data['rounds']) && is_array($data['rounds'])) {
            $valid_rounds = array_filter(array_map('intval', $data['rounds']), function($r) {
                return $r > 0 && $r <= 20;
            });
            if (!empty($valid_rounds)) {
                sort($valid_rounds);
                $rounds = implode(',', $valid_rounds);
            }
        }

        $result = $wpdb->insert(
            $table_name,
            array(
                'tournament_slug' => sanitize_text_field($data['tournament_slug'] ?? 'default'),
                'first_name' => sanitize_text_field($data['first_name']),
                'last_name' => sanitize_text_field($data['last_name']),
                'player_strength' => sanitize_text_field($data['player_strength']),
                'country' => sanitize_text_field($data['country']),
                'email' => sanitize_email($data['email']),
                'egd_number' => !empty($data['egd_number']) ? sanitize_text_field($data['egd_number']) : null,
                'phone_number' => sanitize_text_field($data['phone_number']),
                'rounds' => $rounds,
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        return $result !== false;
    }

    /**
     * Get all registrations
     * @param string|null $tournament_slug Filter by tournament (null = all tournaments)
     */
    public static function get_all_registrations($tournament_slug = null) {
        global $wpdb;

        $table_name = $wpdb->prefix . GTR_TABLE_NAME;

        if ($tournament_slug !== null) {
            return $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM $table_name WHERE tournament_slug = %s ORDER BY id DESC", $tournament_slug)
            );
        }

        return $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC");
    }

    /**
     * Get registrations for display (sorted by player strength)
     * @param string $tournament_slug Filter by tournament
     */
    public static function get_sorted_registrations($tournament_slug = 'default') {
        global $wpdb;

        $table_name = $wpdb->prefix . GTR_TABLE_NAME;
        $results = $wpdb->get_results(
            $wpdb->prepare("SELECT first_name, last_name, player_strength FROM $table_name WHERE tournament_slug = %s", $tournament_slug)
        );

        // Sort using custom logic
        usort($results, array('GTR_Database', 'compare_player_strength'));

        return $results;
    }

    /**
     * Custom comparison function for player strength
     * Kyu: 30k to 1k (high to low number)
     * Dan: 1d to 9d (low to high number)
     * Dan ranks higher than kyu
     */
    public static function compare_player_strength($a, $b) {
        $strength_a = $a->player_strength;
        $strength_b = $b->player_strength;

        // Parse strength values
        preg_match('/(\d+)([kd])/i', $strength_a, $matches_a);
        preg_match('/(\d+)([kd])/i', $strength_b, $matches_b);

        $num_a = (int)$matches_a[1];
        $type_a = strtolower($matches_a[2]);

        $num_b = (int)$matches_b[1];
        $type_b = strtolower($matches_b[2]);

        // Dan is better than kyu
        if ($type_a === 'd' && $type_b === 'k') {
            return -1;
        }
        if ($type_a === 'k' && $type_b === 'd') {
            return 1;
        }

        // Both are dan: higher number is stronger (9d > 1d), so sort descending
        if ($type_a === 'd' && $type_b === 'd') {
            return $num_b - $num_a;
        }

        // Both are kyu: lower number is stronger (1k > 30k), so sort ascending
        if ($type_a === 'k' && $type_b === 'k') {
            return $num_a - $num_b;
        }

        return 0;
    }

    /**
     * Check if EGD number already exists in a tournament
     * @param string $egd_number The EGD number to check
     * @param string $tournament_slug The tournament to check within
     */
    public static function egd_number_exists($egd_number, $tournament_slug = 'default') {
        if (empty($egd_number)) {
            return false;
        }

        global $wpdb;

        $table_name = $wpdb->prefix . GTR_TABLE_NAME;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE egd_number = %s AND tournament_slug = %s",
                $egd_number,
                $tournament_slug
            )
        );

        return $count > 0;
    }

    /**
     * Check if email already exists in a tournament
     * @param string $email The email to check
     * @param string $tournament_slug The tournament to check within
     */
    public static function email_exists($email, $tournament_slug = 'default') {
        if (empty($email)) {
            return false;
        }

        global $wpdb;

        $table_name = $wpdb->prefix . GTR_TABLE_NAME;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE email = %s AND tournament_slug = %s",
                $email,
                $tournament_slug
            )
        );

        return $count > 0;
    }

    /**
     * Delete a registration by ID
     */
    public static function delete_registration($id) {
        global $wpdb;

        $table_name = $wpdb->prefix . GTR_TABLE_NAME;

        return $wpdb->delete(
            $table_name,
            array('id' => $id),
            array('%d')
        );
    }

    /**
     * Get registration count
     * @param string|null $tournament_slug Filter by tournament (null = all tournaments)
     */
    public static function get_registration_count($tournament_slug = null) {
        global $wpdb;

        $table_name = $wpdb->prefix . GTR_TABLE_NAME;

        if ($tournament_slug !== null) {
            return (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE tournament_slug = %s", $tournament_slug)
            );
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    }

    /**
     * Delete all registrations for a tournament
     * @param string $tournament_slug The tournament to delete
     */
    public static function delete_all_by_tournament($tournament_slug) {
        global $wpdb;

        $table_name = $wpdb->prefix . GTR_TABLE_NAME;

        return $wpdb->delete(
            $table_name,
            array('tournament_slug' => $tournament_slug),
            array('%s')
        );
    }

    /**
     * Get all distinct tournament slugs
     */
    public static function get_all_tournaments() {
        global $wpdb;

        $table_name = $wpdb->prefix . GTR_TABLE_NAME;

        return $wpdb->get_col("SELECT DISTINCT tournament_slug FROM $table_name ORDER BY tournament_slug");
    }
}
