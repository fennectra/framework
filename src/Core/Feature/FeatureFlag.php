<?php

namespace Fennec\Core\Feature;

use Fennec\Core\DB;
use Fennec\Core\Env;

class FeatureFlag
{
    private static ?\Redis $redis = null;
    private static int $cacheTtl = 60;

    /**
     * Verifie si un feature flag est active.
     */
    public static function enabled(string $key): bool
    {
        $cached = self::getFromCache($key);

        if ($cached !== null) {
            return $cached === 'enabled';
        }

        $row = self::getFromDb($key);

        if ($row === null) {
            self::setCache($key, 'disabled');

            return false;
        }

        $enabled = (bool) $row['enabled'];
        self::setCache($key, $enabled ? 'enabled' : 'disabled');

        return $enabled;
    }

    /**
     * Verifie si un feature flag est desactive.
     */
    public static function disabled(string $key): bool
    {
        return !self::enabled($key);
    }

    /**
     * Active un feature flag.
     */
    public static function activate(string $key): void
    {
        $row = self::getFromDb($key);

        if ($row === null) {
            self::define($key, true);

            return;
        }

        DB::table('feature_flags')
            ->where('key', $key)
            ->update([
                'enabled' => true,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        self::setCache($key, 'enabled');
    }

    /**
     * Desactive un feature flag.
     */
    public static function deactivate(string $key): void
    {
        $row = self::getFromDb($key);

        if ($row === null) {
            self::define($key, false);

            return;
        }

        DB::table('feature_flags')
            ->where('key', $key)
            ->update([
                'enabled' => false,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        self::setCache($key, 'disabled');
    }

    /**
     * Definit un feature flag avec ses regles optionnelles.
     */
    public static function define(string $key, bool $enabled, ?array $rules = null): void
    {
        $now = date('Y-m-d H:i:s');

        $existing = self::getFromDb($key);

        if ($existing !== null) {
            $data = [
                'enabled' => $enabled,
                'updated_at' => $now,
            ];
            if ($rules !== null) {
                $data['rules'] = json_encode($rules);
            }

            DB::table('feature_flags')->where('key', $key)->update($data);
        } else {
            DB::table('feature_flags')->insert([
                'key' => $key,
                'enabled' => $enabled,
                'rules' => $rules !== null ? json_encode($rules) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        self::setCache($key, $enabled ? 'enabled' : 'disabled');
    }

    /**
     * Retourne un builder pour evaluation conditionnelle.
     */
    public static function for(string $key): FeatureFlagBuilder
    {
        return new FeatureFlagBuilder($key);
    }

    // ─── Cache Redis ─────────────────────────────────────────

    private static function getFromCache(string $key): ?string
    {
        try {
            $redis = self::redis();
            if ($redis === null) {
                return null;
            }

            $prefix = Env::get('REDIS_PREFIX', 'app:');
            $value = $redis->get($prefix . 'feature:' . $key);

            return $value !== false ? $value : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function setCache(string $key, string $value): void
    {
        try {
            $redis = self::redis();
            if ($redis === null) {
                return;
            }

            $prefix = Env::get('REDIS_PREFIX', 'app:');
            $redis->setex($prefix . 'feature:' . $key, self::$cacheTtl, $value);
        } catch (\Throwable) {
            // Silencieux — le cache est optionnel
        }
    }

    private static function getFromDb(string $key): ?array
    {
        try {
            return DB::table('feature_flags')->where('key', $key)->first();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function redis(): ?\Redis
    {
        if (self::$redis !== null) {
            // Verifier que la connexion est toujours vivante
            try {
                self::$redis->ping();
            } catch (\Throwable) {
                self::$redis = null;
            }
        }

        if (self::$redis !== null) {
            return self::$redis;
        }

        if (!extension_loaded('redis')) {
            return null;
        }

        try {
            self::$redis = new \Redis();
            self::$redis->connect(
                Env::get('REDIS_HOST', '127.0.0.1'),
                (int) Env::get('REDIS_PORT', '6379'),
            );

            $password = Env::get('REDIS_PASSWORD');
            if ($password !== '') {
                self::$redis->auth($password);
            }

            $db = (int) Env::get('REDIS_DB', '0');
            if ($db !== 0) {
                self::$redis->select($db);
            }

            return self::$redis;
        } catch (\Throwable) {
            self::$redis = null;

            return null;
        }
    }

    /**
     * Ferme et reset la connexion Redis (cleanup worker).
     */
    public static function resetConnection(): void
    {
        if (self::$redis !== null) {
            try {
                self::$redis->close();
            } catch (\Throwable) {
                // Silencieux
            }
            self::$redis = null;
        }
    }
}
