<?php

namespace Fennec\Core\Security;

use Fennec\Core\Env;

/**
 * Verrouillage de compte apres N tentatives echouees (ISO 27001 A.8.5).
 *
 * Stocke les tentatives via Redis (production) ou fichier (fallback).
 * En mode worker, le cache memoire evite les I/O inutiles.
 *
 * Config :
 *   LOCKOUT_MAX_ATTEMPTS=5     (tentatives avant verrouillage)
 *   LOCKOUT_DURATION=900       (secondes de verrouillage)
 *
 * Usage :
 *   if (AccountLockout::isLocked($email)) { throw 429; }
 *   AccountLockout::recordFailure($email);
 *   AccountLockout::reset($email); // apres login reussi
 */
class AccountLockout
{
    /** @var array<string, array{attempts: int, locked_until: int}> */
    private static array $cache = [];

    /** Taille max du cache memoire pour eviter les fuites en mode worker. */
    private static int $maxCacheSize = 500;

    /** Compteur d'operations pour declencher la purge periodique. */
    private static int $opsSinceLastPurge = 0;

    /** Purge automatique toutes les N operations. */
    private static int $purgeInterval = 100;

    private static ?int $maxAttempts = null;
    private static ?int $lockoutDuration = null;
    private static ?\Redis $redis = null;
    private static bool $redisChecked = false;

    /**
     * Verifie si un compte est verrouille.
     */
    public static function isLocked(string $identifier): bool
    {
        $entry = self::load($identifier);

        if ($entry === null) {
            return false;
        }

        // Verrouillage expire — reset
        if ($entry['locked_until'] > 0 && time() >= $entry['locked_until']) {
            self::reset($identifier);

            return false;
        }

        return $entry['locked_until'] > 0 && time() < $entry['locked_until'];
    }

    /**
     * Retourne le nombre de secondes restantes avant deblocage (0 si non verrouille).
     */
    public static function remainingLockout(string $identifier): int
    {
        $entry = self::load($identifier);

        if ($entry === null || $entry['locked_until'] <= 0) {
            return 0;
        }

        return max(0, $entry['locked_until'] - time());
    }

    /**
     * Enregistre une tentative echouee. Verrouille si le seuil est atteint.
     */
    public static function recordFailure(string $identifier): void
    {
        $maxAttempts = self::getMaxAttempts();
        $lockoutDuration = self::getLockoutDuration();

        $entry = self::load($identifier) ?? ['attempts' => 0, 'locked_until' => 0];
        $entry['attempts']++;

        if ($entry['attempts'] >= $maxAttempts) {
            $entry['locked_until'] = time() + $lockoutDuration;

            SecurityLogger::alert('account.locked', [
                'identifier' => $identifier,
                'attempts' => $entry['attempts'],
                'locked_until' => date('c', $entry['locked_until']),
            ]);
        }

        self::save($identifier, $entry);
    }

