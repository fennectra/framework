<?php

namespace Fennec\Core\Cache;

class RouteCache
{
    private const CACHE_KEY = 'routes';

    public function __construct(
        private FileCache $cache,
    ) {
    }

    /**
     * Compile les routes et les stocke en cache.
     */
    public function compile(array $routes): void
    {
        $compiled = [];

        foreach ($routes as $route) {
            $pattern = $this->convertToRegex($route['path']);
            $compiled[] = [
                'method' => $route['method'],
                'path' => $route['path'],
                'pattern' => $pattern,
                'controller' => $route['controller'],
                'action' => $route['action'],
                'middleware' => $route['middleware'],
            ];
        }

        $this->cache->set(self::CACHE_KEY, $compiled);
    }

    /**
     * Charge les routes compilées depuis le cache.
     */
    public function load(): ?array
    {
        return $this->cache->get(self::CACHE_KEY);
    }

    /**
     * Vérifie si le cache existe.
     */
    public function isCached(): bool
    {
        return $this->cache->has(self::CACHE_KEY);
    }

    /**
     * Invalide le cache des routes.
     */
    public function clear(): void
    {
        $this->cache->clear(self::CACHE_KEY);
    }

    private function convertToRegex(string $path): string
    {
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);

        return '#^' . $pattern . '$#';
    }
}
