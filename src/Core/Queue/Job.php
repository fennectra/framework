<?php

namespace Fennec\Core\Queue;

use Fennec\Core\DB;
use Fennec\Core\Env;

class Job
{
    private static ?\Redis $redis = null;

    /**
     * Dispatch un job dans la queue.
     */
    public static function dispatch(string $jobClass, array $payload = [], string $queue = 'default'): void
    {
        $driver = Env::get('QUEUE_DRIVER', 'redis');

        $data = json_encode([
            'job' => $jobClass,
            'payload' => $payload,
            'attempts' => 0,
            'created_at' => date('c'),
        ], JSON_THROW_ON_ERROR);

        if ($driver === 'redis') {
            self::dispatchRedis($queue, $data);

            return;
        }

        self::dispatchDatabase($queue, $jobClass, $payload);
    }

    private static function dispatchRedis(string $queue, string $data): void
    {
        $redis = self::redis();
        $prefix = Env::get('REDIS_PREFIX', 'app:');
        $redis->rPush($prefix . 'queue:' . $queue, $data);
    }

    private static function dispatchDatabase(string $queue, string $jobClass, array $payload): void
    {
        DB::table('jobs')->insert([
            'queue' => $queue,
            'job_class' => $jobClass,
            'payload' => json_encode($payload),
            'attempts' => 0,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Retourne une connexion Redis reutilisee (singleton worker-safe).
     */
    private static function redis(): \Redis
    {
        // Verifier que la connexion existante est vivante
        if (self::$redis !== null) {
            try {
                self::$redis->ping();

                return self::$redis;
            } catch (\Throwable) {
                self::$redis = null;
            }
        }

        if (!extension_loaded('redis')) {
            throw new \RuntimeException('Extension php-redis requise pour QUEUE_DRIVER=redis.');
        }

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

        return self::$redis;
    }

    /**
     * Ferme la connexion Redis (cleanup worker).
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
