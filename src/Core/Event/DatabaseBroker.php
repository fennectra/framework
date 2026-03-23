<?php

namespace Fennec\Core\Event;

use Fennec\Core\DB;

/**
 * Broker Database : persiste les événements dans une table `events`.
 *
 * Les listeners sync sont exécutés immédiatement.
 * L'event est aussi inséré dans la table pour traitement async par un worker.
 *
 * Table requise :
 *   CREATE TABLE events (
 *       id SERIAL PRIMARY KEY,
 *       event VARCHAR(255) NOT NULL,
 *       payload JSONB,
 *       status VARCHAR(20) DEFAULT 'pending',
 *       created_at TIMESTAMP DEFAULT NOW(),
 *       processed_at TIMESTAMP NULL
 *   );
 *
 * Variables d'environnement :
 *   EVENT_DB_CONNECTION  (défaut: default)
 *   EVENT_DB_TABLE       (défaut: events)
 */
class DatabaseBroker implements EventBrokerInterface
{
    private SyncBroker $sync;

    public function __construct(
        private string $connection = 'default',
        private string $table = 'events',
    ) {
        $this->sync = new SyncBroker();
    }

    public static function fromEnv(): self
    {
        return new self(
            connection: \Fennec\Core\Env::get('EVENT_DB_CONNECTION', 'default'),
            table: \Fennec\Core\Env::get('EVENT_DB_TABLE', 'events'),
        );
    }

    public function publish(string $eventName, mixed $payload): void
    {
        // 1. Exécuter les listeners sync locaux
        $this->sync->publish($eventName, $payload);

        // 2. Persister dans la table events
        DB::table($this->table, $this->connection)->insert([
            'event' => $eventName,
            'payload' => json_encode($this->serializePayload($payload)),
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Récupère les événements pending pour un worker.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchPending(int $limit = 10): array
    {
        return DB::table($this->table, $this->connection)
            ->where('status', 'pending')
            ->orderBy('id', 'ASC')
            ->limit($limit)
            ->get();
    }

    /**
     * Marque un événement comme traité.
     */
    public function markProcessed(int $eventId): void
    {
        DB::table($this->table, $this->connection)
            ->where('id', $eventId)
            ->update([
                'status' => 'processed',
                'processed_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Marque un événement comme échoué.
     */
    public function markFailed(int $eventId): void
    {
        DB::table($this->table, $this->connection)
            ->where('id', $eventId)
            ->update(['status' => 'failed']);
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
        return 'database';
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
