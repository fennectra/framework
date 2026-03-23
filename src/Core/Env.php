<?php

namespace Fennec\Core;

class Env
{
    private static array $vars = [];
    private static bool $loaded = false;

    public static function load(?string $path = null): void
    {
        if (self::$loaded) {
            return;
        }

        $envFile = $path ?? FENNEC_BASE_PATH . '/.env';
        if (!file_exists($envFile)) {
            self::$loaded = true;

            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            self::$vars[trim($key)] = trim(trim($value), '"\'');
        }

        self::$loaded = true;
    }

    public static function get(string $key, string $default = ''): string
    {
        self::load();

        // Les variables d'environnement système priment sur le .env fichier
        // (important pour Docker, K8s, CI/CD)
        $env = getenv($key);
        if ($env !== false && $env !== '') {
            return $env;
        }

        return self::$vars[$key] ?? $default;
    }
}
