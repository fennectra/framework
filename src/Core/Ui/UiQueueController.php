<?php

namespace Fennec\Core\Ui;

use Fennec\Core\DB;
use Fennec\Core\Env;
use Fennec\Core\Response;

class UiQueueController
{
    use UiHelper;

    public function stats(): void
    {
        $driver = Env::get('QUEUE_DRIVER') ?: 'database';

        $stats = [
            'driver' => $driver,
            'pending' => 0,
            'failed' => 0,
            'processed' => 0,
        ];

        try {
            if ($driver === 'database') {
                $stats['pending'] = (int) (DB::raw(
                    'SELECT COUNT(*) as cnt FROM jobs WHERE reserved_at IS NULL'
                )->fetchAll()[0]['cnt'] ?? 0);

                $stats['failed'] = (int) (DB::raw(
                    'SELECT COUNT(*) as cnt FROM failed_jobs'
                )->fetchAll()[0]['cnt'] ?? 0);
            } elseif ($driver === 'redis') {
                $redis = $this->getRedis();
                if ($redis) {
                    $stats['pending'] = $redis->lLen('queues:default') ?: 0;
                    $stats['failed'] = (int) (DB::raw(
                        'SELECT COUNT(*) as cnt FROM failed_jobs'
                    )->fetchAll()[0]['cnt'] ?? 0);
                }
            }
        } catch (\Throwable) {
            // Tables may not exist
        }

        Response::json($stats);
    }

    public function failed(): void
    {
        try {
            $result = $this->paginate('failed_jobs');

            foreach ($result['data'] as &$row) {
                $row['payload'] = json_decode($row['payload'] ?? '{}', true) ?? [];
            }

            Response::json($result);
        } catch (\Throwable) {
            Response::json(['data' => [], 'total' => 0, 'page' => 1, 'per_page' => 20, 'last_page' => 0]);
        }
    }

    public function retry(int $id): void
    {
        try {
            $job = DB::raw('SELECT * FROM failed_jobs WHERE id = ?', [$id])->fetchAll()[0] ?? null;

            if (!$job) {
                Response::json(['error' => 'Failed job not found'], 404);

                return;
            }

            $payload = json_decode($job['payload'] ?? '{}', true) ?? [];
            $queue = $job['queue'] ?? 'default';
            $jobClass = $job['job_class'] ?? '';

            if ($jobClass) {
                \Fennec\Core\Queue\Job::dispatch($jobClass, $payload, $queue);
                DB::raw('DELETE FROM failed_jobs WHERE id = ?', [$id]);
            }

            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function flush(): void
    {
        try {
            DB::raw('DELETE FROM failed_jobs');
            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function deleteFailed(int $id): void
    {
        try {
            DB::raw('DELETE FROM failed_jobs WHERE id = ?', [$id]);
            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
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
