<?php

namespace Fennec\Core;

use Fennec\Core\Storage\GcsDriver;
use Fennec\Core\Storage\LocalDriver;
use Fennec\Core\Storage\S3Driver;
use Fennec\Core\Storage\StorageDriverInterface;

class Storage
{
    private static ?self $instance = null;
    private StorageDriverInterface $driver;

    public function __construct(StorageDriverInterface $driver)
    {
        $this->driver = $driver;
    }

    /**
     * Cree une instance Storage avec le driver configure par env.
     */
    public static function withDriver(string $driver = 'local'): self
    {
        return match ($driver) {
            's3' => new self(new S3Driver()),
            'gcs' => new self(new GcsDriver()),
            default => new self(new LocalDriver()),
        };
    }

    public static function setInstance(self $instance): void
    {
        self::$instance = $instance;
    }

    public static function getInstance(): ?self
    {
        if (self::$instance === null) {
            try {
                Container::getInstance()->get(self::class);
            } catch (\Throwable) {
                // Container non disponible
            }
        }

        return self::$instance;
    }

    // ── Facade statique ──

    public static function put(string $path, string $contents): bool
    {
        return self::getInstance()->driver->put($path, $contents);
    }

    public static function get(string $path): ?string
    {
        return self::getInstance()->driver->get($path);
    }

    public static function exists(string $path): bool
    {
        return self::getInstance()->driver->exists($path);
    }

    public static function delete(string $path): bool
    {
        return self::getInstance()->driver->delete($path);
    }

    public static function url(string $path): string
    {
        return self::getInstance()->driver->url($path);
    }

    public static function copy(string $from, string $to): bool
    {
        return self::getInstance()->driver->copy($from, $to);
    }

    public static function move(string $from, string $to): bool
    {
        return self::getInstance()->driver->move($from, $to);
    }

    public static function size(string $path): ?int
    {
        return self::getInstance()->driver->size($path);
    }

    public static function files(string $directory = ''): array
    {
        return self::getInstance()->driver->files($directory);
    }

    public static function absolutePath(string $path): ?string
    {
        return self::getInstance()->driver->absolutePath($path);
    }

    /**
     * Acces direct au driver (pour les methodes specifiques comme temporaryUrl sur S3).
     */
    public static function driver(): StorageDriverInterface
    {
        return self::getInstance()->driver;
    }
}
