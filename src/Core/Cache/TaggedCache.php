<?php

namespace Fennec\Core\Cache;

class TaggedCache implements CacheInterface
{
    /** @var string[] */
    private array $tags;

    /**
     * @param string[] $tags Tag names to associate with cached keys
     */
    public function __construct(
        private RedisCache $cache,
        array $tags,
    ) {
        $this->tags = $tags;
    }

    public function get(string $key): mixed
    {
        return $this->cache->get($key);
    }

    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
        $this->cache->set($key, $value, $ttl);
        $this->tagKey($key);
    }

    public function has(string $key): bool
    {
        return $this->cache->has($key);
    }

    public function forget(string $key): bool
    {
        $this->untagKey($key);

        return $this->cache->forget($key);
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = $this->cache->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Flush all keys associated with the current tags.
     *
     * Iterates each tag's Redis SET, deletes the cached keys,
     * then removes the tag sets themselves.
     */
    public function flush(): void
    {
        $redis = $this->cache->getRedisConnection();

        foreach ($this->tags as $tag) {
            $tagSetKey = 'tag:' . $tag;
            $members = $redis->connection()->sMembers($tagSetKey);

            if (count($members) > 0) {
                foreach ($members as $member) {
                    $this->cache->forget($member);
                }
            }

            $redis->del($tagSetKey);
        }
    }

    /**
     * Track a cache key in each tag's Redis SET.
     */
    private function tagKey(string $key): void
    {
        $redis = $this->cache->getRedisConnection();

        foreach ($this->tags as $tag) {
            $redis->connection()->sAdd('tag:' . $tag, $key);
        }
    }

    /**
     * Remove a cache key from each tag's Redis SET.
     */
    private function untagKey(string $key): void
    {
        $redis = $this->cache->getRedisConnection();

        foreach ($this->tags as $tag) {
            $redis->connection()->sRem('tag:' . $tag, $key);
        }
    }
}
