<?php
/**
 * Live EGD lookup integration tests.
 *
 * These tests hit europeangodatabase.eu directly. Their purpose is to fail
 * loudly when EGD changes its storage conventions in a way that breaks our
 * fold cascade — for example, if EGD migrates its DB to store diacritics
 * directly (which they've stated is in progress), or if they change the
 * spelling of a stored record.
 *
 * Each test:
 *  1. Submits a diacritic-form name that we know matches at least one player.
 *  2. Runs the same multi-stage fold cascade the production handler uses:
 *     as-entered → union(simple-fold, double-fold) → first-segment variants.
 *  3. Asserts the known player's PIN appears in the merged result.
 *
 * The PINs are stable EGD identifiers and survive name spelling changes,
 * so the test passes both today (when EGD stores "Mueller", "Baecklund", etc.)
 * and in a future where EGD stores "Müller" directly. It only fails when our
 * cascade legitimately can no longer locate the player — which is exactly
 * when the search logic needs to be revisited.
 *
 * Run with `composer test-integration`. Skipped automatically if EGD is
 * unreachable so offline CI doesn't fail.
 */

use PHPUnit\Framework\TestCase;

class EgdLookupLiveTest extends TestCase {

    private const EGD_ENDPOINT = 'https://www.europeangodatabase.eu/EGD/GetPlayerDataByData.php';

    public function testAgrenThuneIsFound() {
        $this->assertCascadeFindsPin(
            'Ågren-Thuné', '',
            '20501074',
            'Anders Ågren-Thuné (pin 20501074, stored as "Agren_Thune")'
        );
    }

    public function testBacklundIsFound() {
        // Bäcklund is stored only as "Baecklund" in EGD — there are no
        // "Backlund" records — so this test confirms the double-letter fold
        // is reachable in the cascade.
        $this->assertCascadeFindsPin(
            'Bäcklund', '',
            '10337063',
            'Staffan Bäcklund (pin 10337063, stored as "Baecklund")'
        );
    }

    public function testMullerIsFound() {
        // Müller is stored only as "Mueller" — "Muller" returns nothing.
        // This confirms ü → ue is reachable.
        $this->assertCascadeFindsAny(
            'Müller', '',
            'Mueller',
            'any "Mueller" record'
        );
    }

    public function testStromMatchesStroem() {
        // Ström has stored variants under both folds: "Ahlstrom" (simple) and
        // "Ahlstroem"/"Aastroem" (double). EGD's as-entered query for "Ström"
        // returns spurious prefix matches ("Strmiska") rather than real Ström
        // people, so the cascade *must* always merge in the fold variants —
        // this test pins that behaviour.
        $players = $this->cascadeLookup('Ström', '', '');
        $this->assertNotEmpty($players, 'No players returned for "Ström"');

        $stored_last_names = array_map(function ($p) {
            return strtolower($p['last_name'] ?? '');
        }, $players);

        $hasStroem = false;
        foreach ($stored_last_names as $name) {
            if (strpos($name, 'stroem') !== false) {
                $hasStroem = true;
                break;
            }
        }

        $this->assertTrue(
            $hasStroem,
            'Expected at least one "Stroem" (double-letter fold) record in merged ' .
            'results for "Ström". Either EGD removed all such records, or the ' .
            'cascade no longer always unions fold variants alongside the ' .
            'as-entered query. Returned last names: ' .
            implode(', ', $stored_last_names)
        );
    }

    // ----------------------------------------------------------------------
    // Shared cascade implementation. Mirrors lookup_with_fallback() in
    // includes/class-form-handler.php so this test verifies EGD's behaviour
    // matches what our production code is built against. Keep in sync.
    // ----------------------------------------------------------------------

    private function assertCascadeFindsPin($first, $last, $expected_pin, $description) {
        $players = $this->cascadeLookup($first, $last, '');
        $pins = array_map(function ($p) { return $p['pin'] ?? ''; }, $players);

        $this->assertContains(
            $expected_pin,
            $pins,
            "Cascade failed to find $description. EGD may have changed its " .
            "storage convention for this name — review the fold variants in " .
            "lookup_with_fallback() in includes/class-form-handler.php. " .
            "Pins returned: " . implode(', ', $pins)
        );
    }

    private function assertCascadeFindsAny($first, $last, $stored_substring, $description) {
        $players = $this->cascadeLookup($first, $last, '');
        $matched = false;
        foreach ($players as $p) {
            if (stripos($p['last_name'] ?? '', $stored_substring) !== false) {
                $matched = true;
                break;
            }
        }
        $this->assertTrue(
            $matched,
            "Cascade did not find $description matching substring '$stored_substring'. " .
            "EGD may have changed its storage convention — review fold variants " .
            "in lookup_with_fallback() in includes/class-form-handler.php. " .
            "Returned " . count($players) . " players."
        );
    }

