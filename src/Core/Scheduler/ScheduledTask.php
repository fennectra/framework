<?php

namespace Fennec\Core\Scheduler;

class ScheduledTask
{
    private string $name = '';
    private string $cronExpression = '* * * * *';
    private mixed $callback;
    private bool $withoutOverlapping = false;
    private int $ttl = 1800;

    public function __construct(mixed $callback)
    {
        $this->callback = $callback;
    }

    // ─── Fluent setters ──────────────────────────────────────

    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function cron(string $expression): static
    {
        $this->cronExpression = $expression;

        return $this;
    }

    public function everyMinute(): static
    {
        return $this->cron('* * * * *');
    }

    public function everyFiveMinutes(): static
    {
        return $this->cron('*/5 * * * *');
    }

    public function everyTenMinutes(): static
    {
        return $this->cron('*/10 * * * *');
    }

    public function everyFifteenMinutes(): static
    {
        return $this->cron('*/15 * * * *');
    }

    public function everyThirtyMinutes(): static
    {
        return $this->cron('*/30 * * * *');
    }

    public function hourly(): static
    {
        return $this->cron('0 * * * *');
    }

    public function daily(): static
    {
        return $this->cron('0 0 * * *');
    }

    public function dailyAt(string $time): static
    {
        $parts = explode(':', $time);
        $hour = (int) $parts[0];
        $minute = (int) ($parts[1] ?? '0');

        return $this->cron("{$minute} {$hour} * * *");
    }

    public function weekly(): static
    {
        return $this->cron('0 0 * * 0');
    }

    public function weekdays(): static
    {
        return $this->cron('0 0 * * 1-5');
    }

    public function monthly(): static
    {
        return $this->cron('0 0 1 * *');
    }

    public function withoutOverlapping(int $ttlSeconds = 1800): static
    {
        $this->withoutOverlapping = true;
        $this->ttl = $ttlSeconds;

        return $this;
    }

    // ─── Getters ─────────────────────────────────────────────

    public function getName(): string
    {
        if ($this->name !== '') {
            return $this->name;
        }

        if (is_array($this->callback)) {
            return implode('::', $this->callback);
        }

        return 'task:' . md5($this->cronExpression . spl_object_id($this));
    }

    public function getCronExpression(): string
    {
        return $this->cronExpression;
    }

    public function getCallback(): mixed
    {
        return $this->callback;
    }

    public function isWithoutOverlapping(): bool
    {
        return $this->withoutOverlapping;
    }

    public function getTtl(): int
    {
        return $this->ttl;
    }

    /**
     * Verifie si la tache est due pour le moment donne.
     */
    public function isDue(\DateTimeInterface $now): bool
    {
        return CronExpression::isDue($this->cronExpression, $now);
    }
}
