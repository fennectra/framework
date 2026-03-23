<?php

namespace Fennec\Core\Broadcasting;

use Fennec\Core\Env;

/**
 * Controleur SSE via Redis Streams (XREAD BLOCK).
 *
 * Contrairement a PUB/SUB :
 *   - XREAD BLOCK ne casse pas la connexion au timeout (pas de reconnexion)
 *   - Les messages sont persistes (reprise via Last-Event-ID)
 *   - Les IDs Redis Stream sont directement utilises comme SSE id:
 *
 * Query params :
 *   ?channels=chat,orders  — filtre les canaux a ecouter
 */
class SseController
{
    private const STREAM_KEY = 'sse:broadcast';
    private const BLOCK_MS = 2000;
    private const HEARTBEAT_EVERY = 5;

    /**
     * Demarre le flux SSE.
     */
    public function stream(): void
    {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        $channelFilter = $this->parseChannels();

        try {
            $redis = $this->createRedisConnection();
        } catch (\Throwable $e) {
            echo 'data: ' . json_encode(['error' => 'Redis unavailable: ' . $e->getMessage()]) . "\n\n";
            flush();

            return;
        }

        // Reprise : Last-Event-ID envoye par le navigateur apres reconnexion
        $lastId = $_SERVER['HTTP_LAST_EVENT_ID'] ?? '$';
        if ($lastId === '') {
            $lastId = '$';
        }

        // Connexion immediate
        echo "retry: 3000\n";
        echo ": connected\n\n";
        flush();

        // Boucle non-bloquante : XREAD BLOCK 2s, heartbeat toutes les 5 iterations vides
        $emptyCount = 0;

        try {
            while (!connection_aborted()) {
                /** @var array<string, array<string, array<string, string>>>|false $results */
                $results = $redis->xRead([self::STREAM_KEY => $lastId], 10, self::BLOCK_MS);

                if ($results && isset($results[self::STREAM_KEY])) {
                    foreach ($results[self::STREAM_KEY] as $id => $fields) {
                        // Filtrer par canal si demande
                        if (!empty($channelFilter) && !in_array($fields['channel'] ?? '', $channelFilter, true)) {
                            $lastId = $id;
                            continue;
                        }

                        $eventType = $fields['event'] ?? $fields['channel'] ?? 'message';

                        echo 'id: ' . $id . "\n";
                        echo 'event: ' . $eventType . "\n";
                        echo 'data: ' . ($fields['payload'] ?? '{}') . "\n\n";
                        flush();

                        $lastId = $id;
                    }
                    $emptyCount = 0;
                } else {
                    $emptyCount++;
                    // Heartbeat toutes les ~10s (5 iterations x 2s block)
                    if ($emptyCount >= self::HEARTBEAT_EVERY) {
                        echo ": heartbeat\n\n";
                        flush();
                        $emptyCount = 0;
                    }
                }
            }
        } finally {
            $redis->close();
        }
    }

    /**
     * @return string[]
     */
    private function parseChannels(): array
    {
        $raw = $_GET['channels'] ?? '';
        if ($raw === '') {
            return [];
        }

        return array_filter(array_map('trim', explode(',', $raw)));
    }

    private function createRedisConnection(): \Redis
    {
        if (!extension_loaded('redis')) {
            throw new \RuntimeException('Extension php-redis requise pour le broadcasting SSE');
        }

        $redis = new \Redis();
        $redis->connect(
            Env::get('REDIS_HOST', '127.0.0.1'),
            (int) Env::get('REDIS_PORT', '6379')
        );

        $password = Env::get('REDIS_PASSWORD', '');
        if ($password !== '') {
            $redis->auth($password);
        }

        $db = (int) Env::get('REDIS_DB', '0');
        if ($db !== 0) {
            $redis->select($db);
        }

        return $redis;
    }
}
