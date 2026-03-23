<?php

namespace Fennec\Core\Cache;

use Fennec\Core\Redis\RedisConnection;

class RedisCache implements CacheInterface
{
    private string $prefix = 'cache:';

    public function __construct(
        private RedisConnection $redis,
    ) {
    }

    /**
     * Returns the underlying RedisConnection.
     */
    public function getRedisConnection(): RedisConnection
    {
        return $this->redis;
    }

    public function get(string $key): mixed
    {
        $raw = $this->redis->get($this->prefix . $key);

        if ($raw === null) {
            return null;
        }

        return json_decode($raw, true);
    }

    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
        $this->redis->set(
            $this->prefix . $key,
            json_encode($value, JSON_THROW_ON_ERROR),
            $ttl,
        );
    }

    public function has(string $key): bool
    {
        return $this->redis->exists($this->prefix . $key);
    }

    public function forget(string $key): bool
    {
        return $this->redis->del($this->prefix . $key) > 0;
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    public function flush(): void
    {
        // Use EVAL to scan and delete all keys with our prefix
        $connection = $this->redis->connection();
        $prefix = $connection->getOption(\Redis::OPT_PREFIX) ?: '';
        $fullPrefix = $prefix . $this->prefix;

        $cursor = null;

        do {
            $keys = $connection->scan($cursor, $fullPrefix . '*', 100);

            if ($keys !== false && count($keys) > 0) {
                $connection->del($keys);
            }
        } while ($cursor > 0);
    }
}
