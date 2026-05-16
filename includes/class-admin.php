<?php
/**
 * Admin panel for Go Tournament Registration
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class GTR_Admin {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'Go Tournament Registration',
            'Tournament Registration',
            'manage_options',
            'go-tournament-registration',
            array($this, 'render_admin_page'),
            'dashicons-groups',
            30
        );
    }

    /**
     * Handle admin actions (delete, export, bulk delete)
     */
    public function handle_admin_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle delete single registration
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'gtr_delete_registration')) {
                wp_die('Security check failed');
            }

            $id = intval($_GET['id']);
            GTR_Database::delete_registration($id);

            $redirect_url = admin_url('admin.php?page=go-tournament-registration&deleted=1');
            if (isset($_GET['tournament'])) {
                $redirect_url = add_query_arg('tournament', sanitize_text_field($_GET['tournament']), $redirect_url);
            }

            wp_redirect($redirect_url);
            exit;
        }

        // Toggle the archived state for a tournament. Archiving also snapshots
        // the current registration count so the "ended with N participants"
        // banner survives subsequent deletions.
        if (isset($_GET['action']) && $_GET['action'] === 'toggle_archive' && isset($_GET['tournament'])) {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'gtr_toggle_archive')) {
                wp_die('Security check failed');
            }

            $tournament_slug = sanitize_text_field($_GET['tournament']);
            $new_state = !GTR_Database::is_tournament_archived($tournament_slug);
            GTR_Database::set_tournament_archived($tournament_slug, $new_state);
            if ($new_state) {
                $live_count = GTR_Database::get_registration_count($tournament_slug);
                GTR_Database::set_tournament_final_count($tournament_slug, $live_count);
            }

            $redirect_url = add_query_arg(
                array(
                    'tournament' => $tournament_slug,
                    $new_state ? 'archived' : 'unarchived' => 1,
                ),
                admin_url('admin.php?page=go-tournament-registration')
            );
            wp_redirect($redirect_url);
            exit;
        }

        // Toggle the registration lock for a tournament
        if (isset($_GET['action']) && $_GET['action'] === 'toggle_lock' && isset($_GET['tournament'])) {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'gtr_toggle_lock')) {
                wp_die('Security check failed');
            }

            $tournament_slug = sanitize_text_field($_GET['tournament']);
            $new_state = !GTR_Database::is_tournament_locked($tournament_slug);
            GTR_Database::set_tournament_locked($tournament_slug, $new_state);

            $redirect_url = add_query_arg(
                array(
                    'tournament' => $tournament_slug,
                    $new_state ? 'locked' : 'unlocked' => 1,
                ),
                admin_url('admin.php?page=go-tournament-registration')
            );
            wp_redirect($redirect_url);
            exit;
        }

        // Handle bulk delete by tournament
        if (isset($_GET['action']) && $_GET['action'] === 'delete_all' && isset($_GET['tournament'])) {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'gtr_delete_all_tournament')) {
                wp_die('Security check failed');
            }

            $tournament_slug = sanitize_text_field($_GET['tournament']);
            $count = GTR_Database::delete_all_by_tournament($tournament_slug);

            wp_redirect(admin_url('admin.php?page=go-tournament-registration&deleted_all=' . $count));
            exit;
        }

        // Handle CSV export
        if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'gtr_export_csv')) {
                wp_die('Security check failed');
            }

            $tournament_filter = isset($_GET['tournament']) ? sanitize_text_field($_GET['tournament']) : null;
            $this->export_csv($tournament_filter);
            exit;
        }

        // Handle OpenGotha XML export
        if (isset($_GET['action']) && $_GET['action'] === 'export_opengotha') {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'gtr_export_opengotha')) {
                wp_die('Security check failed');
            }

            $tournament_filter = isset($_GET['tournament']) ? sanitize_text_field($_GET['tournament']) : null;
            $tournament_rounds = $tournament_filter ? GTR_Database::get_tournament_rounds($tournament_filter) : 0;
            $this->export_opengotha($tournament_filter, $tournament_rounds);
            exit;
        }

        // Handle MacMahon export
        if (isset($_GET['action']) && $_GET['action'] === 'export_macmahon') {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'gtr_export_macmahon')) {
                wp_die('Security check failed');
            }

            $tournament_filter = isset($_GET['tournament']) ? sanitize_text_field($_GET['tournament']) : null;
            $tournament_rounds = $tournament_filter ? GTR_Database::get_tournament_rounds($tournament_filter) : 0;
            $this->export_macmahon($tournament_filter, $tournament_rounds);
            exit;
        }
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Get all tournaments
        $all_tournaments = GTR_Database::get_all_tournaments();

        // Always require a tournament to be selected
        // Default to first tournament if none specified
        $tournament_filter = isset($_GET['tournament']) ? sanitize_text_field($_GET['tournament']) : null;

        if (empty($tournament_filter) && !empty($all_tournaments)) {
            $tournament_filter = $all_tournaments[0];
        }

        // Get registrations for the selected tournament only
        $registrations = !empty($tournament_filter) ? GTR_Database::get_all_registrations($tournament_filter) : array();
        $countries = GTR_Form_Handler::get_country_list();
        $tournament_rounds = $tournament_filter ? GTR_Database::get_tournament_rounds($tournament_filter) : 0;

        ?>
        <div class="wrap">
            <h1>Go Tournament Registrations</h1>

            <?php if (isset($_GET['deleted'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Registration deleted successfully.</p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['deleted_all'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo intval($_GET['deleted_all']); ?> registration(s) deleted successfully.</p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['locked'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Registration locked. New sign-ups will be rejected until you unlock.</p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['unlocked'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Registration unlocked. The form is open again.</p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['archived'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Tournament archived. The participant list is hidden on the public page; the participant count has been preserved.</p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['unarchived'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Tournament unarchived. The participant list is visible again.</p>
                </div>
            <?php endif; ?>

            <div class="notice notice-info">
                <p>Create a page with the shortcode <code>[go_tournament_registration tournament="your-tournament" rounds="N"]</code> to add a registration form.</p>
                <p>Example: <code>[go_tournament_registration tournament="summer-2024" rounds="5"]</code></p>
            </div>

            <?php if (empty($all_tournaments)): ?>
                <div class="notice notice-warning">
                    <p>No tournaments found yet. Once someone registers, tournament data will appear here.</p>
                </div>
            <?php else: ?>
                <div class="gtr-admin-filters" style="margin: 20px 0; display: flex; align-items: center; gap: 15px;">
                    <label for="tournament-filter">Select Tournament:</label>
                    <select id="tournament-filter" onchange="window.location.href=this.value;" style="min-width: 200px;">
                        <?php foreach ($all_tournaments as $tournament): ?>
                            <option value="<?php echo esc_url(admin_url('admin.php?page=go-tournament-registration&tournament=' . urlencode($tournament))); ?>" <?php selected($tournament_filter, $tournament); ?>>
                                <?php echo esc_html($tournament); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if ($tournament_filter): ?>
                <div class="gtr-admin-actions" style="margin: 20px 0; display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                    <?php
                    $export_csv_url = add_query_arg('tournament', $tournament_filter, admin_url('admin.php?page=go-tournament-registration&action=export_csv'));
                    $export_opengotha_url = add_query_arg('tournament', $tournament_filter, admin_url('admin.php?page=go-tournament-registration&action=export_opengotha'));
                    $export_macmahon_url = add_query_arg('tournament', $tournament_filter, admin_url('admin.php?page=go-tournament-registration&action=export_macmahon'));
                    ?>
                    <a href="<?php echo wp_nonce_url($export_csv_url, 'gtr_export_csv'); ?>" class="button button-primary">
                        Export to CSV
                    </a>
                    <a href="<?php echo wp_nonce_url($export_opengotha_url, 'gtr_export_opengotha'); ?>" class="button button-secondary">
                        Export for OpenGotha
                    </a>
                    <a href="<?php echo wp_nonce_url($export_macmahon_url, 'gtr_export_macmahon'); ?>" class="button button-secondary">
                        Export for MacMahon
                    </a>

                    <?php
                    $is_locked = GTR_Database::is_tournament_locked($tournament_filter);
                    $is_archived = GTR_Database::is_tournament_archived($tournament_filter);
                    $toggle_lock_url = wp_nonce_url(
                        admin_url('admin.php?page=go-tournament-registration&action=toggle_lock&tournament=' . urlencode($tournament_filter)),
                        'gtr_toggle_lock'
                    );
                    $toggle_archive_url = wp_nonce_url(
                        admin_url('admin.php?page=go-tournament-registration&action=toggle_archive&tournament=' . urlencode($tournament_filter)),
                        'gtr_toggle_archive'
                    );
                    ?>
                    <a
                        href="<?php echo esc_url($toggle_lock_url); ?>"
                        class="button button-secondary"
                        style="<?php echo $is_locked
                            ? 'background: #f0ad4e; border-color: #eea236; color: white;'
                            : 'background: #5bc0de; border-color: #46b8da; color: white;'; ?>"
                    >
                        <?php echo $is_locked ? 'Unlock Registration' : 'Lock Registration'; ?>
                    </a>
                    <a
                        href="<?php echo esc_url($toggle_archive_url); ?>"
                        class="button button-secondary"
                        style="<?php echo $is_archived
                            ? 'background: #6c757d; border-color: #5a6268; color: white;'
                            : 'background: #343a40; border-color: #23272b; color: white;'; ?>"
                    >
                        <?php echo $is_archived ? 'Unarchive Tournament' : 'Archive Tournament'; ?>
                    </a>

                    <?php if (!empty($registrations)): ?>
                        <a
                            href="<?php echo wp_nonce_url(admin_url('admin.php?page=go-tournament-registration&action=delete_all&tournament=' . urlencode($tournament_filter)), 'gtr_delete_all_tournament'); ?>"
                            class="button button-secondary"
                            onclick="return confirm('Are you sure you want to delete ALL <?php echo intval(count($registrations)); ?> registration(s) for tournament \'<?php echo esc_js($tournament_filter); ?>\'? This cannot be undone!');"
                            style="background: #dc3545; border-color: #dc3545; color: white;"
                        >
                            Delete All Registrations
                        </a>
                    <?php endif; ?>

                    <span class="gtr-total-count">
                        Tournament: <strong><?php echo esc_html($tournament_filter); ?></strong> -
                        Total: <strong><?php echo count($registrations); ?></strong> registration(s)
                    </span>
                </div>
            <?php endif; ?>

            <?php if (empty($registrations)): ?>
                <p>No registrations<?php echo $tournament_filter ? ' for this tournament' : ''; ?>.</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped gtr-registrations-table" data-tournament-rounds="<?php echo (int) $tournament_rounds; ?>">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tournament</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Player Strength</th>
                            <th>GoR</th>
                            <th>Country</th>
                            <th>Email</th>
                            <th>EGD Number</th>
                            <th>Phone Number</th>
                            <th>Rounds</th>
                            <th>Registration Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registrations as $registration): ?>
                            <?php
                            $country_display = $countries[$registration->country] ?? $registration->country;
                            $gor_value = ($registration->gor === null || $registration->gor === '') ? '' : (string) $registration->gor;
                            $egd_value = $registration->egd_number ?? '';
                            $rounds_value = $registration->rounds ?? '';
                            ?>
                            <tr class="gtr-registration-row" data-id="<?php echo (int) $registration->id; ?>">
                                <td><?php echo esc_html($registration->id); ?></td>
                                <td><strong><?php echo esc_html($registration->tournament_slug); ?></strong></td>
                                <td data-field="first_name" data-value="<?php echo esc_attr($registration->first_name); ?>"><?php echo esc_html($registration->first_name); ?></td>
                                <td data-field="last_name" data-value="<?php echo esc_attr($registration->last_name); ?>"><?php echo esc_html($registration->last_name); ?></td>
                                <td data-field="player_strength" data-value="<?php echo esc_attr($registration->player_strength); ?>"><?php echo esc_html($registration->player_strength); ?></td>
                                <td data-field="gor" data-value="<?php echo esc_attr($gor_value); ?>"><?php echo esc_html($gor_value === '' ? '-' : $gor_value); ?></td>
                                <td data-field="country" data-value="<?php echo esc_attr($registration->country); ?>" data-display="<?php echo esc_attr($country_display); ?>"><?php echo esc_html($country_display); ?></td>
                                <td data-field="email" data-value="<?php echo esc_attr($registration->email); ?>"><?php echo esc_html($registration->email); ?></td>
                                <td data-field="egd_number" data-value="<?php echo esc_attr($egd_value); ?>"><?php echo esc_html($egd_value === '' ? '-' : $egd_value); ?></td>
                                <td data-field="phone_number" data-value="<?php echo esc_attr($registration->phone_number); ?>"><?php echo esc_html($registration->phone_number); ?></td>
                                <td data-field="rounds" data-value="<?php echo esc_attr($rounds_value); ?>"><?php echo esc_html($rounds_value === '' ? '-' : $rounds_value); ?></td>
                                <td><?php echo esc_html($registration->registration_date); ?></td>
                                <td class="gtr-row-actions">
                                    <?php
                                    $delete_url = admin_url('admin.php?page=go-tournament-registration&action=delete&id=' . $registration->id);
                                    if ($tournament_filter) {
                                        $delete_url = add_query_arg('tournament', $tournament_filter, $delete_url);
                                    }
                                    ?>
                                    <button type="button" class="button button-small gtr-edit-row">Edit</button>
                                    <button type="button" class="button button-small button-primary gtr-save-row">Save</button>
                                    <button type="button" class="button button-small gtr-row-egd-lookup-btn">EGD lookup</button>
                                    <a
                                        href="<?php echo wp_nonce_url($delete_url, 'gtr_delete_registration'); ?>"
                                        class="button button-small button-link-delete gtr-delete-row"
                                        onclick="return confirm('Are you sure you want to delete this registration?');"
                                    >
                                        Delete
                                    </a>
                                    <button type="button" class="button button-small gtr-cancel-row">Cancel</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <template id="gtr-country-options">
                    <?php foreach ($countries as $code => $name): ?>
                        <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($name); ?></option>
                    <?php endforeach; ?>
                </template>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Sanitize a field for CSV export to prevent formula injection
     * @param mixed $field The field value to sanitize
     * @return string Sanitized field value
     */
    private function sanitize_csv_field($field) {
        $field = (string) $field;
        // Prefix cells starting with =, +, -, @, tab, or carriage return to prevent formula injection
        if (preg_match('/^[\t\r=+\-@]/', $field)) {
            $field = "'" . $field;
        }
        return $field;
    }

    /**
     * Export registrations to CSV
     * @param string|null $tournament_filter Filter by tournament (null = all)
     */
    private function export_csv($tournament_filter = null) {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $registrations = GTR_Database::get_all_registrations($tournament_filter);
        $countries = GTR_Form_Handler::get_country_list();

        $filename = 'go-tournament-registrations';
        if ($tournament_filter) {
            $filename .= '-' . $tournament_filter;
        }
        $filename .= '-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        // CSV headers
        fputcsv($output, array(
            'ID',
            'Tournament',
            'First Name',
            'Last Name',
            'Player Strength',
            'GoR',
            'Country',
            'Email',
            'EGD Number',
            'Phone Number',
            'Rounds',
            'Registration Date'
        ));

        // CSV data (sanitized to prevent formula injection)
        foreach ($registrations as $registration) {
            fputcsv($output, array(
                $this->sanitize_csv_field($registration->id),
                $this->sanitize_csv_field($registration->tournament_slug),
                $this->sanitize_csv_field($registration->first_name),
                $this->sanitize_csv_field($registration->last_name),
                $this->sanitize_csv_field($registration->player_strength),
                $this->sanitize_csv_field($registration->gor ?? ''),
                $this->sanitize_csv_field($countries[$registration->country] ?? $registration->country),
                $this->sanitize_csv_field($registration->email),
                $this->sanitize_csv_field($registration->egd_number ?? ''),
                $this->sanitize_csv_field($registration->phone_number),
                $this->sanitize_csv_field($registration->rounds ?? ''),
                $this->sanitize_csv_field($registration->registration_date)
            ));
        }

        fclose($output);
    }

    /**
     * Convert player strength to OpenGotha/MacMahon rank format (e.g., "3d" -> "3D", "5k" -> "5K")
     * @param string $strength Player strength
     * @return string Normalised rank format
     */
    public static function strength_to_rank($strength) {
        $strength = strtolower(trim($strength));
        if (preg_match('/^(\d+)\s*([dk])$/i', $strength, $matches)) {
            return $matches[1] . strtoupper($matches[2]);
        }
        return $strength;
    }

    /**
     * Convert rounds string to binary participation format
     * @param string|null $rounds Comma-separated rounds (e.g., "1,2,4")
     * @param int $total_rounds Total number of rounds
     * @return string Binary string (e.g., "1101" for rounds 1,2,4 of 4)
     */
    public static function rounds_to_binary($rounds, $total_rounds) {
        if (empty($rounds) || $total_rounds <= 0) {
            return str_repeat('1', max($total_rounds, 1));
        }

        $selected = array_map('intval', explode(',', $rounds));
        $binary = '';
        for ($i = 1; $i <= $total_rounds; $i++) {
            $binary .= in_array($i, $selected) ? '1' : '0';
        }
        return $binary;
    }

    /**
     * Format a single registration as a MacMahon import line.
     *
     * Per the MacMahon 3.x spec (https://www.cgerlach.de/go/macmahon-documentation.html):
     *   surname|firstname|strength|country|club|rating|registration|playinginrounds
     *   - separator: '|'
     *   - strength: number + d/p means dan/pro, anything else is kyu
     *   - country: internet code, case-insensitive (emitted upper-case)
     *   - registration: 'f' final, 'p' preliminary
     *   - playinginrounds: binary string ('11100' = rounds 1-3)
     *
     * @param object $registration Registration row (last_name, first_name, player_strength,
     *                             country, gor, rounds)
     * @param int    $total_rounds Total rounds in the tournament (0 => default to all-1s)
     * @return string The formatted line, without trailing newline
     */
    public static function format_macmahon_line($registration, $total_rounds = 0) {
        $fields = array(
            $registration->last_name,
            $registration->first_name,
            self::strength_to_rank($registration->player_strength),
            strtoupper($registration->country),
            '',
            $registration->gor ?? '',
            'f',
            self::rounds_to_binary($registration->rounds, $total_rounds),
        );
        return implode('|', $fields);
    }

    /**
     * Export registrations to OpenGotha XML format
     * @param string|null $tournament_filter Filter by tournament
     * @param int $total_rounds Total number of rounds in the tournament
     */
    private function export_opengotha($tournament_filter = null, $total_rounds = 0) {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $registrations = GTR_Database::get_all_registrations($tournament_filter);

        $filename = 'opengotha';
        if ($tournament_filter) {
            $filename .= '-' . $tournament_filter;
        }
        $filename .= '-' . date('Y-m-d') . '.xml';

        header('Content-Type: application/xml; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $tournament = $dom->createElement('Tournament');
        $dom->appendChild($tournament);

        $players = $dom->createElement('Players');
        $tournament->appendChild($players);

        foreach ($registrations as $registration) {
            $player = $dom->createElement('Player');

            $player->setAttribute('name', $registration->last_name);
            $player->setAttribute('firstName', $registration->first_name);
            $player->setAttribute('country', strtoupper($registration->country));
            $player->setAttribute('club', '');
            $player->setAttribute('egfPin', $registration->egd_number ?? '');
            $player->setAttribute('rank', $this->strength_to_rank($registration->player_strength));
            $player->setAttribute('rating', $registration->gor ?? '');
            $player->setAttribute('ratingOrigin', $registration->gor ? 'EGF' : '');
            $player->setAttribute('participating', $this->rounds_to_binary($registration->rounds, $total_rounds));
            $player->setAttribute('registeringStatus', 'FIN');

            $players->appendChild($player);
        }

        echo $dom->saveXML();
    }

    /**
     * Export registrations to MacMahon text format (Gerlach's MacMahon program)
     * Format: surname|firstname|strength|country|club|rating|registration|playinginrounds
     * @param string|null $tournament_filter Filter by tournament
     * @param int $total_rounds Total number of rounds in the tournament
     */
    private function export_macmahon($tournament_filter = null, $total_rounds = 0) {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $registrations = GTR_Database::get_all_registrations($tournament_filter);

        $filename = 'macmahon';
        if ($tournament_filter) {
            $filename .= '-' . $tournament_filter;
        }
        $filename .= '-' . date('Y-m-d') . '.txt';

        header('Content-Type: text/plain; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        // Header comment
        echo "; MacMahon import file\n";
        echo "; Format: surname|firstname|strength|country|club|rating|registration|playinginrounds\n";
        echo "; Generated: " . date('Y-m-d H:i:s') . "\n";
        if ($tournament_filter) {
            echo "; Tournament: " . $tournament_filter . "\n";
        }
        echo "\n";

        foreach ($registrations as $registration) {
            echo self::format_macmahon_line($registration, $total_rounds) . "\n";
        }
    }
}
