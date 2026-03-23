<?php

namespace Fennec\Commands;

use Fennec\Attributes\Command;
use Fennec\Core\Cli\CommandInterface;
use Fennec\Core\Redis\RedisLock;
use Fennec\Core\Scheduler\Schedule;
use Fennec\Core\Scheduler\Scheduler;

#[Command('schedule:run', 'Run scheduled tasks')]
class ScheduleRunCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $projectRoot = FENNEC_BASE_PATH;
        $scheduleFile = $projectRoot . '/app/Schedule.php';

        if (!file_exists($scheduleFile)) {
            echo "\033[31m✗ Fichier app/Schedule.php introuvable.\033[0m\n";
            echo "  Creez-le pour definir vos taches planifiees.\n";

            return 1;
        }

        $schedule = new Schedule();
        $defineSchedule = require $scheduleFile;

        if (is_callable($defineSchedule)) {
            $defineSchedule($schedule);
        }

        // Redis lock optionnel (dev = sans lock)
        $lock = null;

        try {
            $lock = RedisLock::fromEnv();
        } catch (\Throwable) {
            echo "  \033[33m⚠\033[0m  Redis indisponible — mode sans verrou\n";
        }

        $scheduler = new Scheduler($lock);

        echo "\033[1;36m";
        echo "  ╔══════════════════════════════════════╗\n";
        echo "  ║   Scheduler — Execution des taches   ║\n";
        echo "  ╚══════════════════════════════════════╝\n";
        echo "\033[0m\n";

        $tasks = $schedule->getTasks();
        echo "  \033[90m" . count($tasks) . " tache(s) enregistree(s)\033[0m\n\n";

        $scheduler->tick($schedule);

        echo "\n  \033[32m✓ Scheduler termine.\033[0m\n";

        return 0;
    }
}
