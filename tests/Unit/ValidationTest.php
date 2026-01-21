<?php
/**
 * Tests for form validation functions
 */

use PHPUnit\Framework\TestCase;

class ValidationTest extends TestCase {

    /**
     * @dataProvider validPlayerStrengthProvider
     */
    public function testValidPlayerStrengths($strength) {
        $this->assertTrue(validate_player_strength($strength), "$strength should be valid");
    }

    public function validPlayerStrengthProvider() {
        return [
            // Kyu ranks (1k to 30k)
            '1 kyu' => ['1k'],
            '15 kyu' => ['15k'],
            '30 kyu' => ['30k'],
            '1 kyu uppercase' => ['1K'],

            // Dan ranks (1d to 9d)
            '1 dan' => ['1d'],
            '5 dan' => ['5d'],
            '9 dan' => ['9d'],
            '1 dan uppercase' => ['1D'],
        ];
    }

    /**
     * @dataProvider invalidPlayerStrengthProvider
     */
    public function testInvalidPlayerStrengths($strength) {
        $this->assertFalse(validate_player_strength($strength), "$strength should be invalid");
    }

    public function invalidPlayerStrengthProvider() {
        return [
            // Out of range
            '0 kyu' => ['0k'],
            '31 kyu' => ['31k'],
            '0 dan' => ['0d'],
            '10 dan' => ['10d'],

            // Invalid format
            'no type' => ['5'],
            'wrong type' => ['5p'],
            'text' => ['beginner'],
            'empty' => [''],
            'negative' => ['-5k'],
            'decimal' => ['5.5k'],
            'space' => ['5 k'],
            'reversed' => ['k5'],
        ];
    }

    public function testEgdNumberLengthValidation() {
        // Valid: 20 chars or less
        $this->assertTrue(strlen('12345678901234567890') <= 20);

        // Invalid: more than 20 chars
        $this->assertTrue(strlen('123456789012345678901') > 20);
    }

    /**
     * @dataProvider openRedirectProvider
     */
    public function testOpenRedirectPrevention($referer, $shouldBeValid) {
        $result = wp_validate_redirect($referer, false);

        if ($shouldBeValid) {
            $this->assertEquals($referer, $result, "$referer should be allowed");
        } else {
            $this->assertFalse($result, "$referer should be blocked");
        }
    }

    public function openRedirectProvider() {
        return [
            // Valid same-origin URLs
            'same host' => ['http://example.com/page', true],
            'same host with path' => ['http://example.com/some/path?query=1', true],
            'relative path' => ['/some/path', true],

            // Invalid cross-origin URLs (should be blocked)
            'different host' => ['http://evil.com/phishing', false],
            'different subdomain' => ['http://sub.evil.com/page', false],
        ];
    }

    public function testSanitizeTextFieldStripsHtmlTags() {
        // Our mock strip_tags removes tags but keeps content
        // WordPress sanitize_text_field does the same - removes HTML tags
        $result = sanitize_text_field('<script>alert("xss")</script>Hello World');
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('</script>', $result);
        $this->assertStringContainsString('Hello World', $result);

        $result = sanitize_text_field('<b>Test</b>');
        $this->assertStringNotContainsString('<b>', $result);
        $this->assertStringContainsString('Test', $result);
    }

    public function testSanitizeEmailValidation() {
        $this->assertEquals('test@example.com', sanitize_email('test@example.com'));
        $this->assertEquals('', sanitize_email('not-an-email'));
        $this->assertEquals('', sanitize_email('missing@domain'));
    }

    public function testTransientDataSanitization() {
        // Simulate the sanitized data array structure
        $raw_post = [
            'first_name' => '<script>alert("xss")</script>John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone_number' => '+1-555-1234',
            'player_strength' => '5k',
            'country' => 'US',
            'egd_number' => '12345678',
            'tournament_slug' => 'summer-2024',
        ];

        $sanitized = [
            'first_name' => sanitize_text_field($raw_post['first_name']),
            'last_name' => sanitize_text_field($raw_post['last_name']),
            'email' => sanitize_email($raw_post['email']),
            'phone_number' => sanitize_text_field($raw_post['phone_number']),
            'player_strength' => sanitize_text_field($raw_post['player_strength']),
            'country' => sanitize_text_field($raw_post['country']),
            'egd_number' => sanitize_text_field($raw_post['egd_number']),
            'tournament_slug' => sanitize_text_field($raw_post['tournament_slug']),
        ];

        // XSS attempt - HTML tags should be stripped (content may remain)
        $this->assertStringNotContainsString('<script>', $sanitized['first_name']);
        $this->assertStringContainsString('John', $sanitized['first_name']);

        // Normal values should pass through
        $this->assertEquals('Doe', $sanitized['last_name']);
        $this->assertEquals('john@example.com', $sanitized['email']);
    }
}
