<?php

namespace Fennec\Core\Database;

class DriverFactory
{
    /** @var array<string, class-string<DatabaseDriverInterface>> */
    private static array $drivers = [
        'pgsql' => PostgreSQLDriver::class,
        'mysql' => MySQLDriver::class,
        'sqlite' => SQLiteDriver::class,
    ];

    /**
     * Create a driver instance from its name.
     */
    public static function make(string $driver): DatabaseDriverInterface
    {
        if (!isset(self::$drivers[$driver])) {
            throw new \InvalidArgumentException(
                "Unsupported database driver: {$driver}. Supported: " . implode(', ', array_keys(self::$drivers))
            );
        }

        $class = self::$drivers[$driver];

        return new $class();
    }

    /**
     * Register a custom driver.
     *
     * @param class-string<DatabaseDriverInterface> $class
     */
    public static function register(string $name, string $class): void
    {
        self::$drivers[$name] = $class;
    }

    /**
     * Return supported driver names.
     *
     * @return list<string>
     */
    public static function supported(): array
    {
        return array_keys(self::$drivers);
    }
}
