<?php

namespace Fennec\Core\Broadcasting;

use Fennec\Core\Redis\RedisConnection;

/**
 * Publie des evenements via Redis Streams pour le broadcasting SSE.
 *
 * Utilise XADD sur le stream `sse:broadcast` avec trim automatique.
 * Avantages vs PUB/SUB :
 *   - Messages persistes (pas perdus si aucun listener)
 *   - Last-Event-ID natif (reprise apres deconnexion)
 *   - XREAD BLOCK ne casse pas la connexion au timeout
 */
class Broadcaster
{
    private const STREAM_KEY = 'sse:broadcast';
    private const MAX_STREAM_LENGTH = 1000;

    public function __construct(
        private RedisConnection $redis,
    ) {
    }

    /**
     * Diffuse un evenement sur un canal.
     *
     * @param string               $channel Nom du canal (ex: 'chat', 'orders')
     * @param string               $event   Nom de l'evenement (ex: 'message.new')
     * @param array<string, mixed> $data    Donnees de l'evenement
     */
    public function broadcast(string $channel, string $event, array $data): void
    {
        $message = json_encode([
            'channel' => $channel,
            'event' => $event,
            'data' => $data,
            'timestamp' => date('c'),
        ], JSON_THROW_ON_ERROR);

        $this->redis->connection()->xAdd(
            self::STREAM_KEY,
            '*',
            ['channel' => $channel, 'event' => $event, 'payload' => $message]
        );

        // Trim pour eviter que le stream grossisse indefiniment (~1000 entries)
        $this->redis->connection()->xTrim(self::STREAM_KEY, self::MAX_STREAM_LENGTH, true);
    }
}
