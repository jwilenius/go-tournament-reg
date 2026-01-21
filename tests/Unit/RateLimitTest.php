<?php
/**
 * Tests for rate limiting functionality
 */

use PHPUnit\Framework\TestCase;

class RateLimitTest extends TestCase {

    protected function setUp(): void {
        clear_mock_transients();
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
    }

    protected function tearDown(): void {
        clear_mock_transients();
        unset($_SERVER['REMOTE_ADDR']);
    }

    /**
     * Simulate the rate limit check logic from GTR_Form_Handler
     */
    private function checkRateLimit(): bool {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
        $transient_key = 'gtr_rate_' . md5($ip);
        $attempts = get_transient($transient_key);

        if ($attempts !== false && $attempts >= 5) {
            return false; // Rate limited
        }

        set_transient($transient_key, ($attempts ?: 0) + 1, 600);
        return true;
    }

    public function testFirstSubmissionIsAllowed() {
        $this->assertTrue($this->checkRateLimit());
    }

    public function testFiveSubmissionsAreAllowed() {
        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($this->checkRateLimit(), "Submission $i should be allowed");
        }
    }

    public function testSixthSubmissionIsBlocked() {
        // Make 5 allowed submissions
        for ($i = 0; $i < 5; $i++) {
            $this->checkRateLimit();
        }

        // 6th should be blocked
        $this->assertFalse($this->checkRateLimit(), "6th submission should be blocked");
    }

    public function testRateLimitIsPerIP() {
        // Fill up rate limit for first IP
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        for ($i = 0; $i < 5; $i++) {
            $this->checkRateLimit();
        }
        $this->assertFalse($this->checkRateLimit(), "First IP should be rate limited");

        // Different IP should still be allowed
        $_SERVER['REMOTE_ADDR'] = '192.168.1.2';
        $this->assertTrue($this->checkRateLimit(), "Second IP should be allowed");
    }

    public function testTransientKeyIsHashedIP() {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $this->checkRateLimit();

        $expected_key = 'gtr_rate_' . md5('10.0.0.1');
        $this->assertEquals(1, get_transient($expected_key));
    }

    public function testCounterIncrementsCorrectly() {
        $_SERVER['REMOTE_ADDR'] = '172.16.0.1';
        $transient_key = 'gtr_rate_' . md5('172.16.0.1');

        $this->checkRateLimit();
        $this->assertEquals(1, get_transient($transient_key));

        $this->checkRateLimit();
        $this->assertEquals(2, get_transient($transient_key));

        $this->checkRateLimit();
        $this->assertEquals(3, get_transient($transient_key));
    }

    public function testHandlesMissingRemoteAddr() {
        unset($_SERVER['REMOTE_ADDR']);

        // Should use 'unknown' as fallback and still work
        $this->assertTrue($this->checkRateLimit());

        $expected_key = 'gtr_rate_' . md5('unknown');
        $this->assertEquals(1, get_transient($expected_key));
    }

    public function testRateLimitAfterTransientExpires() {
        // Simulate filled rate limit
        $transient_key = 'gtr_rate_' . md5($_SERVER['REMOTE_ADDR']);
        set_transient($transient_key, 5, 600);

        $this->assertFalse($this->checkRateLimit(), "Should be blocked");

        // Simulate transient expiration (clear it)
        delete_transient($transient_key);

        // Should be allowed again
        $this->assertTrue($this->checkRateLimit(), "Should be allowed after expiration");
    }
}
