<?php

namespace Tests\Unit;

use Fennec\Core\Notification\Messages\WebhookMessage;
use Fennec\Core\Notification\Notification;
use Fennec\Core\Notification\NotificationInterface;
use Fennec\Core\Webhook\WebhookDeliveryJob;
use Fennec\Core\Webhook\WebhookManager;
use PHPUnit\Framework\TestCase;

class WebhookTest extends TestCase
{
    // ── Signature HMAC ──────────────────────────

    public function testSignGeneratesHmacSha256(): void
    {
        $signature = WebhookManager::sign('{"test":true}', 'secret123', 1700000000);

        $this->assertStringStartsWith('sha256=', $signature);
        $this->assertSame(71, strlen($signature)); // sha256= (7) + 64 hex chars
    }

    public function testSignIsDeterministic(): void
    {
        $sig1 = WebhookManager::sign('payload', 'secret', 1000);
        $sig2 = WebhookManager::sign('payload', 'secret', 1000);

        $this->assertSame($sig1, $sig2);
    }

    public function testSignDiffersWithDifferentSecrets(): void
    {
        $sig1 = WebhookManager::sign('payload', 'secret1', 1000);
        $sig2 = WebhookManager::sign('payload', 'secret2', 1000);

        $this->assertNotSame($sig1, $sig2);
    }

    public function testSignDiffersWithDifferentTimestamps(): void
    {
        $sig1 = WebhookManager::sign('payload', 'secret', 1000);
        $sig2 = WebhookManager::sign('payload', 'secret', 2000);

        $this->assertNotSame($sig1, $sig2);
    }

    public function testSignDiffersWithDifferentPayloads(): void
    {
        $sig1 = WebhookManager::sign('payload1', 'secret', 1000);
        $sig2 = WebhookManager::sign('payload2', 'secret', 1000);

        $this->assertNotSame($sig1, $sig2);
    }

    // ── Verification de signature ──────────────────────────

    public function testVerifyAcceptsValidSignature(): void
    {
        $payload = '{"event":"test"}';
        $secret = 'my-webhook-secret';
        $timestamp = time();
        $signature = WebhookManager::sign($payload, $secret, $timestamp);

        $this->assertTrue(WebhookManager::verify($payload, $secret, $signature, $timestamp));
    }

    public function testVerifyRejectsInvalidSignature(): void
    {
        $this->assertFalse(
            WebhookManager::verify('payload', 'secret', 'sha256=invalid', time())
        );
    }

    public function testVerifyRejectsExpiredTimestamp(): void
    {
        $payload = 'test';
        $secret = 'secret';
        $oldTimestamp = time() - 600; // 10 minutes ago
        $signature = WebhookManager::sign($payload, $secret, $oldTimestamp);

        $this->assertFalse(
            WebhookManager::verify($payload, $secret, $signature, $oldTimestamp)
        );
    }

    public function testVerifyAcceptsRecentTimestamp(): void
    {
        $payload = 'test';
        $secret = 'secret';
        $timestamp = time() - 120; // 2 minutes ago (within 5 min window)
        $signature = WebhookManager::sign($payload, $secret, $timestamp);

        $this->assertTrue(
            WebhookManager::verify($payload, $secret, $signature, $timestamp)
        );
    }

    // ── WebhookMessage ──────────────────────────

    public function testWebhookMessageFluentSetters(): void
    {
        $message = (new WebhookMessage())
            ->url('https://example.com/hook')
            ->secret('s3cret')
            ->event('user.created')
            ->payload(['id' => 42])
            ->headers(['X-Custom' => 'value']);

        $this->assertSame('https://example.com/hook', $message->url);
        $this->assertSame('s3cret', $message->secret);
        $this->assertSame('user.created', $message->event);
        $this->assertSame(['id' => 42], $message->payload);
        $this->assertSame(['X-Custom' => 'value'], $message->headers);
    }

    public function testWebhookMessageDefaults(): void
    {
        $message = new WebhookMessage();

        $this->assertSame('', $message->url);
        $this->assertSame('', $message->secret);
        $this->assertSame('', $message->event);
        $this->assertSame([], $message->payload);
        $this->assertSame([], $message->headers);
    }

    // ── WebhookDeliveryJob ──────────────────────────

    public function testDeliveryJobRetriesCount(): void
    {
        $job = new WebhookDeliveryJob();

        $this->assertSame(5, $job->retries());
    }

    public function testDeliveryJobRetryDelay(): void
    {
        $job = new WebhookDeliveryJob();

        $this->assertSame(10, $job->retryDelay());
    }

    // ── Notification base class ──────────────────────────

    public function testNotificationBaseReturnsNullForWebhook(): void
    {
        $notification = new class extends Notification {
            public function via(): array
            {
                return ['webhook'];
            }
        };

        $this->assertNull($notification->toWebhook());
        $this->assertContains('webhook', $notification->via());
    }

    public function testNotificationInterfaceHasWebhookMethod(): void
    {
        $ref = new \ReflectionClass(NotificationInterface::class);

        $this->assertTrue($ref->hasMethod('toWebhook'));
    }

    // ── WebhookManager instance ──────────────────────────

    public function testWebhookManagerSetAndGetInstance(): void
    {
        $manager = new WebhookManager();
        WebhookManager::setInstance($manager);

        $this->assertSame($manager, WebhookManager::getInstance());
    }

    public function testWebhookManagerThrowsWhenNotInitialized(): void
    {
        // Reset instance via reflection
        $ref = new \ReflectionClass(WebhookManager::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('WebhookManager not initialized');
        WebhookManager::getInstance();
    }

    public function testWebhookManagerClearCache(): void
    {
        $manager = new WebhookManager();
        WebhookManager::setInstance($manager);

        // Should not throw
        $manager->clearCache();

        $this->assertInstanceOf(WebhookManager::class, $manager);
    }
}
