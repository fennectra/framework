<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;
use Fennec\Core\Queue\QueueWorker;

#[Command('queue:work', 'Process queue jobs [--queue=default] [--max-jobs=0] [--timeout=60]')]
class QueueWorkCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $queue = $args['queue'] ?? 'default';
        $maxJobs = (int) ($args['max-jobs'] ?? 0);
        $timeout = (int) ($args['timeout'] ?? 60);

        echo "\033[1;36m";
        echo "  ╔══════════════════════════════════════╗\n";
        echo "  ║   Queue Worker                        ║\n";
        echo "  ╚══════════════════════════════════════╝\n";
        echo "\033[0m\n";
        echo "  Queue:    {$queue}\n";
        echo '  Max jobs: ' . ($maxJobs === 0 ? 'illimite' : $maxJobs) . "\n";
        echo "  Timeout:  {$timeout}s\n\n";

        $worker = new QueueWorker();
        $worker->work($queue, $maxJobs, $timeout);

        return 0;
    }
}
