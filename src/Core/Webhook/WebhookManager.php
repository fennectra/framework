<?php

namespace Fennec\Core\Webhook;

use Fennec\Core\Container;
use Fennec\Core\DB;
use Fennec\Core\Event;
use Fennec\Core\Logger;
use Fennec\Core\Queue\Job;

/**
 * Gestionnaire de webhooks sortants.
 *
 * Ecoute les events internes et dispatche des requetes HTTP
 * vers les URLs enregistrees, avec signature HMAC-SHA256.
 */
class WebhookManager
{
    private static ?self $instance = null;

    /** @var array<string, array<int, array{id: int, url: string, secret: string}>> */
    private array $cache = [];

    /** Taille max du cache LRU (nombre d'event types distincts). */
    private int $maxCacheSize = 100;

    private bool $booted = false;

    public static function setInstance(self $instance): void
    {
        self::$instance = $instance;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            try {
                Container::getInstance()->get(self::class);
            } catch (\Throwable) {
                // Container non disponible
            }

            if (self::$instance === null) {
                throw new \RuntimeException('WebhookManager not initialized');
            }
        }

        return self::$instance;
    }

    /**
     * Enregistre les listeners pour tous les webhooks actifs.
     *
     * Protege contre les appels multiples en mode worker.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        // On ecoute tous les events — le filtrage se fait au dispatch
        Event::listen('*', function (mixed $payload, string $eventName) {
            $this->handleEvent($eventName, $payload);
        });
    }

    /**
     * Dispatche un event vers les webhooks concernes.
     */
    public function handleEvent(string $eventName, mixed $payload): void
    {
        $webhooks = $this->getWebhooksForEvent($eventName);

        foreach ($webhooks as $webhook) {
            Job::dispatch(WebhookDeliveryJob::class, [
                'webhook_id' => $webhook['id'],
                'url' => $webhook['url'],
                'secret' => $webhook['secret'],
                'event' => $eventName,
                'payload' => is_array($payload) ? $payload : ['data' => $payload],
            ], 'webhooks');
        }
    }

    /**
     * Dispatche un webhook de maniere synchrone (sans queue).
     */
    public static function dispatch(string $event, array $payload): void
    {
        self::getInstance()->handleEvent($event, $payload);
    }

    /**
     * Envoie une requete HTTP POST signee vers l'URL du webhook.
     *
     * @return array{status: int, body: string, success: bool}
     */
    public static function send(string $url, array $payload, string $secret, string $event): array
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = self::sign($json, $secret, $timestamp);

        $headers = [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json),
            'X-Webhook-Event: ' . $event,
            'X-Webhook-Signature: ' . $signature,
            'X-Webhook-Timestamp: ' . $timestamp,
            'User-Agent: Fennec-Webhook/1.0',
        ];

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $json,
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        $statusCode = 0;
        if (isset($http_response_header[0])) {
            preg_match('/HTTP\/\S+\s+(\d+)/', $http_response_header[0], $matches);
            $statusCode = (int) ($matches[1] ?? 0);
        }

        return [
            'status' => $statusCode,
            'body' => $body !== false ? $body : '',
            'success' => $statusCode >= 200 && $statusCode < 300,
        ];
    }

    /**
     * Genere la signature HMAC-SHA256.
     */
    public static function sign(string $payload, string $secret, int $timestamp): string
    {
        $message = $timestamp . '.' . $payload;

        return 'sha256=' . hash_hmac('sha256', $message, $secret);
    }

    /**
     * Verifie une signature de webhook.
     */
    public static function verify(string $payload, string $secret, string $signature, int $timestamp): bool
    {
        // Rejeter les requetes trop anciennes (5 minutes)
        if (abs(time() - $timestamp) > 300) {
            return false;
        }

        $expected = self::sign($payload, $secret, $timestamp);

        return hash_equals($expected, $signature);
    }

    /**
     * Recupere les webhooks actifs pour un event donne.
     *
     * Utilise un cache LRU borne a $maxCacheSize entrees pour eviter
     * les fuites memoire en mode worker.
     *
     * @return array<int, array{id: int, url: string, secret: string}>
     */
    private function getWebhooksForEvent(string $eventName): array
    {
        if (isset($this->cache[$eventName])) {
            // LRU : deplacer l'entree en fin de tableau (plus recente)
            $value = $this->cache[$eventName];
            unset($this->cache[$eventName]);
            $this->cache[$eventName] = $value;

            return $value;
        }

        try {
            $rows = DB::table('webhooks')
                ->where('is_active', true)
                ->get();
        } catch (\Throwable $e) {
            Logger::error('Failed to fetch webhooks', ['error' => $e->getMessage()]);

            return [];
        }

        $matched = [];
        foreach ($rows as $row) {
            $events = json_decode($row['events'] ?? '[]', true) ?: [];
            if (in_array('*', $events, true) || in_array($eventName, $events, true)) {
                $matched[] = [
                    'id' => (int) $row['id'],
                    'url' => $row['url'],
                    'secret' => $row['secret'],
                ];
            }
        }

        // Eviction LRU : supprimer la plus ancienne entree si le cache est plein
        if (count($this->cache) >= $this->maxCacheSize) {
            reset($this->cache);
            $oldest = key($this->cache);
            unset($this->cache[$oldest]);
        }

        $this->cache[$eventName] = $matched;

        return $matched;
    }

    /**
     * Vide le cache des webhooks (utile apres creation/modification).
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Retourne true si boot() a deja ete appele.
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Retourne le nombre d'entrees dans le cache.
     */
    public function cacheSize(): int
    {
        return count($this->cache);
    }

    /**
     * Definit la taille max du cache LRU.
     */
    public function setMaxCacheSize(int $size): void
    {
        $this->maxCacheSize = $size;
    }
}
