<?php
/**
 * Tests for email duplicate checking logic
 *
 * Note: These tests verify the logic flow, not actual database queries.
 * The GTR_Database::email_exists() method requires WordPress DB mocking
 * for full integration testing.
 */

use PHPUnit\Framework\TestCase;

class EmailDuplicateTest extends TestCase {

    /**
     * Simulates the email_exists check logic
     */
    private function emailExists($email, $tournament_slug, $existingEmails) {
        if (empty($email)) {
            return false;
        }

        $key = strtolower($email) . '|' . $tournament_slug;
        return in_array($key, $existingEmails);
    }

    public function testEmptyEmailReturnsNoDuplicate() {
        $existing = ['test@example.com|summer-2024'];
        $this->assertFalse($this->emailExists('', 'summer-2024', $existing));
    }

    public function testExistingEmailInSameTournamentReturnsDuplicate() {
        $existing = ['john@example.com|summer-2024'];
        $this->assertTrue($this->emailExists('john@example.com', 'summer-2024', $existing));
    }

    public function testExistingEmailInDifferentTournamentReturnsNoDuplicate() {
        $existing = ['john@example.com|summer-2024'];
        $this->assertFalse($this->emailExists('john@example.com', 'winter-2024', $existing));
    }

    public function testNewEmailReturnsNoDuplicate() {
        $existing = ['john@example.com|summer-2024'];
        $this->assertFalse($this->emailExists('jane@example.com', 'summer-2024', $existing));
    }

    public function testSameEmailCanRegisterForMultipleTournaments() {
        $existing = ['john@example.com|summer-2024'];

        // Same email for different tournament should be allowed
        $this->assertFalse($this->emailExists('john@example.com', 'winter-2024', $existing));
        $this->assertFalse($this->emailExists('john@example.com', 'spring-2025', $existing));
    }

    public function testMultipleEmailsInSameTournament() {
        $existing = [
            'john@example.com|summer-2024',
            'jane@example.com|summer-2024',
            'bob@example.com|summer-2024',
        ];

        // All existing emails should be detected
        $this->assertTrue($this->emailExists('john@example.com', 'summer-2024', $existing));
        $this->assertTrue($this->emailExists('jane@example.com', 'summer-2024', $existing));
        $this->assertTrue($this->emailExists('bob@example.com', 'summer-2024', $existing));

        // New email should not be detected
        $this->assertFalse($this->emailExists('alice@example.com', 'summer-2024', $existing));
    }

    public function testValidationFlowOnlyChecksEmailIfFormatValid() {
        // Simulates the validation logic in GTR_Form_Handler::validate_form_data

        $data = ['email' => 'invalid-email', 'tournament_slug' => 'default'];
        $errors = [];

        // Step 1: Check if email is valid format
        if (empty($data['email'])) {
            $errors['email'] = 'Email is required.';
        } elseif (!is_email($data['email'])) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        // Step 2: Only check for duplicates if no email errors yet
        $existingEmails = ['invalid-email|default']; // This shouldn't matter
        if (!isset($errors['email'])) {
            if ($this->emailExists($data['email'], $data['tournament_slug'], $existingEmails)) {
                $errors['email'] = 'This email is already registered for this tournament.';
            }
        }

        // Invalid email format error should be shown, not duplicate error
        $this->assertEquals('Please enter a valid email address.', $errors['email']);
    }

    public function testDefaultTournamentSlug() {
        $existing = ['john@example.com|default'];

        // When no tournament_slug provided, defaults to 'default'
        $this->assertTrue($this->emailExists('john@example.com', 'default', $existing));
    }
}
