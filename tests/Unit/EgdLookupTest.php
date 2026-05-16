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

    public function testSanitizeEgdInputPreservesUnderscoreInSurnames() {
        // EGD stores some surnames literally with underscores (e.g. "Agren_Thune").
        // The sanitizer must keep them so the API lookup matches.
        $this->assertEquals('Agren_Thune', sanitize_egd_input('Agren_Thune'));
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

    public function testFirstNameSegmentSplitsOnHyphen() {
        $this->assertEquals('Ågren', first_name_segment('Ågren-Thué'));
        $this->assertEquals('Mary', first_name_segment('Mary-Jane'));
    }

    public function testFirstNameSegmentSplitsOnSpace() {
        $this->assertEquals('Ågren', first_name_segment('Ågren Thué'));
        $this->assertEquals('Jean', first_name_segment('Jean Paul'));
    }

    public function testFirstNameSegmentReturnsSimpleNameUnchanged() {
        $this->assertEquals('Smith', first_name_segment('Smith'));
        $this->assertEquals('Müller', first_name_segment('Müller'));
    }

    public function testFirstNameSegmentHandlesEmpty() {
        $this->assertEquals('', first_name_segment(''));
    }

    public function testTransliterateSimpleFoldsAccents() {
        $this->assertEquals('Agren', transliterate_simple('Ågren'));
        $this->assertEquals('Thue', transliterate_simple('Thué'));
        $this->assertEquals('Muller', transliterate_simple('Müller'));
        $this->assertEquals('Oberg', transliterate_simple('Öberg'));
        $this->assertEquals('Capek', transliterate_simple('Čapek'));
    }

    public function testTransliterateSimpleLeavesPlainAsciiUnchanged() {
        $this->assertEquals('Smith', transliterate_simple('Smith'));
        $this->assertEquals('Mary-Jane', transliterate_simple('Mary-Jane'));
    }

    public function testTransliterateDoubleExpandsVowelMarks() {
        // Matches the EGD-stored forms of real Swedish/German players:
        //   "Aagren" (Å→Aa), "Lindstroem" (ö→oe), "Mueller" (ü→ue), "Bjoern" (ö→oe).
        $this->assertEquals('Aagren', transliterate_double('Ågren'));
        $this->assertEquals('Lindstroem', transliterate_double('Lindström'));
        $this->assertEquals('Mueller', transliterate_double('Müller'));
        $this->assertEquals('Bjoern', transliterate_double('Björn'));
        $this->assertEquals('Oeyvind', transliterate_double('Øyvind'));
    }

    public function testTransliterateDoubleFoldsAcutesToPlainLetter() {
        // EGD stores "Thune" for the player whose name is originally "Thuné" —
        // so acutes fold to e, not "ee".
        $this->assertEquals('Thune', transliterate_double('Thuné'));
        $this->assertEquals('Jose', transliterate_double('José'));
    }

    public function testTransliterateHandlesEmpty() {
        $this->assertEquals('', transliterate_simple(''));
        $this->assertEquals('', transliterate_double(''));
    }

    public function testMergeEgdResultsUnionsDistinctPlayers() {
        $simple = array(
            'players' => array(array('pin' => '111', 'last_name' => 'Agren_Thune')),
            'total' => 1, 'has_more' => false, 'search_url' => '',
        );
        $double = array(
            'players' => array(array('pin' => '222', 'last_name' => 'Aagren')),
            'total' => 1, 'has_more' => false, 'search_url' => '',
        );
        $merged = merge_egd_results(array($simple, $double));

        $this->assertCount(2, $merged['players']);
        $this->assertEquals('111', $merged['players'][0]['pin']);
        $this->assertEquals('222', $merged['players'][1]['pin']);
    }

    public function testMergeEgdResultsDedupesByPin() {
        $a = array(
            'players' => array(array('pin' => '111', 'last_name' => 'X')),
            'total' => 1, 'has_more' => false, 'search_url' => '',
        );
        $b = array(
            'players' => array(
                array('pin' => '111', 'last_name' => 'X'),
                array('pin' => '222', 'last_name' => 'Y'),
            ),
            'total' => 2, 'has_more' => false, 'search_url' => '',
        );
        $merged = merge_egd_results(array($a, $b));

        $this->assertCount(2, $merged['players']);
        $pins = array_column($merged['players'], 'pin');
        $this->assertEquals(array('111', '222'), $pins);
    }

    public function testMergeEgdResultsCapsAtTen() {
        $players_a = array();
        for ($i = 0; $i < 8; $i++) {
            $players_a[] = array('pin' => 'A' . $i);
        }
        $players_b = array();
        for ($i = 0; $i < 8; $i++) {
            $players_b[] = array('pin' => 'B' . $i);
        }
        $merged = merge_egd_results(array(
            array('players' => $players_a, 'total' => 8, 'has_more' => false, 'search_url' => ''),
            array('players' => $players_b, 'total' => 8, 'has_more' => false, 'search_url' => ''),
        ));

        $this->assertCount(10, $merged['players']);
    }

    public function testMergeEgdResultsCarriesHasMoreAndSearchUrl() {
        $overflowing = array(
            'players' => array(array('pin' => '111')),
            'total' => 50,
            'has_more' => true,
            'search_url' => 'https://example.com/search',
        );
        $small = array(
            'players' => array(array('pin' => '222')),
            'total' => 1, 'has_more' => false, 'search_url' => '',
        );
        $merged = merge_egd_results(array($small, $overflowing));

        $this->assertTrue($merged['has_more']);
        $this->assertEquals('https://example.com/search', $merged['search_url']);
    }

    public function testMergeEgdResultsHandlesEmptyInputs() {
        $merged = merge_egd_results(array());
        $this->assertEquals(array(), $merged['players']);
        $this->assertFalse($merged['has_more']);
        $this->assertEquals('', $merged['search_url']);
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
