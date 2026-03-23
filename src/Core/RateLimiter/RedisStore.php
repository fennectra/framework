<?php

namespace Fennec\Core\RateLimiter;

use Fennec\Core\Env;

class RedisStore implements RateLimiterStoreInterface
{
    private ?\Redis $redis = null;

    public function __construct(
        private string $host = '127.0.0.1',
        private int $port = 6379,
        private ?string $password = null,
        private int $db = 0,
        private string $prefix = 'app:ratelimit:',
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(
            host: Env::get('REDIS_HOST', '127.0.0.1'),
            port: (int) Env::get('REDIS_PORT', '6379'),
            password: Env::get('REDIS_PASSWORD') ?: null,
            db: (int) Env::get('REDIS_DB', '0'),
        );
    }

    public function increment(string $key, int $windowSeconds): array
    {
        $this->connect();

        $redisKey = $this->prefix . $key;
        $hits = $this->redis->incr($redisKey);

        // Premier hit : mettre le TTL
        if ($hits === 1) {
            $this->redis->expire($redisKey, $windowSeconds);
        }

        $ttl = $this->redis->ttl($redisKey);
        $resetAt = time() + max($ttl, 0);

        return ['hits' => $hits, 'resetAt' => $resetAt];
    }

    private function connect(): void
    {
        // Health check : reconnecter si la connexion est morte
        if ($this->redis !== null) {
            try {
                $this->redis->ping();

                return;
            } catch (\Throwable) {
                $this->redis = null;
            }
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

    /**
     * Ferme la connexion Redis (cleanup worker).
     */
    public function disconnect(): void
    {
        if ($this->redis !== null) {
            try {
                $this->redis->close();
            } catch (\Throwable) {
                // Silencieux
            }
            $this->redis = null;
        }
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
