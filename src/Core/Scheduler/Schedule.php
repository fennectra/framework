<?php

namespace Fennec\Core\Scheduler;

class Schedule
{
    /** @var ScheduledTask[] */
    private array $tasks = [];

    /**
     * Planifie un callback.
     */
    public function call(callable $callback): ScheduledTask
    {
        $task = new ScheduledTask($callback);
        $this->tasks[] = $task;

        return $task;
    }

    /**
     * Planifie une commande CLI par son nom.
     */
    public function command(string $commandName): ScheduledTask
    {
        $task = new ScheduledTask(['command', $commandName]);
        $task->name('command:' . $commandName);
        $this->tasks[] = $task;

        return $task;
    }

    /**
     * @return ScheduledTask[]
     */
    public function getTasks(): array
    {
        return $this->tasks;
    }
}
