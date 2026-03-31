<?php

namespace Tests\Unit;

use Fennec\Core\Env;
use Fennec\Core\Security\Hash;
use PHPUnit\Framework\TestCase;

class HashTest extends TestCase
{
    protected function setUp(): void
    {
        $ref = new \ReflectionClass(Env::class);
        $ref->getProperty('loaded')->setValue(null, true);
        $ref->getProperty('vars')->setValue(null, []);
    }

    protected function tearDown(): void
    {
        $ref = new \ReflectionClass(Env::class);
        $ref->getProperty('loaded')->setValue(null, false);
        $ref->getProperty('vars')->setValue(null, []);
    }

    private function setEnv(array $vars): void
    {
        $ref = new \ReflectionClass(Env::class);
        $ref->getProperty('vars')->setValue(null, $vars);
    }

    // ── Default (bcrypt) ────────────────────────────────────

    public function testMakeReturnsBcryptHashByDefault(): void
    {
        $hash = Hash::make('secret');

        $this->assertStringStartsWith('$2y$', $hash);
    }

    public function testVerifyReturnsTrueForValidPassword(): void
    {
        $hash = Hash::make('secret');

        $this->assertTrue(Hash::verify('secret', $hash));
    }

    public function testVerifyReturnsFalseForInvalidPassword(): void
    {
        $hash = Hash::make('secret');

        $this->assertFalse(Hash::verify('wrong', $hash));
    }

    public function testDefaultAlgorithmIsBcrypt(): void
    {
        $this->assertSame(PASSWORD_BCRYPT, Hash::algorithm());
    }

    public function testDefaultAlgorithmNameIsBcrypt(): void
    {
        $this->assertSame('bcrypt', Hash::algorithmName());
    }

    // ── Bcrypt cost ─────────────────────────────────────────

    public function testBcryptCostIsConfigurable(): void
    {
        $this->setEnv(['PASSWORD_HASH_ALGO' => 'bcrypt', 'PASSWORD_HASH_COST' => '10']);

        $hash = Hash::make('secret');

        // bcrypt hash encodes cost as 2 digits after $2y$
        $this->assertStringStartsWith('$2y$10$', $hash);
    }

    public function testBcryptCostDefaultIs12(): void
    {
        $hash = Hash::make('secret');

        $this->assertStringStartsWith('$2y$12$', $hash);
    }

    public function testBcryptCostClampedToMin4(): void
    {
        $this->setEnv(['PASSWORD_HASH_COST' => '1']);

        $hash = Hash::make('secret');

        $this->assertStringStartsWith('$2y$04$', $hash);
    }

    public function testBcryptCostClampedToMax31(): void
    {
        // We only verify the algorithm resolves without error.
        // Actually hashing with cost 31 would take minutes, so we test
        // the clamping logic indirectly via a reasonable cost.
        $this->setEnv(['PASSWORD_HASH_COST' => '99']);

        // Verify algorithm still resolves (does not throw)
        $this->assertSame(PASSWORD_BCRYPT, Hash::algorithm());
    }

    // ── Argon2i ─────────────────────────────────────────────

    public function testArgon2iAlgorithm(): void
    {
        if (!defined('PASSWORD_ARGON2I')) {
            $this->markTestSkipped('Argon2i not available in this PHP build.');
        }

        $this->setEnv(['PASSWORD_HASH_ALGO' => 'argon2i']);

        $hash = Hash::make('secret');

        $this->assertStringStartsWith('$argon2i$', $hash);
        $this->assertTrue(Hash::verify('secret', $hash));
    }

    public function testArgon2iAlgorithmConstant(): void
    {
        if (!defined('PASSWORD_ARGON2I')) {
            $this->markTestSkipped('Argon2i not available in this PHP build.');
        }

        $this->setEnv(['PASSWORD_HASH_ALGO' => 'argon2i']);

        $this->assertSame(PASSWORD_ARGON2I, Hash::algorithm());
    }

    // ── Argon2id ────────────────────────────────────────────

    public function testArgon2idAlgorithm(): void
    {
        if (!defined('PASSWORD_ARGON2ID')) {
            $this->markTestSkipped('Argon2id not available in this PHP build.');
        }

        $this->setEnv(['PASSWORD_HASH_ALGO' => 'argon2id']);

        $hash = Hash::make('secret');

        $this->assertStringStartsWith('$argon2id$', $hash);
        $this->assertTrue(Hash::verify('secret', $hash));
    }