    /**
     * Mirror lookup_with_fallback() from includes/class-form-handler.php.
     * Returns the deduped union of as-entered + simple-fold + double-fold,
     * with a segment-shortened retry if the full-name union is empty.
     */
    private function cascadeLookup($last, $first, $country) {
        $full = $this->unionLookup($last, $first, $country);
        if (!empty($full)) {
            return $full;
        }

        $segment_last = $this->firstSegment($last);
        $segment_first = $this->firstSegment($first);
        if ($segment_last === $last && $segment_first === $first) {
            return array();
        }
        return $this->unionLookup($segment_last, $segment_first, $country);
    }

    private function unionLookup($last, $first, $country) {
        $variants = array(
            array($last, $first),
            array($this->simpleFold($last), $this->simpleFold($first)),
            array($this->doubleFold($last), $this->doubleFold($first)),
        );

        $results = array();
        $seen_queries = array();
        foreach ($variants as $q) {
            $key = $q[0] . "\0" . $q[1];
            if (isset($seen_queries[$key])) continue;
            $seen_queries[$key] = true;
            $results = array_merge($results, $this->fetch($q[0], $q[1], $country));
        }
        return $this->dedupe($results);
    }

    private function dedupe($players) {
        $seen = array();
        $out = array();
        foreach ($players as $p) {
            $pin = $p['pin'] ?? '';
            if ($pin === '' || isset($seen[$pin])) continue;
            $seen[$pin] = true;
            $out[] = $p;
        }
        return $out;
    }

    private function firstSegment($name) {
        if ($name === '' || $name === null) return $name;
        $parts = preg_split('/[\s\-]+/u', $name, 2);
        return ($parts && $parts[0] !== '') ? $parts[0] : $name;
    }

    private function simpleFold($name) {
        if ($name === '' || $name === null) return $name;
        $map = array(
            'Å' => 'A', 'å' => 'a', 'Ä' => 'A', 'ä' => 'a',
            'Ö' => 'O', 'ö' => 'o', 'Ø' => 'O', 'ø' => 'o',
            'Ü' => 'U', 'ü' => 'u',
            'É' => 'E', 'é' => 'e', 'È' => 'E', 'è' => 'e', 'Ê' => 'E', 'ê' => 'e',
            'Ñ' => 'N', 'ñ' => 'n', 'ß' => 'ss',
        );
        return strtr($name, $map);
    }

    private function doubleFold($name) {
        if ($name === '' || $name === null) return $name;
        $map = array(
            'Å' => 'Aa', 'å' => 'aa', 'Ä' => 'Ae', 'ä' => 'ae',
            'Ö' => 'Oe', 'ö' => 'oe', 'Ø' => 'Oe', 'ø' => 'oe',
            'Ü' => 'Ue', 'ü' => 'ue', 'ß' => 'ss',
        );
        return $this->simpleFold(strtr($name, $map));
    }

    /**
     * Hit EGD with a single query and return normalised player records.
     * Uses the cURL extension (php-curl) rather than the http/https stream
     * wrapper because some hardened environments (sandboxes, shared hosts)
     * disable url_fopen but leave cURL available.
     *
     * Skips the test on any network/transport failure so offline CI doesn't
     * report false negatives — only a successful response with unexpected
     * content can cause a real failure.
     */
    private function fetch($last, $first, $country) {
        if (!function_exists('curl_init')) {
            $this->markTestSkipped('php-curl extension required for live EGD tests.');
        }

        $params = array();
        if ($last !== '') $params['lastname'] = $last;
        if ($first !== '') $params['name'] = $first;
        if ($country !== '') $params['country_code'] = $country;
        if (empty($params)) return array();

        $url = self::EGD_ENDPOINT . '?' . http_build_query($params);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, 'gtr-egd-integration-test');
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($body === false || $status >= 500) {
            $this->markTestSkipped("EGD unreachable ($error, status $status): $url");
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            $this->markTestSkipped("EGD returned non-JSON for: $url");
        }

        if (!isset($data['players']) || !is_array($data['players'])) {
            return array();
        }

        $out = array();
        foreach ($data['players'] as $p) {
            $out[] = array(
                'pin' => $p['Pin_Player'] ?? '',
                'last_name' => $p['Last_Name'] ?? '',
                'first_name' => $p['Name'] ?? '',
                'country' => $p['Country_Code'] ?? '',
            );
        }
        return $out;
    }
}
