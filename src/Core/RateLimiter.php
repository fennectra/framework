<?php

namespace Fennec\Core;

use Fennec\Core\RateLimiter\RateLimiterStoreInterface;

class RateLimiter
{
    public function __construct(
        private RateLimiterStoreInterface $store,
    ) {
    }

    /**
     * Verifie si la requete est dans la limite.
     *
     * @return array{allowed: bool, limit: int, remaining: int, resetAt: int}
     */
    public function check(string $key, int $limit, int $windowSeconds): array
    {
        $result = $this->store->increment($key, $windowSeconds);
        $remaining = max(0, $limit - $result['hits']);

        return [
            'allowed' => $result['hits'] <= $limit,
            'limit' => $limit,
            'remaining' => $remaining,
            'resetAt' => $result['resetAt'],
        ];
    }
}
