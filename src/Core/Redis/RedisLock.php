<?php

namespace Fennec\Core\Redis;

use Fennec\Core\Env;

class RedisLock
{
    private ?\Redis $redis = null;
    private string $ownerToken;

    public function __construct(
        private ?string $host = null,
        private int $port = 6379,
        private ?string $password = null,
        private int $db = 0,
        private string $prefix = 'app:lock:',
    ) {
        $this->ownerToken = gethostname() . ':' . getmypid() . ':' . bin2hex(random_bytes(8));
    }

    public static function fromEnv(): self
    {
        return new self(
            host: Env::get('REDIS_HOST', '127.0.0.1'),
            port: (int) Env::get('REDIS_PORT', '6379'),
            password: Env::get('REDIS_PASSWORD') ?: null,
            db: (int) Env::get('REDIS_DB', '0'),
            prefix: Env::get('REDIS_PREFIX', 'app:') . 'lock:',
        );
    }

    /**
     * Acquiert un verrou avec TTL (SET NX EX atomique).
     */
    public function acquire(string $name, int $ttlSeconds): bool
    {
        $this->connect();

        $key = $this->prefix . $name;

        return (bool) $this->redis->set($key, $this->ownerToken, ['NX', 'EX' => $ttlSeconds]);
    }

    /**
     * Relache le verrou uniquement si on en est le proprietaire (Lua atomique).
     */
    public function release(string $name): bool
    {
        $this->connect();

        $key = $this->prefix . $name;

        $lua = <<<'LUA'
if redis.call("get", KEYS[1]) == ARGV[1] then
    return redis.call("del", KEYS[1])
else
    return 0
end
LUA;

        return (bool) $this->redis->eval($lua, [$key, $this->ownerToken], 1);
    }

    /**
     * Verifie si un verrou est actif.
     */
    public function isLocked(string $name): bool
    {
        $this->connect();

        return (bool) $this->redis->exists($this->prefix . $name);
    }

    private function connect(): void
    {
        // Health check : reconnecter si la connexion est morte
        if ($this->redis !== null) {
            try {
                $this->redis->ping();

                return;
            } catch (\Throwable) {
                $this->redis = null;
            }
        }

        if (!extension_loaded('redis')) {
            throw new \RuntimeException('Extension php-redis requise pour RedisLock.');
        }

        $this->redis = new \Redis();
        $this->redis->connect($this->host ?? '127.0.0.1', $this->port);

        if ($this->password !== null) {
            $this->redis->auth($this->password);
        }

        if ($this->db !== 0) {
            $this->redis->select($this->db);
        }
    }

    /**
     * Ferme la connexion Redis (cleanup worker).
     */
    public function disconnect(): void
    {
        if ($this->redis !== null) {
            try {
                $this->redis->close();
            } catch (\Throwable) {
                // Silencieux
            }
            $this->redis = null;
        }
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
