<?php
/**
 * Database operations for Go Tournament Registration
 */

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
            first_name varchar(30) NOT NULL,
            last_name varchar(30) NOT NULL,
            player_strength varchar(4) NOT NULL,
            country varchar(2) NOT NULL,
            email varchar(100) NOT NULL,
            egd_number varchar(20) DEFAULT NULL,
            phone_number varchar(20) NOT NULL,
            registration_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY egd_number (egd_number)
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

        $result = $wpdb->insert(
            $table_name,
            array(
                'first_name' => sanitize_text_field($data['first_name']),
                'last_name' => sanitize_text_field($data['last_name']),
                'player_strength' => sanitize_text_field($data['player_strength']),
                'country' => sanitize_text_field($data['country']),
                'email' => sanitize_email($data['email']),
                'egd_number' => !empty($data['egd_number']) ? sanitize_text_field($data['egd_number']) : null,
                'phone_number' => sanitize_text_field($data['phone_number']),
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        return $result !== false;
    }

    /**
     * Get all registrations
     */
    public static function get_all_registrations() {
        global $wpdb;

        $table_name = $wpdb->prefix . GTR_TABLE_NAME;

        return $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC");
    }

    /**
     * Get registrations for display (sorted by player strength)
     */
    public static function get_sorted_registrations() {
        global $wpdb;

        $table_name = $wpdb->prefix . GTR_TABLE_NAME;
        $results = $wpdb->get_results("SELECT first_name, last_name, player_strength FROM $table_name");

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
     * Check if EGD number already exists
     */
    public static function egd_number_exists($egd_number) {
        if (empty($egd_number)) {
            return false;
        }

        global $wpdb;

        $table_name = $wpdb->prefix . GTR_TABLE_NAME;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE egd_number = %s",
                $egd_number
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
     */
    public static function get_registration_count() {
        global $wpdb;

        $table_name = $wpdb->prefix . GTR_TABLE_NAME;

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    }
}
