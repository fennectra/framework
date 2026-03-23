<?php

namespace Tests\Unit;

use Fennec\Core\Logging\LogMaskingProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

class LogMaskingProcessorTest extends TestCase
{
    private LogMaskingProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new LogMaskingProcessor();
    }

    private function makeRecord(array $context = [], array $extra = []): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'test',
            context: $context,
            extra: $extra,
        );
    }

    public function testMasksPasswordKey(): void
    {
        $record = $this->makeRecord(['password' => 'secret123']);
        $result = ($this->processor)($record);

        $this->assertSame('***', $result->context['password']);
    }

    public function testMasksTokenKey(): void
    {
        $record = $this->makeRecord(['token' => 'eyJhbGciOi...']);
        $result = ($this->processor)($record);

        $this->assertSame('***', $result->context['token']);
    }

    public function testMasksAuthorizationKey(): void
    {
        $record = $this->makeRecord(['authorization' => 'Bearer xyz']);
        $result = ($this->processor)($record);

        $this->assertSame('***', $result->context['authorization']);
    }

    public function testDoesNotMaskNonSensitiveKeys(): void
    {
        $record = $this->makeRecord(['email' => 'john@example.com', 'name' => 'John']);
        $result = ($this->processor)($record);

        $this->assertSame('john@example.com', $result->context['email']);
        $this->assertSame('John', $result->context['name']);
    }

    public function testMasksNestedSensitiveKeys(): void
    {
        $record = $this->makeRecord([
            'user' => [
                'email' => 'john@example.com',
                'password' => 'hash',
                'api_key' => 'key123',
            ],
        ]);
        $result = ($this->processor)($record);

        $this->assertSame('john@example.com', $result->context['user']['email']);
        $this->assertSame('***', $result->context['user']['password']);
        $this->assertSame('***', $result->context['user']['api_key']);
    }

    public function testMasksExtraArray(): void
    {
        $record = $this->makeRecord([], ['secret' => 'value']);
        $result = ($this->processor)($record);

        $this->assertSame('***', $result->extra['secret']);
    }

    public function testMasksCaseInsensitive(): void
    {
        $record = $this->makeRecord(['Password' => 'abc', 'TOKEN' => 'xyz']);
        $result = ($this->processor)($record);

        $this->assertSame('***', $result->context['Password']);
        $this->assertSame('***', $result->context['TOKEN']);
    }

    public function testMasksPartialKeyMatch(): void
    {
        $record = $this->makeRecord([
            'access_token' => 'abc',
            'refresh_token' => 'xyz',
            'user_password_hash' => 'hash',
        ]);
        $result = ($this->processor)($record);

        $this->assertSame('***', $result->context['access_token']);
        $this->assertSame('***', $result->context['refresh_token']);
        $this->assertSame('***', $result->context['user_password_hash']);
    }

    public function testExtraKeysAreMasked(): void
    {
        $processor = new LogMaskingProcessor(['custom_field']);
        $record = $this->makeRecord(['custom_field' => 'sensitive']);
        $result = ($processor)($record);

        $this->assertSame('***', $result->context['custom_field']);
    }

    public function testEmptyContextReturnsEmpty(): void
    {
        $record = $this->makeRecord([]);
        $result = ($this->processor)($record);

        $this->assertSame([], $result->context);
    }

    public function testMasksCreditCard(): void
    {
        $record = $this->makeRecord(['credit_card' => '4111111111111111']);
        $result = ($this->processor)($record);

        $this->assertSame('***', $result->context['credit_card']);
    }

    public function testMasksSsn(): void
    {
        $record = $this->makeRecord(['ssn' => '123-45-6789']);
        $result = ($this->processor)($record);

        $this->assertSame('***', $result->context['ssn']);
    }
}
