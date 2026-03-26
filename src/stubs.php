<?php

/**
 * Stubs for external extensions and runtime constants.
 * This file is never executed — it's only used by IDE (Intelephense) and PHPStan.
 */

/** Project root path, defined at runtime by forge/index.php */
define('FENNEC_BASE_PATH', __DIR__ . '/..');

if (false) {
    /**
     * FrankenPHP : gère une requête HTTP en mode worker.
     * Fournie par l'extension FrankenPHP au runtime.
     *
     * @param callable $handler Callback exécuté pour chaque requête
     * @return bool true si une nouvelle requête est disponible, false pour arrêter
     * @see https://frankenphp.dev/docs/worker/
     */
    function frankenphp_handle_request(callable $handler): bool
    {
        return false;
    }
}

/**
 * Stub pour ext-redis.
 * Ce bloc n'est jamais exécuté — il sert uniquement à PHPStan/IDE.
 */
if (false) {
    class Redis
    {
        public const OPT_PREFIX = 2;
        public const OPT_READ_TIMEOUT = 3;
        public const OPT_SERIALIZER = 1;
        public const SERIALIZER_PHP = 1;
        public const SERIALIZER_JSON = 4;
        public const MULTI = 1;
        public const PIPELINE = 2;

        public function connect(string $host, int $port = 6379, float $timeout = 0): bool { return true; }
        public function pconnect(string $host, int $port = 6379, float $timeout = 0): bool { return true; }
        public function auth(mixed $credentials): bool { return true; }
        public function select(int $db): bool { return true; }
        public function ping(string $message = ''): string|bool { return true; }
        public function get(string $key): string|false { return ''; }
        public function set(string $key, mixed $value, mixed $options = null): bool { return true; }
        public function del(array|string ...$keys): int { return 0; }
        public function exists(string ...$keys): int|bool { return 0; }
        public function expire(string $key, int $timeout): bool { return true; }
        public function ttl(string $key): int|false { return 0; }
        public function incr(string $key): int { return 0; }
        public function decr(string $key): int { return 0; }
        public function lpush(string $key, string ...$values): int { return 0; }
        public function rpush(string $key, string ...$values): int { return 0; }
        public function lpop(string $key): string|false { return ''; }
        public function rpop(string $key): string|false { return ''; }
        public function blPop(array|string $keys, float|int $timeout = 0): array|false|null { return []; }
        public function lrange(string $key, int $start, int $end): array { return []; }
        public function llen(string $key): int { return 0; }
        public function publish(string $channel, string $message): int { return 0; }
        public function subscribe(array $channels, callable $callback): mixed { return null; }
        public function eval(string $script, array $args = [], int $numKeys = 0): mixed { return null; }
        public function setex(string $key, int $expire, mixed $value): bool { return true; }
        public function setnx(string $key, mixed $value): bool { return true; }
        public function close(): bool { return true; }
        public function disconnect(): bool { return true; }
        public function keys(string $pattern): array { return []; }
        public function scan(?int &$cursor, ?string $pattern = null, int $count = 0): array|false { return []; }
        public function multi(int $mode = 1): self { return $this; }
        public function pipeline(): self { return $this; }
        public function exec(): array|false { return []; }
        public function getOption(int $option): mixed { return null; }
        public function setOption(int $option, mixed $value): bool { return true; }
        public function sAdd(string $key, mixed ...$members): int|false { return 0; }
        public function sRem(string $key, mixed ...$members): int { return 0; }
        public function sMembers(string $key): array { return []; }
        public function info(?string $section = null): array { return []; }
        public function dbSize(): int { return 0; }
        public function flushDB(bool $async = false): bool { return true; }
        public function xAdd(string $key, string $id, array $messages, int $maxLen = 0, bool $approximate = false): string|false { return ''; }
        public function xTrim(string $key, int $maxLen, bool $approximate = false): int { return 0; }
        public function xRead(array $streams, int $count = -1, int $block = -1): array|false { return []; }
    }
}
