<?php
/**
 * Tests for EGD lookup functionality
 */

use PHPUnit\Framework\TestCase;

class EgdLookupTest extends TestCase {

    protected function setUp(): void {
        clear_mock_transients();
    }

    public function testSanitizeEgdInputPreservesAsciiNames() {
        $this->assertEquals('John', sanitize_egd_input('John'));
        $this->assertEquals('Smith', sanitize_egd_input('Smith'));
        $this->assertEquals("O'Brien", sanitize_egd_input("O'Brien"));
        $this->assertEquals('Mary-Jane', sanitize_egd_input('Mary-Jane'));
    }

    public function testSanitizeEgdInputPreservesUnicodeNames() {
        $this->assertEquals('Müller', sanitize_egd_input('Müller'));
        $this->assertEquals('José', sanitize_egd_input('José'));
        $this->assertEquals('Björk', sanitize_egd_input('Björk'));
        $this->assertEquals('Øyvind', sanitize_egd_input('Øyvind'));
        $this->assertEquals('Čapek', sanitize_egd_input('Čapek'));
    }

    public function testSanitizeEgdInputRemovesMaliciousInput() {
        $this->assertStringNotContainsString('<', sanitize_egd_input('<script>alert("xss")</script>'));
        $this->assertEquals('John', sanitize_egd_input('John123'));
        $this->assertEquals('Smith', sanitize_egd_input('Smith@#$%'));
        $this->assertEquals('John Smith', sanitize_egd_input("John\nSmith"));
    }

    public function testGorToStrengthStandardValues() {
        $this->assertEquals('3d', gor_to_strength(2300));
        $this->assertEquals('1d', gor_to_strength(2100));
        $this->assertEquals('1k', gor_to_strength(2000));
        $this->assertEquals('6k', gor_to_strength(1500));
        $this->assertEquals('11k', gor_to_strength(1000));
    }

    public function testGorToStrengthBoundaryCases() {
        $this->assertEquals('1k', gor_to_strength(2099));
        $this->assertEquals('1k', gor_to_strength(2001));
        $this->assertEquals('1d', gor_to_strength(2100));
        $this->assertEquals('2d', gor_to_strength(2200));
    }

    public function testGorToStrengthCapsAtNineDan() {
        $this->assertEquals('9d', gor_to_strength(3000));
        $this->assertEquals('9d', gor_to_strength(5000));
        $this->assertEquals('9d', gor_to_strength(10000));
    }

    public function testGorToStrengthLowValues() {
        $this->assertEquals('21k', gor_to_strength(0));
        $result = gor_to_strength(-100);
        $this->assertMatchesRegularExpression('/^\d+k$/', $result);
        $this->assertEquals('30k', gor_to_strength(-1000));
    }

    public function testSanitizeCountryCodeAcceptsValidCodes() {
        $this->assertEquals('DE', sanitize_country_code('DE'));
        $this->assertEquals('US', sanitize_country_code('us'));
        $this->assertEquals('JP', sanitize_country_code('JP'));
    }

    public function testSanitizeCountryCodeRejectsInvalidCodes() {
        $this->assertEquals('', sanitize_country_code('XX'));
        $this->assertEquals('', sanitize_country_code('INVALID'));
        $this->assertEquals('', sanitize_country_code(''));
        $this->assertEquals('', sanitize_country_code('<script>'));
    }

    public function testEgdRateLimitingBlocksAfterTenRequests() {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';

        for ($i = 0; $i < 10; $i++) {
            $this->assertTrue(check_egd_rate_limit(), "Request " . ($i + 1) . " should be allowed");
        }

        $this->assertFalse(check_egd_rate_limit(), "11th request should be rate limited");
    }

    public function testEgdRateLimitingIsPerIp() {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        for ($i = 0; $i < 10; $i++) {
            check_egd_rate_limit();
        }
        $this->assertFalse(check_egd_rate_limit());

        $_SERVER['REMOTE_ADDR'] = '192.168.1.2';
        $this->assertTrue(check_egd_rate_limit());
    }
}

function check_egd_rate_limit() {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    $transient_key = 'gtr_egd_rate_' . md5($ip);
    $attempts = get_transient($transient_key);

    if ($attempts !== false && $attempts >= 10) {
        return false;
    }

    set_transient($transient_key, ($attempts ?: 0) + 1, 60);
    return true;
}
