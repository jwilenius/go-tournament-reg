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

            <div class="notice notice-info">
                <p>Create a page with the shortcode <code>[go_tournament_registration tournament="your-tournament"]</code> to add a registration form.</p>
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
                <div class="gtr-admin-actions" style="margin: 20px 0; display: flex; align-items: center; gap: 15px;">
                    <?php
                    $export_url = add_query_arg('tournament', $tournament_filter, admin_url('admin.php?page=go-tournament-registration&action=export_csv'));
                    ?>
                    <a href="<?php echo wp_nonce_url($export_url, 'gtr_export_csv'); ?>" class="button button-primary">
                        Export to CSV
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
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tournament</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Player Strength</th>
                            <th>Country</th>
                            <th>Email</th>
                            <th>EGD Number</th>
                            <th>Phone Number</th>
                            <th>Registration Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registrations as $registration): ?>
                            <tr>
                                <td><?php echo esc_html($registration->id); ?></td>
                                <td><strong><?php echo esc_html($registration->tournament_slug); ?></strong></td>
                                <td><?php echo esc_html($registration->first_name); ?></td>
                                <td><?php echo esc_html($registration->last_name); ?></td>
                                <td><?php echo esc_html($registration->player_strength); ?></td>
                                <td><?php echo esc_html($countries[$registration->country] ?? $registration->country); ?></td>
                                <td><?php echo esc_html($registration->email); ?></td>
                                <td><?php echo esc_html($registration->egd_number ?? '-'); ?></td>
                                <td><?php echo esc_html($registration->phone_number); ?></td>
                                <td><?php echo esc_html($registration->registration_date); ?></td>
                                <td>
                                    <?php
                                    $delete_url = admin_url('admin.php?page=go-tournament-registration&action=delete&id=' . $registration->id);
                                    if ($tournament_filter) {
                                        $delete_url = add_query_arg('tournament', $tournament_filter, $delete_url);
                                    }
                                    ?>
                                    <a
                                        href="<?php echo wp_nonce_url($delete_url, 'gtr_delete_registration'); ?>"
                                        class="button button-small button-link-delete"
                                        onclick="return confirm('Are you sure you want to delete this registration?');"
                                    >
                                        Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
            'Country',
            'Email',
            'EGD Number',
            'Phone Number',
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
                $this->sanitize_csv_field($countries[$registration->country] ?? $registration->country),
                $this->sanitize_csv_field($registration->email),
                $this->sanitize_csv_field($registration->egd_number ?? ''),
                $this->sanitize_csv_field($registration->phone_number),
                $this->sanitize_csv_field($registration->registration_date)
            ));
        }

        fclose($output);
    }
}
