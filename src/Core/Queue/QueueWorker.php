<?php

namespace Fennec\Core\Queue;

use Fennec\Core\Container;
use Fennec\Core\DB;
use Fennec\Core\Env;

class QueueWorker
{
    private ?\Redis $redis = null;

    public function __construct(
        private FailedJobHandler $failedHandler = new FailedJobHandler(),
    ) {
    }

    /**
     * Boucle principale du worker.
     *
     * @param string $queue   Nom de la queue
     * @param int    $maxJobs 0 = illimite
     * @param int    $timeout Timeout BLPOP en secondes
     */
    public function work(string $queue = 'default', int $maxJobs = 0, int $timeout = 60): void
    {
        $driver = Env::get('QUEUE_DRIVER', 'redis');
        $processed = 0;

        echo "  \033[32m▶\033[0m  Worker demarre (queue={$queue}, driver={$driver})\n";

        while (true) {
            if ($maxJobs > 0 && $processed >= $maxJobs) {
                echo "  \033[33m⏹\033[0m  Limite de {$maxJobs} jobs atteinte.\n";
                break;
            }

            $jobData = $driver === 'redis'
                ? $this->fetchRedis($queue, $timeout)
                : $this->fetchDatabase($queue);

            if ($jobData === null) {
                if ($driver === 'database') {
                    sleep(1);
                }
                continue;
            }

            $this->processJob($jobData, $queue);
            $processed++;
        }
    }

    private function fetchRedis(string $queue, int $timeout): ?array
    {
        $redis = $this->getRedis();
        $prefix = Env::get('REDIS_PREFIX', 'app:');
        $key = $prefix . 'queue:' . $queue;

        $result = $redis->blPop([$key], $timeout);

        if (empty($result)) {
            return null;
        }

        return json_decode($result[1], true);
    }

    private function fetchDatabase(string $queue): ?array
    {
        $row = DB::raw(
            'SELECT * FROM jobs WHERE status = ? AND queue = ? ORDER BY id ASC LIMIT 1 FOR UPDATE SKIP LOCKED',
            ['pending', $queue]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        // Marquer comme processing
        DB::table('jobs')->where('id', $row['id'])->update(['status' => 'processing']);

        return [
            'job' => $row['job_class'],
            'payload' => json_decode($row['payload'], true) ?? [],
            'attempts' => (int) $row['attempts'],
            'db_id' => $row['id'],
        ];
    }

    private function processJob(array $jobData, string $queue): void
    {
        $jobClass = $jobData['job'] ?? '';
        $payload = $jobData['payload'] ?? [];
        $attempts = ($jobData['attempts'] ?? 0) + 1;
        $dbId = $jobData['db_id'] ?? null;

        echo "  \033[36m◉\033[0m  {$jobClass} (tentative #{$attempts})\n";

        try {
            $instance = $this->resolveJob($jobClass);
            $instance->handle($payload);

            echo "  \033[32m✓\033[0m  {$jobClass} — succes\n";

            // Supprimer du DB si driver database
            if ($dbId !== null) {
                DB::table('jobs')->where('id', $dbId)->delete();
            }
        } catch (\Throwable $e) {
            echo "  \033[31m✗\033[0m  {$jobClass} — erreur : {$e->getMessage()}\n";

            $instance = $this->resolveJob($jobClass);
            $maxRetries = $instance->retries();

            if ($attempts < $maxRetries) {
                echo "  \033[33m↻\033[0m  Re-queue (tentative {$attempts}/{$maxRetries})\n";
                $this->requeue($jobData, $attempts, $queue, $instance->retryDelay(), $dbId);
            } else {
                echo "  \033[31m☠\033[0m  {$jobClass} — echec definitif apres {$attempts} tentatives\n";
                $instance->failed($payload, $e);
                $this->failedHandler->store($queue, $jobClass, $payload, (string) $e);

                if ($dbId !== null) {
                    DB::table('jobs')->where('id', $dbId)->update(['status' => 'failed']);
                }
            }
        }
    }

    private function requeue(array $jobData, int $attempts, string $queue, int $delay, ?string $dbId): void
    {
        $driver = Env::get('QUEUE_DRIVER', 'redis');

        if ($driver === 'redis') {
            $data = json_encode([
                'job' => $jobData['job'],
                'payload' => $jobData['payload'],
                'attempts' => $attempts,
                'created_at' => $jobData['created_at'] ?? date('c'),
                'retry_after' => date('c', time() + $delay),
            ], JSON_THROW_ON_ERROR);

            $redis = $this->getRedis();
            $prefix = Env::get('REDIS_PREFIX', 'app:');

            // Simple RPUSH avec delai gere au traitement
            $redis->rPush($prefix . 'queue:' . $queue, $data);

            return;
        }

        if ($dbId !== null) {
            DB::table('jobs')->where('id', $dbId)->update([
                'attempts' => $attempts,
                'status' => 'pending',
                'available_at' => date('Y-m-d H:i:s', time() + $delay),
            ]);
        }
    }

    private function resolveJob(string $jobClass): JobInterface
    {
        if (!class_exists($jobClass)) {
            throw new \RuntimeException("Classe job introuvable : {$jobClass}");
        }

        try {
            $instance = Container::getInstance()->make($jobClass);
        } catch (\Throwable) {
            $instance = new $jobClass();
        }

        if (!$instance instanceof JobInterface) {
            throw new \RuntimeException("{$jobClass} doit implementer JobInterface.");
        }

        return $instance;
    }

    private function getRedis(): \Redis
    {
        if ($this->redis !== null) {
            return $this->redis;
        }

        if (!extension_loaded('redis')) {
            throw new \RuntimeException('Extension php-redis requise pour QUEUE_DRIVER=redis.');
        }

        $this->redis = new \Redis();
        $this->redis->connect(
            Env::get('REDIS_HOST', '127.0.0.1'),
            (int) Env::get('REDIS_PORT', '6379'),
        );

        $password = Env::get('REDIS_PASSWORD');
        if ($password !== '') {
            $this->redis->auth($password);
        }

        $db = (int) Env::get('REDIS_DB', '0');
        if ($db !== 0) {
            $this->redis->select($db);
        }

        return $this->redis;
    }
}
