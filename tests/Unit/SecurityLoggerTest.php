<?php

namespace Tests\Unit;

use Fennec\Core\Security\SecurityLogger;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class SecurityLoggerTest extends TestCase
{
    private TestHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new TestHandler();
        $logger = new Logger('security');
        $logger->pushHandler($this->handler);
        SecurityLogger::setInstance($logger);

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_URI'] = '/test';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['X_REQUEST_ID'] = 'abc123';
        $_REQUEST['__auth_user'] = ['email' => 'test@example.com'];
    }

    protected function tearDown(): void
    {
        $ref = new \ReflectionClass(SecurityLogger::class);
        $prop = $ref->getProperty('instance');
        $prop->setValue(null, null);

        unset(
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['REQUEST_URI'],
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['X_REQUEST_ID'],
            $_REQUEST['__auth_user']
        );
    }

    public function testAlertLogsWarningLevel(): void
    {
        SecurityLogger::alert('auth.failed', ['email' => 'bad@example.com']);

        $this->assertTrue($this->handler->hasWarningRecords());
        $record = $this->handler->getRecords()[0];
        $this->assertSame('auth.failed', $record->message);
    }

    public function testTrackLogsInfoLevel(): void
    {
        SecurityLogger::track('token.revoked', ['user_id' => 42]);

        $this->assertTrue($this->handler->hasInfoRecords());
        $record = $this->handler->getRecords()[0];
        $this->assertSame('token.revoked', $record->message);
    }

    public function testCriticalLogsCriticalLevel(): void
    {
        SecurityLogger::critical('brute_force.detected', ['attempts' => 100]);

        $this->assertTrue($this->handler->hasCriticalRecords());
    }

    public function testContextIsEnrichedWithRequestData(): void
    {
        SecurityLogger::alert('test.event', ['custom' => 'value']);

        $record = $this->handler->getRecords()[0];
        $context = $record->context;

        $this->assertSame('value', $context['custom']);
        $this->assertSame('abc123', $context['request_id']);
        $this->assertSame('127.0.0.1', $context['ip']);
        $this->assertSame('/test', $context['uri']);
        $this->assertSame('POST', $context['method']);
        $this->assertSame('test@example.com', $context['user']);
        $this->assertArrayHasKey('timestamp', $context);
    }

    public function testContextWithoutAuthUser(): void
    {
        unset($_REQUEST['__auth_user']);

        SecurityLogger::track('anonymous.action');

        $record = $this->handler->getRecords()[0];
        $this->assertNull($record->context['user']);
    }

    public function testMultipleEventsAreLogged(): void
    {
        SecurityLogger::alert('event.one');
        SecurityLogger::track('event.two');
        SecurityLogger::critical('event.three');

        $this->assertCount(3, $this->handler->getRecords());
    }
}
