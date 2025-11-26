<?php
/**
 * Display handler for Go Tournament Registration
 */

class GTR_Display {

    /**
     * Render the complete registration page (form + participant list)
     * @param string $tournament_slug Tournament identifier
     * @param string $title Optional title to display
     */
    public static function render_registration_page($tournament_slug = 'default', $title = '') {
        echo '<div class="gtr-container">';

        if (!empty($title)) {
            echo '<h1 class="gtr-tournament-title">' . esc_html($title) . '</h1>';
        }

        self::render_messages();
        self::render_registration_form($tournament_slug);
        self::render_participant_list($tournament_slug);

        echo '</div>';
    }

    /**
     * Render success and error messages
     */
    private static function render_messages() {
        $success = get_transient('gtr_form_success');
        if ($success) {
            echo '<div class="gtr-message gtr-success">' . esc_html($success) . '</div>';
            delete_transient('gtr_form_success');
        }

        $errors = get_transient('gtr_form_errors');
        if ($errors && is_array($errors)) {
            echo '<div class="gtr-message gtr-error">';
            echo '<ul>';
            foreach ($errors as $error) {
                echo '<li>' . esc_html($error) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
    }

    /**
     * Render the registration form
     * @param string $tournament_slug Tournament identifier
     */
    private static function render_registration_form($tournament_slug = 'default') {
        $form_data = get_transient('gtr_form_data');
        $errors = get_transient('gtr_form_errors');

        // Clear transients after retrieving
        delete_transient('gtr_form_data');
        delete_transient('gtr_form_errors');

        $countries = GTR_Form_Handler::get_country_list();

        ?>
        <div class="gtr-registration-form">
            <h2>Tournament Registration</h2>
            <form method="post" action="" class="gtr-form">
                <?php wp_nonce_field('gtr_registration_form', 'gtr_nonce'); ?>
                <input type="hidden" name="tournament_slug" value="<?php echo esc_attr($tournament_slug); ?>" />

                <div class="gtr-form-row">
                    <div class="gtr-form-field">
                        <label for="first_name">First Name <span class="required">*</span></label>
                        <input
                            type="text"
                            id="first_name"
                            name="first_name"
                            maxlength="30"
                            value="<?php echo esc_attr($form_data['first_name'] ?? ''); ?>"
                            required
                            class="<?php echo isset($errors['first_name']) ? 'error' : ''; ?>"
                        >
                    </div>

                    <div class="gtr-form-field">
                        <label for="last_name">Last Name <span class="required">*</span></label>
                        <input
                            type="text"
                            id="last_name"
                            name="last_name"
                            maxlength="30"
                            value="<?php echo esc_attr($form_data['last_name'] ?? ''); ?>"
                            required
                            class="<?php echo isset($errors['last_name']) ? 'error' : ''; ?>"
                        >
                    </div>
                </div>

                <div class="gtr-form-row">
                    <div class="gtr-form-field">
                        <label for="player_strength">Player Strength <span class="required">*</span></label>
                        <input
                            type="text"
                            id="player_strength"
                            name="player_strength"
                            placeholder="e.g., 5k, 3d"
                            value="<?php echo esc_attr($form_data['player_strength'] ?? ''); ?>"
                            required
                            class="<?php echo isset($errors['player_strength']) ? 'error' : ''; ?>"
                        >
                        <small>Format: 30k-1k or 1d-9d</small>
                    </div>

                    <div class="gtr-form-field">
                        <label for="country">Country <span class="required">*</span></label>
                        <select
                            id="country"
                            name="country"
                            required
                            class="<?php echo isset($errors['country']) ? 'error' : ''; ?>"
                        >
                            <option value="">Select a country</option>
                            <?php foreach ($countries as $code => $name): ?>
                                <option
                                    value="<?php echo esc_attr($code); ?>"
                                    <?php selected($form_data['country'] ?? '', $code); ?>
                                >
                                    <?php echo esc_html($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="gtr-form-row">
                    <div class="gtr-form-field">
                        <label for="email">Email <span class="required">*</span></label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?php echo esc_attr($form_data['email'] ?? ''); ?>"
                            required
                            class="<?php echo isset($errors['email']) ? 'error' : ''; ?>"
                        >
                    </div>

                    <div class="gtr-form-field">
                        <label for="phone_number">Phone Number <span class="required">*</span></label>
                        <input
                            type="tel"
                            id="phone_number"
                            name="phone_number"
                            value="<?php echo esc_attr($form_data['phone_number'] ?? ''); ?>"
                            required
                            class="<?php echo isset($errors['phone_number']) ? 'error' : ''; ?>"
                        >
                    </div>
                </div>

                <div class="gtr-form-row">
                    <div class="gtr-form-field">
                        <label for="egd_number">EGD Number</label>
                        <input
                            type="text"
                            id="egd_number"
                            name="egd_number"
                            value="<?php echo esc_attr($form_data['egd_number'] ?? ''); ?>"
                            class="<?php echo isset($errors['egd_number']) ? 'error' : ''; ?>"
                        >
                        <small>Optional</small>
                    </div>
                </div>

                <div class="gtr-form-submit">
                    <button type="submit" name="gtr_submit" class="gtr-button">Register</button>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Render the participant list
     * @param string $tournament_slug Tournament identifier
     */
    private static function render_participant_list($tournament_slug = 'default') {
        $participants = GTR_Database::get_sorted_registrations($tournament_slug);
        $count = GTR_Database::get_registration_count($tournament_slug);

        ?>
        <div class="gtr-participant-list">
            <h2>Registered Participants</h2>
            <p class="gtr-count">Total registered: <strong><?php echo esc_html($count); ?></strong></p>

            <?php if (empty($participants)): ?>
                <p class="gtr-no-participants">No participants registered yet.</p>
            <?php else: ?>
                <div class="gtr-participants">
                    <table class="gtr-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Player Strength</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($participants as $participant): ?>
                                <tr>
                                    <td><?php echo esc_html($participant->first_name . ' ' . $participant->last_name); ?></td>
                                    <td class="gtr-strength"><?php echo esc_html($participant->player_strength); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
