<?php

namespace Fennec\Core\RateLimiter;

interface RateLimiterStoreInterface
{
    /**
     * Incremente le compteur pour une cle et retourne [hits, resetAt].
     *
     * @return array{hits: int, resetAt: int}
     */
    public function increment(string $key, int $windowSeconds): array;
}
