<?php

namespace Fennec\Core\Ui;

use Fennec\Core\Env;
use Fennec\Core\Response;
use Fennec\Core\Security\SecurityLogger;

class UiCacheController
{
    public function stats(): void
    {
        $driver = Env::get('CACHE_DRIVER') ?: 'file';

        $stats = [
            'driver' => $driver,
            'routeCacheEnabled' => file_exists((defined('FENNEC_BASE_PATH') ? FENNEC_BASE_PATH : '') . '/var/cache/routes.cache.php'),
        ];

        if ($driver === 'redis') {
            try {
                $redis = $this->getRedis();
                if ($redis) {
                    $info = $redis->info('memory');
                    $stats['memoryUsed'] = $info['used_memory'] ?? 0;
                    $stats['memoryPeak'] = $info['used_memory_peak'] ?? 0;
                    $stats['connectedClients'] = $redis->info('clients')['connected_clients'] ?? 0;

                    $prefix = Env::get('REDIS_PREFIX') ?: 'fennec:';
                    $keys = $redis->keys($prefix . '*');
                    $stats['keys'] = count($keys ?: []);
                }
            } catch (\Throwable) {
                $stats['error'] = 'Redis not available';
            }
        } elseif ($driver === 'file') {
            $cacheDir = (defined('FENNEC_BASE_PATH') ? FENNEC_BASE_PATH : '') . '/var/cache';
            if (is_dir($cacheDir)) {
                $files = glob($cacheDir . '/*');
                $stats['files'] = count($files ?: []);
                $stats['size'] = array_sum(array_map('filesize', $files ?: []));
            }
        }

        Response::json($stats);
    }

    public function flush(): void
    {
        $driver = Env::get('CACHE_DRIVER') ?: 'file';

        try {
            if ($driver === 'redis') {
                $redis = $this->getRedis();
                if ($redis) {
                    $prefix = Env::get('REDIS_PREFIX') ?: 'fennec:';
                    $keys = $redis->keys($prefix . '*');
                    if ($keys) {
                        $redis->del($keys);
                    }
                }
            } elseif ($driver === 'file') {
                $cacheDir = (defined('FENNEC_BASE_PATH') ? FENNEC_BASE_PATH : '') . '/var/cache';
                if (is_dir($cacheDir)) {
                    $files = glob($cacheDir . '/*.cache*');
                    foreach ($files ?: [] as $file) {
                        @unlink($file);
                    }
                }
            }

            SecurityLogger::track('cache.flushed', ['driver' => $driver, 'by' => 'admin_ui']);
            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function flushRoutes(): void
    {
        $cacheFile = (defined('FENNEC_BASE_PATH') ? FENNEC_BASE_PATH : '') . '/var/cache/routes.cache.php';

        if (file_exists($cacheFile)) {
            @unlink($cacheFile);
        }

        Response::json(['success' => true]);
    }

    private function getRedis(): ?\Redis
    {
        if (!extension_loaded('redis')) {
            return null;
        }

        try {
            $redis = new \Redis();
            $redis->connect(
                Env::get('REDIS_HOST') ?: '127.0.0.1',
                (int) (Env::get('REDIS_PORT') ?: 6379)
            );
            $password = Env::get('REDIS_PASSWORD');
            if ($password) {
                $redis->auth($password);
            }

            return $redis;
        } catch (\Throwable) {
            return null;
        }
    }
}