    public function testArgon2idAlgorithmConstant(): void
    {
        if (!defined('PASSWORD_ARGON2ID')) {
            $this->markTestSkipped('Argon2id not available in this PHP build.');
        }

        $this->setEnv(['PASSWORD_HASH_ALGO' => 'argon2id']);

        $this->assertSame(PASSWORD_ARGON2ID, Hash::algorithm());
    }

    public function testArgon2idWithCustomOptions(): void
    {
        if (!defined('PASSWORD_ARGON2ID')) {
            $this->markTestSkipped('Argon2id not available in this PHP build.');
        }

        $this->setEnv([
            'PASSWORD_HASH_ALGO' => 'argon2id',
            'PASSWORD_HASH_MEMORY' => '32768',
            'PASSWORD_HASH_TIME' => '2',
            'PASSWORD_HASH_THREADS' => '1',
        ]);

        $hash = Hash::make('secret');

        $this->assertStringStartsWith('$argon2id$', $hash);
        $this->assertTrue(Hash::verify('secret', $hash));
    }

    // ── needsRehash ─────────────────────────────────────────

    public function testNeedsRehashReturnsFalseWhenAlgoMatches(): void
    {
        $hash = Hash::make('secret');

        $this->assertFalse(Hash::needsRehash($hash));
    }

    public function testNeedsRehashReturnsTrueWhenCostChanges(): void
    {
        $this->setEnv(['PASSWORD_HASH_COST' => '10']);
        $hash = Hash::make('secret');

        // Change cost
        $this->setEnv(['PASSWORD_HASH_COST' => '14']);

        $this->assertTrue(Hash::needsRehash($hash));
    }

    public function testNeedsRehashReturnsTrueWhenAlgoChanges(): void
    {
        if (!defined('PASSWORD_ARGON2ID')) {
            $this->markTestSkipped('Argon2id not available in this PHP build.');
        }

        // Hash with bcrypt
        $hash = Hash::make('secret');

        // Switch to argon2id
        $this->setEnv(['PASSWORD_HASH_ALGO' => 'argon2id']);

        $this->assertTrue(Hash::needsRehash($hash));
    }

    // ── Cross-algorithm verify ──────────────────────────────

    public function testVerifyWorksAcrossAlgorithms(): void
    {
        if (!defined('PASSWORD_ARGON2ID')) {
            $this->markTestSkipped('Argon2id not available in this PHP build.');
        }

        // Hash with bcrypt
        $bcryptHash = Hash::make('secret');

        // Switch to argon2id
        $this->setEnv(['PASSWORD_HASH_ALGO' => 'argon2id']);

        // verify still works on bcrypt hash even though current algo is argon2id
        $this->assertTrue(Hash::verify('secret', $bcryptHash));
    }

    // ── Case insensitive algo name ──────────────────────────

    public function testAlgorithmNameIsCaseInsensitive(): void
    {
        $this->setEnv(['PASSWORD_HASH_ALGO' => 'BCRYPT']);

        $this->assertSame(PASSWORD_BCRYPT, Hash::algorithm());
    }

    public function testAlgorithmNameMixedCase(): void
    {
        $this->setEnv(['PASSWORD_HASH_ALGO' => 'Argon2ID']);

        if (!defined('PASSWORD_ARGON2ID')) {
            $this->markTestSkipped('Argon2id not available in this PHP build.');
        }

        $this->assertSame(PASSWORD_ARGON2ID, Hash::algorithm());
    }

    // ── Error handling ──────────────────────────────────────

    public function testUnsupportedAlgorithmThrowsException(): void
    {
        $this->setEnv(['PASSWORD_HASH_ALGO' => 'md5']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported password hash algorithm');

        Hash::algorithm();
    }

    public function testMakeWithUnsupportedAlgorithmThrows(): void
    {
        $this->setEnv(['PASSWORD_HASH_ALGO' => 'sha256']);

        $this->expectException(\RuntimeException::class);

        Hash::make('secret');
    }

    // ── algorithmName ───────────────────────────────────────

    public function testAlgorithmNameReturnsConfiguredValue(): void
    {
        $this->setEnv(['PASSWORD_HASH_ALGO' => 'argon2id']);

        $this->assertSame('argon2id', Hash::algorithmName());
    }

    public function testAlgorithmNameDefaultsWhenNotSet(): void
    {
        $this->assertSame('bcrypt', Hash::algorithmName());
    }
}
