<?php
/**
 * Admin panel for Go Tournament Registration
 */

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
     * Handle admin actions (delete, export)
     */
    public function handle_admin_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle delete action
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'gtr_delete_registration')) {
                wp_die('Security check failed');
            }

            $id = intval($_GET['id']);
            GTR_Database::delete_registration($id);

            wp_redirect(admin_url('admin.php?page=go-tournament-registration&deleted=1'));
            exit;
        }

        // Handle CSV export
        if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'gtr_export_csv')) {
                wp_die('Security check failed');
            }

            $this->export_csv();
            exit;
        }
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        $registrations = GTR_Database::get_all_registrations();
        $countries = GTR_Form_Handler::get_country_list();

        ?>
        <div class="wrap">
            <h1>Go Tournament Registrations</h1>

            <?php if (isset($_GET['deleted'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Registration deleted successfully.</p>
                </div>
            <?php endif; ?>

            <div class="gtr-admin-actions">
                <a
                    href="<?php echo wp_nonce_url(admin_url('admin.php?page=go-tournament-registration&action=export_csv'), 'gtr_export_csv'); ?>"
                    class="button button-primary"
                >
                    Export to CSV
                </a>
                <span class="gtr-total-count">
                    Total Registrations: <strong><?php echo count($registrations); ?></strong>
                </span>
            </div>

            <?php if (empty($registrations)): ?>
                <p>No registrations yet.</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
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
                                <td><?php echo esc_html($registration->first_name); ?></td>
                                <td><?php echo esc_html($registration->last_name); ?></td>
                                <td><?php echo esc_html($registration->player_strength); ?></td>
                                <td><?php echo esc_html($countries[$registration->country] ?? $registration->country); ?></td>
                                <td><?php echo esc_html($registration->email); ?></td>
                                <td><?php echo esc_html($registration->egd_number ?? '-'); ?></td>
                                <td><?php echo esc_html($registration->phone_number); ?></td>
                                <td><?php echo esc_html($registration->registration_date); ?></td>
                                <td>
                                    <a
                                        href="<?php echo wp_nonce_url(admin_url('admin.php?page=go-tournament-registration&action=delete&id=' . $registration->id), 'gtr_delete_registration'); ?>"
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
     * Export registrations to CSV
     */
    private function export_csv() {
        $registrations = GTR_Database::get_all_registrations();
        $countries = GTR_Form_Handler::get_country_list();

        $filename = 'go-tournament-registrations-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');

        // CSV headers
        fputcsv($output, array(
            'ID',
            'First Name',
            'Last Name',
            'Player Strength',
            'Country',
            'Email',
            'EGD Number',
            'Phone Number',
            'Registration Date'
        ));

        // CSV data
        foreach ($registrations as $registration) {
            fputcsv($output, array(
                $registration->id,
                $registration->first_name,
                $registration->last_name,
                $registration->player_strength,
                $countries[$registration->country] ?? $registration->country,
                $registration->email,
                $registration->egd_number ?? '',
                $registration->phone_number,
                $registration->registration_date
            ));
        }

        fclose($output);
    }
}
