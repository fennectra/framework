<?php

namespace Fennec\Core\Event;

use Fennec\Core\Env;

/**
 * Broker Redis : publie les événements dans un canal Redis.
 *
 * Les listeners sync sont toujours exécutés immédiatement.
 * En plus, l'event est publié dans Redis pour les consumers externes.
 *
 * Nécessite l'extension php-redis.
 *
 * Variables d'environnement :
 *   REDIS_HOST     (défaut: 127.0.0.1)
 *   REDIS_PORT     (défaut: 6379)
 *   REDIS_PASSWORD (défaut: null)
 *   REDIS_DB       (défaut: 0, valeurs possibles: 0-15)
 *   REDIS_PREFIX   (défaut: app:events:)
 */
class RedisBroker implements EventBrokerInterface
{
    private ?\Redis $redis = null;
    private SyncBroker $sync;

    public function __construct(
        private string $host = '127.0.0.1',
        private int $port = 6379,
        private ?string $password = null,
        private int $db = 0,
        private string $prefix = 'app:events:',
    ) {
        $this->sync = new SyncBroker();
    }

    public static function fromEnv(): self
    {
        return new self(
            host: Env::get('REDIS_HOST', '127.0.0.1'),
            port: (int) Env::get('REDIS_PORT', '6379'),
            password: Env::get('REDIS_PASSWORD') ?: null,
            db: (int) Env::get('REDIS_DB', '0'),
            prefix: Env::get('REDIS_PREFIX', 'app:events:'),
        );
    }

    public function publish(string $eventName, mixed $payload): void
    {
        // 1. Exécuter les listeners sync locaux
        $this->sync->publish($eventName, $payload);

        // 2. Publier dans Redis pour les consumers async
        $message = json_encode([
            'event' => $eventName,
            'payload' => $this->serializePayload($payload),
            'timestamp' => date('c'),
        ], JSON_THROW_ON_ERROR);

        $this->connect();
        $this->redis->publish($this->prefix . $eventName, $message);

        // Aussi push dans une liste pour les workers qui LPOP
        $this->redis->rPush($this->prefix . 'queue', $message);
    }

    /**
     * Expose le broker sync interne pour enregistrer des listeners locaux.
     */
    public function sync(): SyncBroker
    {
        return $this->sync;
    }

    public function driver(): string
    {
        return 'redis';
    }

    private function connect(): void
    {
        if ($this->redis !== null) {
            return;
        }

        if (!extension_loaded('redis')) {
            throw new \RuntimeException('Extension php-redis requise pour EVENT_BROKER=redis. Installez-la avec: pecl install redis');
        }

        $this->redis = new \Redis();
        $this->redis->connect($this->host, $this->port);

        if ($this->password !== null) {
            $this->redis->auth($this->password);
        }

        if ($this->db !== 0) {
            $this->redis->select($this->db);
        }
    }

    private function serializePayload(mixed $payload): mixed
    {
        if (is_object($payload)) {
            if (method_exists($payload, 'toArray')) {
                return $payload->toArray();
            }
            if (method_exists($payload, 'jsonSerialize')) {
                return $payload->jsonSerialize();
            }

            return (array) $payload;
        }

        return $payload;
    }
}
