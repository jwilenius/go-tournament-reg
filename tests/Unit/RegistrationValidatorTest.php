<?php
/**
 * Tests for GTR_Registration_Validator (shared by public form and admin inline edit).
 */

use PHPUnit\Framework\TestCase;

class RegistrationValidatorTest extends TestCase {

    protected function setUp(): void {
        GtrDatabaseStub::reset();
    }

    private function validInput(array $overrides = array()): array {
        return array_merge(array(
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'player_strength' => '5k',
            'country' => 'US',
            'email' => 'alice@example.com',
            'phone_number' => '+1-555-1234',
            'egd_number' => '',
            'rounds' => array(),
        ), $overrides);
    }

    public function testValidInputReturnsNoErrors() {
        $errors = GTR_Registration_Validator::validate($this->validInput());
        $this->assertSame(array(), $errors);
    }

    public function testRequiredFields() {
        $errors = GTR_Registration_Validator::validate(array());
        $this->assertArrayHasKey('first_name', $errors);
        $this->assertArrayHasKey('last_name', $errors);
        $this->assertArrayHasKey('player_strength', $errors);
        $this->assertArrayHasKey('country', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('phone_number', $errors);
    }

    public function testFirstNameMaxLength() {
        $errors = GTR_Registration_Validator::validate($this->validInput(array(
            'first_name' => str_repeat('a', 31),
        )));
        $this->assertArrayHasKey('first_name', $errors);
    }

    public function testEmailFormat() {
        $errors = GTR_Registration_Validator::validate($this->validInput(array(
            'email' => 'not-an-email',
        )));
        $this->assertArrayHasKey('email', $errors);
        $this->assertStringContainsString('valid email', $errors['email']);
    }

    public function testEgdNumberMaxLength() {
        $errors = GTR_Registration_Validator::validate($this->validInput(array(
            'egd_number' => str_repeat('1', 21),
        )));
        $this->assertArrayHasKey('egd_number', $errors);
    }

    public function testPlayerStrengthInvalid() {
        $errors = GTR_Registration_Validator::validate($this->validInput(array(
            'player_strength' => '31k',
        )));
        $this->assertArrayHasKey('player_strength', $errors);
    }

    public function testPlayerStrengthHelperBoundaries() {
        $this->assertTrue(GTR_Registration_Validator::validate_player_strength('1k'));
        $this->assertTrue(GTR_Registration_Validator::validate_player_strength('30k'));
        $this->assertTrue(GTR_Registration_Validator::validate_player_strength('9d'));
        $this->assertFalse(GTR_Registration_Validator::validate_player_strength('0k'));
        $this->assertFalse(GTR_Registration_Validator::validate_player_strength('10d'));
        $this->assertFalse(GTR_Registration_Validator::validate_player_strength(''));
    }

    public function testDuplicateEmailWithinTournamentRejected() {
        GtrDatabaseStub::$rows = array(
            array('email' => 'alice@example.com', 'tournament_slug' => 'summer-2026', 'id' => 7),
        );
        $errors = GTR_Registration_Validator::validate(
            $this->validInput(),
            array('tournament_slug' => 'summer-2026')
        );
        $this->assertArrayHasKey('email', $errors);
        $this->assertStringContainsString('already registered', $errors['email']);
    }

    public function testDuplicateEmailInOtherTournamentAllowed() {
        GtrDatabaseStub::$rows = array(
            array('email' => 'alice@example.com', 'tournament_slug' => 'winter-2025', 'id' => 9),
        );
        $errors = GTR_Registration_Validator::validate(
            $this->validInput(),
            array('tournament_slug' => 'summer-2026')
        );
        $this->assertArrayNotHasKey('email', $errors);
    }

    public function testExcludeIdSkipsOwnRow() {
        GtrDatabaseStub::$rows = array(
            array('email' => 'alice@example.com', 'tournament_slug' => 'summer-2026', 'id' => 42),
        );
        $errors = GTR_Registration_Validator::validate(
            $this->validInput(),
            array('tournament_slug' => 'summer-2026', 'exclude_id' => 42)
        );
        $this->assertArrayNotHasKey('email', $errors);
    }

    public function testExcludeIdStillDetectsOtherRows() {
        GtrDatabaseStub::$rows = array(
            array('email' => 'alice@example.com', 'tournament_slug' => 'summer-2026', 'id' => 42),
            array('email' => 'alice@example.com', 'tournament_slug' => 'summer-2026', 'id' => 99),
        );
        $errors = GTR_Registration_Validator::validate(
            $this->validInput(),
            array('tournament_slug' => 'summer-2026', 'exclude_id' => 42)
        );
        $this->assertArrayHasKey('email', $errors);
    }

    public function testRoundsRequiredWhenTournamentHasRounds() {
        $errors = GTR_Registration_Validator::validate(
            $this->validInput(array('rounds' => array())),
            array('tournament_rounds' => 4)
        );
        $this->assertArrayHasKey('rounds', $errors);
    }

    public function testRoundsOutOfRangeRejected() {
        $errors = GTR_Registration_Validator::validate(
            $this->validInput(array('rounds' => array(1, 5))),
            array('tournament_rounds' => 4)
        );
        $this->assertArrayHasKey('rounds', $errors);
    }

    public function testRoundsInRangeAccepted() {
        $errors = GTR_Registration_Validator::validate(
            $this->validInput(array('rounds' => array(1, 2, 4))),
            array('tournament_rounds' => 4)
        );
        $this->assertArrayNotHasKey('rounds', $errors);
    }

    public function testRoundsIgnoredWhenTournamentHasNoRounds() {
        $errors = GTR_Registration_Validator::validate(
            $this->validInput(array('rounds' => array())),
            array('tournament_rounds' => 0)
        );
        $this->assertArrayNotHasKey('rounds', $errors);
    }

    public function testParseRoundsCsvAcceptsSpacesAndDeduplicates() {
        $this->assertSame(array(1, 2, 4), GTR_Registration_Validator::parse_rounds_csv('1, 2, 4'));
        $this->assertSame(array(1, 2), GTR_Registration_Validator::parse_rounds_csv('2,1,2'));
    }

    public function testParseRoundsCsvIgnoresNonNumericTokens() {
        $this->assertSame(array(1, 3), GTR_Registration_Validator::parse_rounds_csv('1,abc,,3'));
    }

    public function testParseRoundsCsvEmpty() {
        $this->assertSame(array(), GTR_Registration_Validator::parse_rounds_csv(''));
        $this->assertSame(array(), GTR_Registration_Validator::parse_rounds_csv('   '));
    }
}
