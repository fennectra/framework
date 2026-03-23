<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;
use Fennec\Core\DB;
use Fennec\Core\Queue\FailedJobHandler;

#[Command('queue:retry', 'Retry failed jobs [--id=N] [--all]')]
class QueueRetryCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $handler = new FailedJobHandler();
        $id = $args['id'] ?? null;
        $all = isset($args['all']);

        if ($id !== null) {
            try {
                $handler->retry((int) $id);
                echo "\033[32m✓ Job #{$id} re-dispatch dans la queue.\033[0m\n";
            } catch (\Throwable $e) {
                echo "\033[31m✗ {$e->getMessage()}\033[0m\n";

                return 1;
            }

            return 0;
        }

        if ($all) {
            $rows = DB::table('failed_jobs')->get();
            $count = 0;

            foreach ($rows as $row) {
                try {
                    $handler->retry((int) $row['id']);
                    $count++;
                } catch (\Throwable $e) {
                    echo "\033[31m✗ Job #{$row['id']} — {$e->getMessage()}\033[0m\n";
                }
            }

            echo "\033[32m✓ {$count} job(s) re-dispatch.\033[0m\n";

            return 0;
        }

        // Liste les failed jobs
        $rows = DB::table('failed_jobs')->orderBy('failed_at', 'DESC')->get();

        if (empty($rows)) {
            echo "\033[32m✓ Aucun job echoue.\033[0m\n";

            return 0;
        }

        echo "\033[1mJobs echoues :\033[0m\n\n";
        echo str_pad('ID', 6) . str_pad('Queue', 15) . str_pad('Job', 40) . "Date\n";
        echo str_repeat('─', 80) . "\n";

        foreach ($rows as $row) {
            echo str_pad($row['id'], 6)
                . str_pad($row['queue'], 15)
                . str_pad($row['job_class'], 40)
                . ($row['failed_at'] ?? '') . "\n";
        }

        echo "\n  \033[33mUsage :\033[0m php bin/cli queue:retry --id=1\n";
        echo "          php bin/cli queue:retry --all\n";

        return 0;
    }
}