    /**
     * Reset les tentatives (apres login reussi).
     */
    public static function reset(string $identifier): void
    {
        unset(self::$cache[$identifier]);

        $redis = self::redis();
        if ($redis !== null) {
            $redis->del(self::redisKey($identifier));

            return;
        }

        $file = self::filePath($identifier);
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    /**
     * Retourne le nombre de tentatives pour un identifiant.
     */
    public static function attempts(string $identifier): int
    {
        $entry = self::load($identifier);

        return $entry['attempts'] ?? 0;
    }

    /**
     * Retourne tous les comptes actuellement verrouilles.
     *
     * @return array<string, array{attempts: int, locked_until: int, remaining: int}>
     */
    public static function locked(): array
    {
        $result = [];
        $now = time();

        $redis = self::redis();
        if ($redis !== null) {
            $prefix = self::redisPrefix();
            $keys = $redis->keys($prefix . '*');
            foreach ($keys as $key) {
                $data = $redis->get($key);
                if ($data === false) {
                    continue;
                }
                $entry = json_decode($data, true);
                if ($entry && isset($entry['locked_until']) && $entry['locked_until'] > $now) {
                    $id = str_replace($prefix, '', $key);
                    $result[$id] = [
                        'attempts' => $entry['attempts'],
                        'locked_until' => $entry['locked_until'],
                        'remaining' => $entry['locked_until'] - $now,
                    ];
                }
            }

            return $result;
        }

        // Fallback fichier
        $dir = self::lockoutDir();
        if (!is_dir($dir)) {
            return $result;
        }
        foreach (glob($dir . '/*.json') as $file) {
            $data = @file_get_contents($file);
            if ($data === false) {
                continue;
            }
            $entry = json_decode($data, true);
            if ($entry && isset($entry['locked_until']) && $entry['locked_until'] > $now) {
                $id = urldecode(basename($file, '.json'));
                $result[$id] = [
                    'attempts' => $entry['attempts'],
                    'locked_until' => $entry['locked_until'],
                    'remaining' => $entry['locked_until'] - $now,
                ];
            }
        }

        return $result;
    }

    // ── Persistence ──────────────────────────

    /**
     * @return array{attempts: int, locked_until: int}|null
     */
    private static function load(string $identifier): ?array
    {
        // Cache memoire (rapide en mode worker)
        if (isset(self::$cache[$identifier])) {
            return self::$cache[$identifier];
        }

        $redis = self::redis();
        if ($redis !== null) {
            $data = $redis->get(self::redisKey($identifier));
            if ($data !== false) {
                $entry = json_decode($data, true);
                self::$cache[$identifier] = $entry;

                return $entry;
            }

            return null;
        }

        // Fallback fichier
        $file = self::filePath($identifier);
        if (!file_exists($file)) {
            return null;
        }

        $data = @file_get_contents($file);
        if ($data === false) {
            return null;
        }

        $entry = json_decode($data, true);
        if (!is_array($entry)) {
            return null;
        }

        self::$cache[$identifier] = $entry;

        return $entry;
    }

    /**
     * @param array{attempts: int, locked_until: int} $entry
     */
    private static function save(string $identifier, array $entry): void
    {
        self::$cache[$identifier] = $entry;
        self::$opsSinceLastPurge++;

        // Purge periodique des entrees expirees
        if (self::$opsSinceLastPurge >= self::$purgeInterval) {
            self::purgeExpired();
        }

        // Eviction si le cache depasse la taille max
        if (count(self::$cache) > self::$maxCacheSize) {
            self::evictOldest();
        }

        $ttl = self::getLockoutDuration() + 60; // TTL = lockout + 1 minute de marge

        $redis = self::redis();
        if ($redis !== null) {
            $redis->setex(self::redisKey($identifier), $ttl, json_encode($entry));

            return;
        }

        // Fallback fichier
        $dir = self::lockoutDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = self::filePath($identifier);
        file_put_contents($file, json_encode($entry), LOCK_EX);
    }

    // ── Cache management ──────────────────────────

    /**
     * Purge les entrees expirees du cache memoire.
     *
     * Une entree est expiree si son lockout_until est passe
     * et qu'elle n'a plus de raison d'etre en cache.
     */
    public static function purgeExpired(): void
    {
        $now = time();
        $duration = self::getLockoutDuration();

        foreach (self::$cache as $key => $entry) {
            // Entree verrouillee mais expiree
            if ($entry['locked_until'] > 0 && $entry['locked_until'] <= $now) {
                unset(self::$cache[$key]);
                continue;
            }

            // Entree non verrouillee — garder seulement pendant la duree du lockout
            // (apres ce delai, l'entree backend fait foi)
            if ($entry['locked_until'] === 0 && $entry['attempts'] > 0) {
                // On ne peut pas connaitre l'age exact, mais si le lockout est
                // expire c'est que l'entree est ancienne
                continue;
            }
        }

        self::$opsSinceLastPurge = 0;
    }

    /**
     * Evicte les entrees les plus anciennes pour rester sous $maxCacheSize.
     */
    private static function evictOldest(): void
    {
        // Supprimer les entrees expirees d'abord
        self::purgeExpired();

        // Si toujours trop grand, supprimer les premieres entrees (FIFO)
        while (count(self::$cache) > self::$maxCacheSize) {
            reset(self::$cache);
            $oldest = key(self::$cache);
            if ($oldest === null) {
                break;
            }
            unset(self::$cache[$oldest]);
        }
    }

    /**
     * Retourne le nombre d'entrees dans le cache memoire.
     */
    public static function cacheSize(): int
    {
        return count(self::$cache);
    }

    /**
     * Definit la taille max du cache memoire.
     */
    public static function setMaxCacheSize(int $size): void
    {
        self::$maxCacheSize = $size;
    }

    // ── Redis ──────────────────────────

    private static function redis(): ?\Redis
    {
        if (self::$redisChecked) {
            return self::$redis;
        }

        self::$redisChecked = true;

        if (!extension_loaded('redis') || !Env::get('REDIS_HOST')) {
            return null;
        }

        try {
            $redis = new \Redis();
            $redis->connect(
                Env::get('REDIS_HOST', '127.0.0.1'),
                (int) Env::get('REDIS_PORT', '6379'),
            );

            $password = Env::get('REDIS_PASSWORD');
            if ($password !== '') {
                $redis->auth($password);
            }

            $db = (int) Env::get('REDIS_DB', '0');
            if ($db !== 0) {
                $redis->select($db);
            }

            self::$redis = $redis;
        } catch (\Throwable) {
            self::$redis = null;
        }

        return self::$redis;
    }

    private static function redisPrefix(): string
    {
        return Env::get('REDIS_PREFIX', 'app:') . 'lockout:';
    }

    private static function redisKey(string $identifier): string
    {
        return self::redisPrefix() . $identifier;
    }

    // ── File fallback ──────────────────────────

    private static function lockoutDir(): string
    {
        return FENNEC_BASE_PATH . '/var/lockout';
    }

    private static function filePath(string $identifier): string
    {
        return self::lockoutDir() . '/' . urlencode($identifier) . '.json';
    }

    // ── Config ──────────────────────────

    private static function getMaxAttempts(): int
    {
        if (self::$maxAttempts === null) {
            self::$maxAttempts = (int) Env::get('LOCKOUT_MAX_ATTEMPTS', '5');
        }

        return self::$maxAttempts;
    }

    private static function getLockoutDuration(): int
    {
        if (self::$lockoutDuration === null) {
            self::$lockoutDuration = (int) Env::get('LOCKOUT_DURATION', '900');
        }

        return self::$lockoutDuration;
    }

    /**
     * Reset l'etat complet (pour les tests).
     */
    public static function flush(): void
    {
        self::$cache = [];
        self::$maxAttempts = null;
        self::$lockoutDuration = null;
        self::$redis = null;
        self::$redisChecked = false;

        // Nettoyer les fichiers de lockout
        $dir = self::lockoutDir();
        if (is_dir($dir)) {
            foreach (glob($dir . '/*.json') as $file) {
                @unlink($file);
            }
        }
    }
}
