<?php

namespace Fennec\Core\Storage;

use Fennec\Core\Env;

class LocalDriver implements StorageDriverInterface
{
    private string $root;
    private string $publicUrl;

    public function __construct(?string $root = null)
    {
        $this->root = $root ?? FENNEC_BASE_PATH . '/storage';
        $this->publicUrl = rtrim(Env::get('APP_URL', ''), '/') . '/storage';

        if (!is_dir($this->root)) {
            mkdir($this->root, 0775, true);
        }
    }

    public function put(string $path, string $contents): bool
    {
        $fullPath = $this->fullPath($path);
        $dir = dirname($fullPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return file_put_contents($fullPath, $contents, LOCK_EX) !== false;
    }

    public function get(string $path): ?string
    {
        $fullPath = $this->fullPath($path);

        if (!file_exists($fullPath)) {
            return null;
        }

        return file_get_contents($fullPath);
    }

    public function exists(string $path): bool
    {
        return file_exists($this->fullPath($path));
    }

    public function delete(string $path): bool
    {
        $fullPath = $this->fullPath($path);

        if (!file_exists($fullPath)) {
            return false;
        }

        return unlink($fullPath);
    }

    public function url(string $path): string
    {
        return $this->publicUrl . '/' . ltrim($path, '/');
    }

    public function copy(string $from, string $to): bool
    {
        $toPath = $this->fullPath($to);
        $dir = dirname($toPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return copy($this->fullPath($from), $toPath);
    }

    public function move(string $from, string $to): bool
    {
        $toPath = $this->fullPath($to);
        $dir = dirname($toPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return rename($this->fullPath($from), $toPath);
    }

    public function size(string $path): ?int
    {
        $fullPath = $this->fullPath($path);

        if (!file_exists($fullPath)) {
            return null;
        }

        return filesize($fullPath) ?: null;
    }

    public function files(string $directory = ''): array
    {
        $dir = $this->fullPath($directory);

        if (!is_dir($dir)) {
            return [];
        }

        $files = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $relativePath = str_replace([$this->root . '/', $this->root . '\\'], '', $file->getPathname());
            $files[] = str_replace('\\', '/', $relativePath);
        }

        return $files;
    }

    public function absolutePath(string $path): ?string
    {
        $fullPath = $this->fullPath($path);

        return file_exists($fullPath) ? $fullPath : null;
    }

    private function fullPath(string $path): string
    {
        return $this->root . '/' . ltrim($path, '/');
    }
}
