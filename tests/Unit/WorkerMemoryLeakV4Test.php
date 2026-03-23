<?php

namespace Tests\Unit;

use Fennec\Core\RateLimiter\RedisStore;
use Fennec\Core\Redis\RedisLock;
use PHPUnit\Framework\TestCase;

/**
 * Tests de detection de fuites memoire — vague 4.
 *
 * RedisLock disconnect, RedisStore disconnect, SseController try/finally.
 *
 * @see ST-501
 */
class WorkerMemoryLeakV4Test extends TestCase
{
    // ── RedisLock — disconnect + health check ────────────────

    public function testRedisLockDisconnectCleansUp(): void
    {
        $lock = new RedisLock(host: '127.0.0.1');

        $ref = new \ReflectionClass($lock);
        $redisProp = $ref->getProperty('redis');

        // Avant connect, redis est null
        $this->assertNull($redisProp->getValue($lock));

        // disconnect() sur null = no-op
        $lock->disconnect();
        $this->assertNull($redisProp->getValue($lock));
    }

    public function testRedisLockDisconnectIsIdempotent(): void
    {
        $lock = new RedisLock(host: '127.0.0.1');

        // Appeler 5 fois sans erreur
        for ($i = 0; $i < 5; $i++) {
            $lock->disconnect();
        }

        $ref = new \ReflectionClass($lock);
        $redisProp = $ref->getProperty('redis');
        $this->assertNull($redisProp->getValue($lock));
    }

    public function testRedisLockConnectHasHealthCheck(): void
    {
        $lock = new RedisLock(host: '127.0.0.1');

        $ref = new \ReflectionClass($lock);
        $connectMethod = $ref->getMethod('connect');

        // Le code contient un ping() health check
        $source = file_get_contents($ref->getFileName());
        $this->assertStringContainsString(
            '->ping()',
            $source,
            'RedisLock::connect() doit contenir un health check ping()'
        );
    }

    // ── RedisStore — disconnect + health check ───────────────

    public function testRedisStoreDisconnectCleansUp(): void
    {
        $store = new RedisStore(host: '127.0.0.1');

        $ref = new \ReflectionClass($store);
        $redisProp = $ref->getProperty('redis');

        $this->assertNull($redisProp->getValue($store));

        // disconnect() sur null = no-op
        $store->disconnect();
        $this->assertNull($redisProp->getValue($store));
    }

    public function testRedisStoreDisconnectIsIdempotent(): void
    {
        $store = new RedisStore(host: '127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            $store->disconnect();
        }

        $ref = new \ReflectionClass($store);
        $redisProp = $ref->getProperty('redis');
        $this->assertNull($redisProp->getValue($store));
    }

    public function testRedisStoreConnectHasHealthCheck(): void
    {
        $store = new RedisStore(host: '127.0.0.1');

        $ref = new \ReflectionClass($store);
        $source = file_get_contents($ref->getFileName());
        $this->assertStringContainsString(
            '->ping()',
            $source,
            'RedisStore::connect() doit contenir un health check ping()'
        );
    }

    // ── SseController — try/finally ──────────────────────────

    public function testSseControllerUseTryFinally(): void
    {
        $ref = new \ReflectionClass(\Fennec\Core\Broadcasting\SseController::class);
        $source = file_get_contents($ref->getFileName());

        // Verifier que la boucle SSE est dans un try/finally
        $this->assertStringContainsString(
            'finally',
            $source,
            'SseController::stream() doit utiliser try/finally pour garantir redis->close()'
        );

        // Verifier que close() est dans le finally
        $finallyPos = strpos($source, 'finally');
        $closePos = strpos($source, '$redis->close()', $finallyPos);
        $this->assertNotFalse(
            $closePos,
            '$redis->close() doit etre dans le bloc finally'
        );
    }

    // ── Destructeurs ─────────────────────────────────────────

    public function testRedisLockHasDestructor(): void
    {
        $ref = new \ReflectionClass(RedisLock::class);
        $this->assertTrue(
            $ref->hasMethod('__destruct'),
            'RedisLock doit avoir un __destruct() pour fermer la connexion'
        );
    }

    public function testRedisStoreHasDestructor(): void
    {
        $ref = new \ReflectionClass(RedisStore::class);
        $this->assertTrue(
            $ref->hasMethod('__destruct'),
            'RedisStore doit avoir un __destruct() pour fermer la connexion'
        );
    }
}
