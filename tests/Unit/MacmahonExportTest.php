<?php
/**
 * Tests for the MacMahon import-file export.
 *
 * Verifies that GTR_Admin's MacMahon formatting matches the spec at
 * https://www.cgerlach.de/go/macmahon-documentation.html
 *   surname|firstname|strength|country|club|rating|registration|playinginrounds
 *
 * The spec defines:
 *   - separator '|'
 *   - strength: number+d (Dan) or number+p (Pro); anything else is Kyu
 *   - country: internet codes, case-insensitive
 *   - registration: 'p'/'P' preliminary, 'f'/'F' final
 *   - playinginrounds: binary string ('11100' = rounds 1-3 only)
 *   - encoding: UTF-8
 *   - lines starting with ';' are comments; blank lines allowed
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

// Minimal WordPress shims needed only to load class-admin.php (its constructor
// is never invoked in these tests; we only call public static helpers).
if (!function_exists('add_action')) {
    function add_action(...$args) {}
}
if (!function_exists('current_user_can')) {
    function current_user_can($cap) { return true; }
}
if (!function_exists('wp_die')) {
    function wp_die($msg = '') { throw new RuntimeException((string) $msg); }
}
if (!function_exists('__')) {
    function __($s, $domain = null) { return $s; }
}
if (!function_exists('esc_html')) {
    function esc_html($s) { return $s; }
}
if (!function_exists('esc_attr')) {
    function esc_attr($s) { return $s; }
}
if (!function_exists('esc_html__')) {
    function esc_html__($s, $d = null) { return $s; }
}
if (!function_exists('admin_url')) {
    function admin_url($path = '') { return 'http://example.com/wp-admin/' . $path; }
}
if (!function_exists('add_query_arg')) {
    function add_query_arg($key, $value, $url) { return $url . '&' . $key . '=' . $value; }
}
if (!function_exists('wp_nonce_url')) {
    function wp_nonce_url($url, $action) { return $url . '&_wpnonce=test'; }
}
if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action) { return 'test-nonce'; }
}
if (!function_exists('selected')) {
    function selected($a, $b, $echo = true) { return ''; }
}
if (!function_exists('absint')) {
    function absint($n) { return abs((int) $n); }
}

require_once __DIR__ . '/../../includes/class-admin.php';

class MacmahonExportTest extends TestCase {

    private function row(array $overrides = []) {
        $defaults = [
            'last_name' => 'Wilenius',
            'first_name' => 'Jim',
            'player_strength' => '3k',
            'country' => 'SE',
            'gor' => '1765',
            'rounds' => '1,2,3,4,5',
        ];
        return (object) array_merge($defaults, $overrides);
    }

    /**
     * @dataProvider strengthProvider
     */
    public function testStrengthToRankMatchesSpec($input, $expected) {
        $this->assertSame($expected, GTR_Admin::strength_to_rank($input));
    }

    public function strengthProvider() {
        return [
            'kyu lower'     => ['20k', '20K'],
            'kyu upper'     => ['7K',  '7K'],
            'dan lower'     => ['3d',  '3D'],
            'dan upper'     => ['1D',  '1D'],
            'kyu with space' => ['10 k', '10K'],
            'dan with space' => ['5 d',  '5D'],
            'leading/trailing whitespace' => [' 2k ', '2K'],
            'high kyu (DDK)' => ['25k', '25K'],
            'unknown form falls through' => ['abc', 'abc'],
            'pro currently unsupported, returned as-is lowercased' => ['3p', '3p'],
        ];
    }

    /**
     * @dataProvider roundsProvider
     */
    public function testRoundsToBinaryMatchesSpec($rounds, $total, $expected) {
        $this->assertSame($expected, GTR_Admin::rounds_to_binary($rounds, $total));
    }

    public function roundsProvider() {
        return [
            'all rounds of 5'        => ['1,2,3,4,5', 5, '11111'],
            'first three of five'    => ['1,2,3',     5, '11100'],   // spec example
            'sparse'                 => ['1,3',       4, '1010'],
            'last only'              => ['5',         5, '00001'],
            'empty rounds defaults to all-in'  => ['',  5, '11111'],
            'null rounds defaults to all-in'   => [null, 3, '111'],
            'zero total_rounds returns single 1' => ['1,2', 0, '1'],
            'unsorted input still places bits correctly' => ['3,1', 3, '101'],
            'duplicates are harmless' => ['1,1,2', 3, '110'],
        ];
    }

    public function testFormatLineMatchesSpecFieldOrderAndSeparator() {
        $line = GTR_Admin::format_macmahon_line($this->row(), 5);
        // surname|firstname|strength|country|club|rating|registration|playinginrounds
        $this->assertSame('Wilenius|Jim|3K|SE||1765|f|11111', $line);
    }

    public function testFormatLineHasExactlyEightPipeSeparatedFields() {
        $line = GTR_Admin::format_macmahon_line($this->row(), 5);
        $fields = explode('|', $line);
        $this->assertCount(8, $fields, 'MacMahon spec requires 8 fields');
    }

    public function testClubFieldIsEmptyByDesign() {
        // Club is not collected by the registration form; the 5th field must be empty.
        $line = GTR_Admin::format_macmahon_line($this->row(), 5);
        $fields = explode('|', $line);
        $this->assertSame('', $fields[4]);
    }

    public function testRegistrationStatusIsFinal() {
        $line = GTR_Admin::format_macmahon_line($this->row(), 5);
        $fields = explode('|', $line);
        $this->assertSame('f', $fields[6], "Spec: 'f' = final registration");
    }

    public function testCountryCodeIsUppercasedIsoTwoLetter() {
        $line = GTR_Admin::format_macmahon_line($this->row(['country' => 'se']), 5);
        $fields = explode('|', $line);
        $this->assertSame('SE', $fields[3]);
    }

    public function testMissingGorYieldsEmptyRatingField() {
        $line = GTR_Admin::format_macmahon_line($this->row(['gor' => null]), 5);
        $fields = explode('|', $line);
        $this->assertSame('', $fields[5]);
    }

    public function testEmptyGorYieldsEmptyRatingField() {
        $line = GTR_Admin::format_macmahon_line($this->row(['gor' => '']), 5);
        $fields = explode('|', $line);
        $this->assertSame('', $fields[5]);
    }

    public function testKyuStrengthFromCsvRow() {
        $line = GTR_Admin::format_macmahon_line($this->row([
            'last_name' => 'Cozzi',
            'first_name' => 'Giacomo',
            'player_strength' => '12k',
            'country' => 'IT',
            'gor' => null,
        ]), 5);
        $this->assertSame('Cozzi|Giacomo|12K|IT|||f|11111', $line);
    }

    public function testDanStrengthFromCsvRow() {
        $line = GTR_Admin::format_macmahon_line($this->row([
            'last_name' => 'Aakerblom',
            'first_name' => 'Charlie',
            'player_strength' => '5d',
            'country' => 'SE',
            'gor' => '2492',
        ]), 5);
        $this->assertSame('Aakerblom|Charlie|5D|SE||2492|f|11111', $line);
    }

    public function testUtf8CharactersArePreservedVerbatim() {
        // Spec: "text files that must be encoded as UTF-8". The formatter must
        // not transcode or strip non-ASCII characters in names.
        $line = GTR_Admin::format_macmahon_line($this->row([
            'last_name' => 'Bäcklund',
            'first_name' => 'Staffan',
            'player_strength' => '1d',
            'country' => 'SE',
            'gor' => '2114',
        ]), 5);
        $this->assertSame('Bäcklund|Staffan|1D|SE||2114|f|11111', $line);
        $this->assertTrue(mb_check_encoding($line, 'UTF-8'));
    }

    public function testPartialRoundsParticipation() {
        // Spec example: '11100' = playing rounds 1-3 only.
        $line = GTR_Admin::format_macmahon_line($this->row(['rounds' => '1,2,3']), 5);
        $fields = explode('|', $line);
        $this->assertSame('11100', $fields[7]);
    }

    public function testFullSampleMatchesUserProvidedRegTxt() {
        // The user's reg.csv → reg.txt round-trip: every CSV row must produce
        // the corresponding reg.txt line. This locks the whole pipeline.
        $rows = [
            ['Stoehr',     'Joël',    '20k', 'SE', null,   '1,2,3,4,5', 'Stoehr|Joël|20K|SE|||f|11111'],
            ['Stoehr',     'Jean',    '20k', 'SE', null,   '1,2,3,4,5', 'Stoehr|Jean|20K|SE|||f|11111'],
            ['Cozzi',      'Giacomo', '12k', 'IT', null,   '1,2,3,4,5', 'Cozzi|Giacomo|12K|IT|||f|11111'],
            ['Dingertz',   'Filip',   '10k', 'SE', null,   '1,2,3,4,5', 'Dingertz|Filip|10K|SE|||f|11111'],
            ['Feng',       'Xuanru',  '20k', 'CN', '170',  '1,2,3,4,5', 'Feng|Xuanru|20K|CN||170|f|11111'],
            ['Silfver',    'Anton',   '3d',  'SE', '2333', '1,2,3,4,5', 'Silfver|Anton|3D|SE||2333|f|11111'],
            ['Gaban',      'Renaud',  '2d',  'BE', '2206', '1,2,3,4,5', 'Gaban|Renaud|2D|BE||2206|f|11111'],
            ['Ågren Thuné', 'Anders', '3k',  'SE', '1856', '1,2,3,4,5', 'Ågren Thuné|Anders|3K|SE||1856|f|11111'],
            ['Bäcklund',   'Staffan', '1d',  'SE', '2114', '1,2,3,4,5', 'Bäcklund|Staffan|1D|SE||2114|f|11111'],
            ['Kylmänen',   'Kai',     '2k',  'SE', '1859', '1,2,3,4,5', 'Kylmänen|Kai|2K|SE||1859|f|11111'],
        ];
        foreach ($rows as $r) {
            $reg = (object) [
                'last_name' => $r[0],
                'first_name' => $r[1],
                'player_strength' => $r[2],
                'country' => $r[3],
                'gor' => $r[4],
                'rounds' => $r[5],
            ];
            $this->assertSame($r[6], GTR_Admin::format_macmahon_line($reg, 5));
        }
    }
}
