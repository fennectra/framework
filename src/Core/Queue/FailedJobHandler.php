<?php

namespace Fennec\Core\Queue;

use Fennec\Core\DB;

class FailedJobHandler
{
    /**
     * Stocke un job echoue dans la table failed_jobs.
     */
    public function store(string $queue, string $jobClass, array $payload, string $exception): void
    {
        DB::table('failed_jobs')->insert([
            'queue' => $queue,
            'job_class' => $jobClass,
            'payload' => json_encode($payload),
            'exception' => mb_substr($exception, 0, 10000),
            'failed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Re-dispatch un job echoue dans sa queue.
     */
    public function retry(int $id): void
    {
        $row = DB::table('failed_jobs')->where('id', $id)->first();

        if (!$row) {
            throw new \RuntimeException("Failed job #{$id} introuvable.");
        }

        $payload = json_decode($row['payload'], true) ?? [];
        Job::dispatch($row['job_class'], $payload, $row['queue']);

        DB::table('failed_jobs')->where('id', $id)->delete();
    }

    /**
     * Supprime tous les jobs echoues.
     */
    public function flush(): void
    {
        DB::raw('DELETE FROM failed_jobs');
    }
}
