<?php

namespace Fennec\Core\RateLimiter;

class InMemoryStore implements RateLimiterStoreInterface
{
    /** @var array<string, array{hits: int, resetAt: int}> */
    private static array $counters = [];

    /** Taille max des compteurs pour eviter les fuites en mode worker. */
    private static int $maxSize = 1000;

    /** Compteur d'operations pour declencher la purge globale. */
    private static int $opCount = 0;

    /** Purge globale toutes les N operations. */
    private static int $purgeInterval = 200;

    public function increment(string $key, int $windowSeconds): array
    {
        $now = time();
        self::$opCount++;

        // Pruner l'entree courante si expiree
        if (isset(self::$counters[$key]) && self::$counters[$key]['resetAt'] <= $now) {
            unset(self::$counters[$key]);
        }

        // Purge globale periodique des entrees expirees
        if (self::$opCount >= self::$purgeInterval) {
            self::purgeExpired();
        }

        if (!isset(self::$counters[$key])) {
            self::$counters[$key] = [
                'hits' => 0,
                'resetAt' => $now + $windowSeconds,
            ];
        }

        self::$counters[$key]['hits']++;

        // Eviction FIFO si on depasse la taille max
        if (count(self::$counters) > self::$maxSize) {
            reset(self::$counters);
            unset(self::$counters[key(self::$counters)]);
        }

        return self::$counters[$key];
    }

    /**
     * Purge toutes les entrees expirees.
     */
    public static function purgeExpired(): void
    {
        $now = time();

        foreach (self::$counters as $key => $counter) {
            if ($counter['resetAt'] <= $now) {
                unset(self::$counters[$key]);
            }
        }

        self::$opCount = 0;
    }

    /**
     * Vide tous les compteurs (pour les tests).
     */
    public static function flush(): void
    {
        self::$counters = [];
        self::$opCount = 0;
    }

    /**
     * Retourne le nombre de compteurs actifs.
     */
    public static function count(): int
    {
        return count(self::$counters);
    }

    /**
     * Definit la taille max des compteurs.
     */
    public static function setMaxSize(int $size): void
    {
        self::$maxSize = $size;
    }
}
