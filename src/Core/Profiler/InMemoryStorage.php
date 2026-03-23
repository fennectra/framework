<?php

namespace Fennec\Core\Profiler;

class InMemoryStorage implements ProfilerStorageInterface
{
    /** @var ProfileEntry[] Ring buffer (persiste dans le worker via static) */
    private static array $profiles = [];
    private int $maxSize;

    public function __construct(int $maxSize = 50)
    {
        $this->maxSize = $maxSize;
    }

    public function store(ProfileEntry $entry): void
    {
        self::$profiles[] = $entry;

        // Eviction O(1) amorti : couper le debut du tableau quand il depasse la limite
        if (count(self::$profiles) > $this->maxSize) {
            self::$profiles = array_slice(self::$profiles, -$this->maxSize);
        }
    }

    public function getAll(): array
    {
        return array_reverse(self::$profiles);
    }

    public function getById(string $id): ?ProfileEntry
    {
        foreach (self::$profiles as $entry) {
            if ($entry->id === $id) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Vide le ring buffer (appeler entre les requetes worker si besoin).
     */
    public static function clear(): void
    {
        self::$profiles = [];
    }

    /**
     * Retourne le nombre d'entrees stockees.
     */
    public static function count(): int
    {
        return count(self::$profiles);
    }
}
