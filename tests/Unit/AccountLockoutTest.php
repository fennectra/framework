<?php

namespace Tests\Unit;

use Fennec\Core\Env;
use Fennec\Core\Security\AccountLockout;
use Fennec\Core\Security\SecurityLogger;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class AccountLockoutTest extends TestCase
{
    protected function setUp(): void
    {
        AccountLockout::flush();

        // Env config
        $ref = new \ReflectionClass(Env::class);
        $ref->getProperty('loaded')->setValue(null, true);
        $ref->getProperty('vars')->setValue(null, [
            'LOCKOUT_MAX_ATTEMPTS' => '3',
            'LOCKOUT_DURATION' => '60',
            'SECRET_KEY' => 'test-key',
        ]);

        // Mock SecurityLogger
        $handler = new TestHandler();
        $logger = new Logger('security');
        $logger->pushHandler($handler);
        SecurityLogger::setInstance($logger);

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        AccountLockout::flush();

        $ref = new \ReflectionClass(Env::class);
        $ref->getProperty('loaded')->setValue(null, false);
        $ref->getProperty('vars')->setValue(null, []);

        $ref = new \ReflectionClass(SecurityLogger::class);
        $ref->getProperty('instance')->setValue(null, null);
        $ref->getProperty('previousHash')->setValue(null, '');

        unset($_SERVER['REMOTE_ADDR']);
    }

    public function testNotLockedByDefault(): void
    {
        $this->assertFalse(AccountLockout::isLocked('test@example.com'));
    }

    public function testAttemptsStartAtZero(): void
    {
        $this->assertSame(0, AccountLockout::attempts('test@example.com'));
    }

    public function testRecordFailureIncrementsAttempts(): void
    {
        AccountLockout::recordFailure('test@example.com');

        $this->assertSame(1, AccountLockout::attempts('test@example.com'));
    }

    public function testLocksAfterMaxAttempts(): void
    {
        for ($i = 0; $i < 3; $i++) {
            AccountLockout::recordFailure('test@example.com');
        }

        $this->assertTrue(AccountLockout::isLocked('test@example.com'));
    }

    public function testNotLockedBeforeMaxAttempts(): void
    {
        AccountLockout::recordFailure('test@example.com');
        AccountLockout::recordFailure('test@example.com');

        $this->assertFalse(AccountLockout::isLocked('test@example.com'));
    }

    public function testRemainingLockoutReturnsSeconds(): void
    {
        for ($i = 0; $i < 3; $i++) {
            AccountLockout::recordFailure('test@example.com');
        }

        $remaining = AccountLockout::remainingLockout('test@example.com');

        $this->assertGreaterThan(0, $remaining);
        $this->assertLessThanOrEqual(60, $remaining);
    }

    public function testResetClearsAttempts(): void
    {
        AccountLockout::recordFailure('test@example.com');
        AccountLockout::recordFailure('test@example.com');

        AccountLockout::reset('test@example.com');

        $this->assertSame(0, AccountLockout::attempts('test@example.com'));
        $this->assertFalse(AccountLockout::isLocked('test@example.com'));
    }

    public function testDifferentIdentifiersAreIndependent(): void
    {
        for ($i = 0; $i < 3; $i++) {
            AccountLockout::recordFailure('user1@example.com');
        }

        $this->assertTrue(AccountLockout::isLocked('user1@example.com'));
        $this->assertFalse(AccountLockout::isLocked('user2@example.com'));
    }

    public function testFlushClearsAll(): void
    {
        AccountLockout::recordFailure('test@example.com');

        AccountLockout::flush();

        $this->assertSame(0, AccountLockout::attempts('test@example.com'));
    }
}
