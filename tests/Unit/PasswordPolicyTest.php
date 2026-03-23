<?php

namespace Tests\Unit;

use Fennec\Core\Env;
use Fennec\Core\Security\PasswordPolicy;
use PHPUnit\Framework\TestCase;

class PasswordPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        $ref = new \ReflectionClass(Env::class);
        $ref->getProperty('loaded')->setValue(null, true);
        $ref->getProperty('vars')->setValue(null, ['PASSWORD_MIN_LENGTH' => '12']);
    }

    protected function tearDown(): void
    {
        $ref = new \ReflectionClass(Env::class);
        $ref->getProperty('loaded')->setValue(null, false);
        $ref->getProperty('vars')->setValue(null, []);
    }

    public function testStrongPasswordPasses(): void
    {
        $errors = PasswordPolicy::validate('MyStr0ng!Pass');

        $this->assertEmpty($errors);
    }

    public function testTooShortPasswordFails(): void
    {
        $errors = PasswordPolicy::validate('Sh0rt!');

        $this->assertNotEmpty($errors);
        $this->assertTrue($this->containsError($errors, 'au moins 12'));
    }

    public function testNoUppercaseFails(): void
    {
        $errors = PasswordPolicy::validate('nouppercas3!pw');

        $this->assertTrue($this->containsError($errors, 'majuscule'));
    }

    public function testNoLowercaseFails(): void
    {
        $errors = PasswordPolicy::validate('NOLOWERCASE3!P');

        $this->assertTrue($this->containsError($errors, 'minuscule'));
    }

    public function testNoDigitFails(): void
    {
        $errors = PasswordPolicy::validate('NoDigitHere!!pw');

        $this->assertTrue($this->containsError($errors, 'chiffre'));
    }

    public function testNoSpecialCharFails(): void
    {
        $errors = PasswordPolicy::validate('NoSpecial123pw');

        $this->assertTrue($this->containsError($errors, 'special'));
    }

    public function testCommonPasswordFails(): void
    {
        // 'password123' is in the common list (case insensitive)
        $errors = PasswordPolicy::validate('password123');

        $this->assertTrue($this->containsError($errors, 'courant'));
    }

    public function testAssertValidThrowsOnWeakPassword(): void
    {
        $this->expectException(\RuntimeException::class);

        PasswordPolicy::assertValid('weak');
    }

    public function testAssertValidPassesOnStrongPassword(): void
    {
        PasswordPolicy::assertValid('MyStr0ng!Pass');

        $this->assertTrue(true);
    }

    public function testStrengthScoreMaxIs5(): void
    {
        $score = PasswordPolicy::strength('MyStr0ng!Pass');

        $this->assertSame(5, $score);
    }

    public function testStrengthScoreMinIs0(): void
    {
        $score = PasswordPolicy::strength('');

        $this->assertSame(0, $score);
    }

    public function testStrengthScoreMedium(): void
    {
        $score = PasswordPolicy::strength('abcdefgh');

        $this->assertSame(1, $score);
    }

    public function testCustomMinLengthFromEnv(): void
    {
        $ref = new \ReflectionClass(Env::class);
        $ref->getProperty('vars')->setValue(null, ['PASSWORD_MIN_LENGTH' => '8']);

        $errors = PasswordPolicy::validate('Str0ng!P');

        $this->assertEmpty($errors);
    }

    /**
     * @param string[] $errors
     */
    private function containsError(array $errors, string $keyword): bool
    {
        foreach ($errors as $error) {
            if (str_contains(strtolower($error), strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }
}
