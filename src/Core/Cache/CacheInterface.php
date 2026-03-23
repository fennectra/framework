<?php

namespace Fennec\Core\Cache;

interface CacheInterface
{
    public function get(string $key): mixed;

    public function set(string $key, mixed $value, int $ttl = 3600): void;

    public function has(string $key): bool;

    public function forget(string $key): bool;

    /**
     * Get an item from the cache, or store the result of the callback.
     */
    public function remember(string $key, int $ttl, callable $callback): mixed;

    public function flush(): void;
}
