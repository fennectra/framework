<?php

namespace Fennec\Core\Ui;

use Fennec\Core\Env;
use Fennec\Core\Response;

class UiSchedulerController
{
    public function tasks(): void
    {
        $tasks = [];

        // Load the app schedule if it exists
        $schedulePath = (defined('FENNEC_BASE_PATH') ? FENNEC_BASE_PATH : '') . '/app/Schedule.php';

        if (file_exists($schedulePath)) {
            $schedule = new \Fennec\Core\Scheduler\Schedule();
            require $schedulePath;

            foreach ($schedule->getTasks() as $task) {
                $callback = $task->getCallback();
                $type = 'callback';
                $target = 'closure';

                if (is_array($callback)) {
                    $type = 'method';
                    $target = is_object($callback[0])
                        ? get_class($callback[0]) . '::' . $callback[1]
                        : $callback[0] . '::' . $callback[1];
                } elseif (is_string($callback)) {
                    $type = 'command';
                    $target = $callback;
                }

                $tasks[] = [
                    'name' => $task->getName(),
                    'cron' => $task->getCronExpression(),
                    'type' => $type,
                    'target' => $target,
                    'withoutOverlapping' => $task->isWithoutOverlapping(),
                    'ttl' => $task->getTtl(),
                    'isDue' => $task->isDue(new \DateTimeImmutable()),
                ];
            }
        }

        Response::json([
            'enabled' => (bool) Env::get('SCHEDULER_ENABLED'),
            'tasks' => $tasks,
        ]);
    }
}
