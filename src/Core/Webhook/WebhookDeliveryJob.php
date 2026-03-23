<?php

namespace Fennec\Core\Webhook;

use Fennec\Core\DB;
use Fennec\Core\Logger;
use Fennec\Core\Queue\JobInterface;

/**
 * Job asynchrone pour envoyer un webhook avec retry et backoff exponentiel.
 */
class WebhookDeliveryJob implements JobInterface
{
    public function handle(array $payload): void
    {
        $url = $payload['url'];
        $secret = $payload['secret'];
        $event = $payload['event'];
        $webhookPayload = $payload['payload'];
        $webhookId = $payload['webhook_id'];
        $attempt = ($payload['attempt'] ?? 0) + 1;

        $result = WebhookManager::send($url, $webhookPayload, $secret, $event);

        // Logger la tentative
        $this->logDelivery($webhookId, $event, $url, $result, $attempt);

        if (!$result['success']) {
            throw new \RuntimeException(
                "Webhook delivery failed: HTTP {$result['status']} for {$url}"
            );
        }

        Logger::info('Webhook delivered', [
            'webhook_id' => $webhookId,
            'event' => $event,
            'url' => $url,
            'status' => $result['status'],
        ]);
    }

    public function retries(): int
    {
        return 5;
    }

    /**
     * Backoff exponentiel : 10s, 30s, 90s, 270s, 810s.
     */
    public function retryDelay(): int
    {
        return 10;
    }

    public function failed(array $payload, \Throwable $e): void
    {
        Logger::error('Webhook delivery permanently failed', [
            'webhook_id' => $payload['webhook_id'] ?? null,
            'event' => $payload['event'] ?? null,
            'url' => $payload['url'] ?? null,
            'error' => $e->getMessage(),
        ]);

        // Marquer la derniere delivery comme failed
        try {
            $webhookId = $payload['webhook_id'] ?? null;
            if ($webhookId !== null) {
                DB::table('webhook_deliveries')
                    ->where('webhook_id', $webhookId)
                    ->where('event', $payload['event'] ?? '')
                    ->orderBy('created_at', 'DESC')
                    ->limit(1)
                    ->update(['status' => 'failed']);
            }
        } catch (\Throwable) {
            // Ignorer — best effort
        }
    }

    private function logDelivery(int $webhookId, string $event, string $url, array $result, int $attempt): void
    {
        try {
            DB::table('webhook_deliveries')->insert([
                'webhook_id' => $webhookId,
                'event' => $event,
                'url' => $url,
                'payload' => json_encode($result),
                'status' => $result['success'] ? 'delivered' : 'pending',
                'http_status' => $result['status'],
                'response_body' => mb_substr($result['body'], 0, 2000),
                'attempt' => $attempt,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Logger::error('Failed to log webhook delivery', ['error' => $e->getMessage()]);
        }
    }
}
