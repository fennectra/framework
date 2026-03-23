<?php

namespace Fennec\Core\Cache;

class FileCache
{
    private string $cacheDir;

    public function __construct(?string $cacheDir = null)
    {
        $this->cacheDir = $cacheDir ?? FENNEC_BASE_PATH . '/var/cache';
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0775, true);
        }
    }

    /**
     * Récupère une valeur du cache.
     */
    public function get(string $key): mixed
    {
        $file = $this->path($key);
        if (!file_exists($file)) {
            return null;
        }

        return require $file;
    }

    /**
     * Stocke une valeur dans le cache.
     */
    public function set(string $key, mixed $data): void
    {
        $file = $this->path($key);
        $content = '<?php return ' . var_export($data, true) . ";\n";
        file_put_contents($file, $content, LOCK_EX);

        // Invalider l'opcache si actif
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }
    }

    /**
     * Vérifie si une clé existe dans le cache.
     */
    public function has(string $key): bool
    {
        return file_exists($this->path($key));
    }

    /**
     * Supprime une clé du cache, ou tout le cache si $key est null.
     */
    public function clear(?string $key = null): void
    {
        if ($key !== null) {
            $file = $this->path($key);
            if (file_exists($file)) {
                @unlink($file);
            }

            return;
        }

        // Supprimer tous les fichiers de cache
        $files = glob($this->cacheDir . '/*.php');
        foreach ($files as $file) {
            @unlink($file);
        }
    }

    private function path(string $key): string
    {
        return $this->cacheDir . '/' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $key) . '.php';
    }
}
