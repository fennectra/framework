<?php

namespace Fennec\Core\Scheduler;

use Fennec\Core\Redis\RedisLock;

class Scheduler
{
    private ?int $lastTick = null;

    public function __construct(
        private ?RedisLock $lock = null,
    ) {
    }

    /**
     * Execute les taches dues du schedule.
     * Throttle : minimum 60 secondes entre deux ticks.
     */
    public function tick(Schedule $schedule): void
    {
        $now = time();

        if ($this->lastTick !== null && ($now - $this->lastTick) < 60) {
            return;
        }

        $this->lastTick = $now;
        $dateTime = new \DateTimeImmutable('@' . $now);

        foreach ($schedule->getTasks() as $task) {
            if (!$task->isDue($dateTime)) {
                continue;
            }

            $taskName = $task->getName();
            $locked = false;

            // Verrou anti-overlap
            if ($task->isWithoutOverlapping() && $this->lock !== null) {
                if (!$this->lock->acquire('scheduler:' . $taskName, $task->getTtl())) {
                    echo "  \033[33m⏭\033[0m  {$taskName} — deja en cours (locked)\n";
                    continue;
                }
                $locked = true;
            }

            try {
                echo "  \033[32m▶\033[0m  {$taskName}\n";
                $this->executeTask($task);
                echo "  \033[32m✓\033[0m  {$taskName} — termine\n";
            } catch (\Throwable $e) {
                echo "  \033[31m✗\033[0m  {$taskName} — erreur : {$e->getMessage()}\n";
            } finally {
                if ($locked && $this->lock !== null) {
                    $this->lock->release('scheduler:' . $taskName);
                }
            }
        }
    }

    private function executeTask(ScheduledTask $task): void
    {
        $callback = $task->getCallback();

        // Commande CLI
        if (is_array($callback) && ($callback[0] ?? null) === 'command') {
            $commandName = $callback[1] ?? '';
            $fennecRoot = FENNEC_BASE_PATH;
            $bin = $fennecRoot . '/bin/cli';
            passthru(PHP_BINARY . ' ' . escapeshellarg($bin) . ' ' . escapeshellarg($commandName));

            return;
        }

        // Callable classique
        if (is_callable($callback)) {
            $callback();

            return;
        }

        // [class, method]
        if (is_array($callback) && count($callback) === 2) {
            [$class, $method] = $callback;
            $instance = new $class();
            $instance->$method();
        }
    }
}
