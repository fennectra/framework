<?php

namespace Fennec\Core\Profiler;

class Profiler
{
    private static ?self $instance = null;
    private ?ProfileEntry $current = null;

    public function __construct(
        private ProfilerStorageInterface $storage,
        private bool $enabled = true,
    ) {
    }

    public static function getInstance(): ?self
    {
        return self::$instance;
    }

    public static function setInstance(self $profiler): void
    {
        self::$instance = $profiler;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function start(string $method, string $uri): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->current = new ProfileEntry($method, $uri);
    }

    public function stop(): ?ProfileEntry
    {
        if (!$this->enabled || $this->current === null) {
            return null;
        }

        $entry = $this->current;
        $entry->stop();

        // Detection N+1
        $this->detectN1($entry);

        $this->storage->store($entry);
        $this->current = null;

        return $entry;
    }

    public function addQuery(string $sql, array $params, float $durationMs): void
    {
        if ($this->current === null) {
            return;
        }

        $this->current->queries[] = [
            'sql' => $sql,
            'params' => $params,
            'ms' => round($durationMs, 2),
        ];
    }

    public function addEvent(string $name, float $durationMs = 0): void
    {
        if ($this->current === null) {
            return;
        }

        $this->current->events[] = [
            'name' => $name,
            'ms' => round($durationMs, 2),
        ];
    }

    public function addMiddleware(string $class, float $durationMs): void
    {
        if ($this->current === null) {
            return;
        }

        $this->current->middlewares[] = [
            'class' => $class,
            'ms' => round($durationMs, 2),
        ];
    }

    public function addResolution(string $class): void
    {
        if ($this->current === null) {
            return;
        }

        $this->current->resolutions[] = $class;
    }

    /** @return ProfileEntry[] */
    public function getAll(): array
    {
        return $this->storage->getAll();
    }

    public function getById(string $id): ?ProfileEntry
    {
        return $this->storage->getById($id);
    }

    private function detectN1(ProfileEntry $entry): void
    {
        $patterns = [];
        foreach ($entry->queries as $q) {
            $pattern = preg_replace('/= :[\w]+/', '= ?', $q['sql']);
            $pattern = preg_replace('/IN \([^)]+\)/', 'IN (?)', $pattern);
            $patterns[$pattern] = ($patterns[$pattern] ?? 0) + 1;
        }

        foreach ($patterns as $pattern => $count) {
            if ($count > 3) {
                $entry->n1Warnings[] = "Query executed {$count}x: {$pattern}";
            }
        }
    }
}
