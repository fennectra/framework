<?php

namespace Fennec\Core\Redis;

use Fennec\Core\Env;

/**
 * Shared Redis connection wrapper with lazy connection.
 *
 * Requires ext-redis. Throws RuntimeException if not loaded.
 *
 * Variables d'environnement :
 *   REDIS_HOST     (défaut: 127.0.0.1)
 *   REDIS_PORT     (défaut: 6379)
 *   REDIS_PASSWORD (défaut: null)
 *   REDIS_DB       (défaut: 0)
 *   REDIS_PREFIX   (défaut: app:)
 */
class RedisConnection
{
    private ?\Redis $redis = null;

    public function __construct(
        private string $host = '127.0.0.1',
        private int $port = 6379,
        private ?string $password = null,
        private int $db = 0,
        private string $prefix = 'app:',
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(
            host: Env::get('REDIS_HOST', '127.0.0.1'),
            port: (int) Env::get('REDIS_PORT', '6379'),
            password: Env::get('REDIS_PASSWORD') ?: null,
            db: (int) Env::get('REDIS_DB', '0'),
            prefix: Env::get('REDIS_PREFIX', 'app:'),
        );
    }

    /**
     * Returns the raw \Redis instance, connecting lazily if needed.
     */
    public function connection(): \Redis
    {
        $this->connect();

        return $this->redis;
    }

    public function get(string $key): mixed
    {
        $this->connect();
        $value = $this->redis->get($this->prefix . $key);

        return $value === false ? null : $value;
    }

    public function set(string $key, mixed $value, ?int $ex = null): bool
    {
        $this->connect();

        if ($ex !== null) {
            return $this->redis->setex($this->prefix . $key, $ex, $value);
        }

        return $this->redis->set($this->prefix . $key, $value);
    }

    public function del(string $key): int
    {
        $this->connect();

        return $this->redis->del($this->prefix . $key);
    }

    public function exists(string $key): bool
    {
        $this->connect();

        return (bool) $this->redis->exists($this->prefix . $key);
    }

    public function expire(string $key, int $ttl): bool
    {
        $this->connect();

        return $this->redis->expire($this->prefix . $key, $ttl);
    }

    public function ttl(string $key): int
    {
        $this->connect();

        return $this->redis->ttl($this->prefix . $key);
    }

    public function incr(string $key): int
    {
        $this->connect();

        return $this->redis->incr($this->prefix . $key);
    }

    public function setnx(string $key, mixed $value): bool
    {
        $this->connect();

        return $this->redis->setnx($this->prefix . $key, $value);
    }

    public function lPush(string $key, mixed $value): int|false
    {
        $this->connect();

        return $this->redis->lPush($this->prefix . $key, $value);
    }

    public function lPop(string $key): mixed
    {
        $this->connect();

        return $this->redis->lPop($this->prefix . $key);
    }

    public function rPush(string $key, mixed $value): int|false
    {
        $this->connect();

        return $this->redis->rPush($this->prefix . $key, $value);
    }

    /**
     * Execute a Lua script via EVAL.
     *
     * @param string   $script Lua script body
     * @param string[] $keys   Redis keys referenced in the script
     * @param mixed[]  $args   Additional arguments passed to the script
     */
    public function eval(string $script, array $keys = [], array $args = []): mixed
    {
        $this->connect();

        return $this->redis->eval($script, array_merge($keys, $args), count($keys));
    }

    /**
     * Execute multiple commands in a pipeline.
     *
     * The callable receives the \Redis instance in PIPELINE mode.
     */
    public function pipeline(callable $callback): array
    {
        $this->connect();
        $pipe = $this->redis->pipeline();
        $callback($pipe);

        return $pipe->exec();
    }

    public function ping(): bool
    {
        $this->connect();

        return $this->redis->ping() === true || $this->redis->ping() === '+PONG';
    }

    private function connect(): void
    {
        if ($this->redis !== null) {
            return;
        }

        if (!extension_loaded('redis')) {
            throw new \RuntimeException(
                'Extension php-redis requise. Installez-la avec: pecl install redis'
            );
        }

        $this->redis = new \Redis();
        $this->redis->connect($this->host, $this->port);

        if ($this->password !== null) {
            $this->redis->auth($this->password);
        }

        if ($this->db !== 0) {
            $this->redis->select($this->db);
        }
    }
}
