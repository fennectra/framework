<?php

namespace Fennec\Core\Ui;

use Fennec\Core\DB;
use Fennec\Core\Response;
use Fennec\Core\Security\SecurityLogger;
use Fennec\Core\Webhook;

class UiWebhooksController
{
    use UiHelper;

    public function list(): void
    {
        try {
            $rows = DB::raw('SELECT * FROM webhooks ORDER BY id DESC')->fetchAll();

            foreach ($rows as &$row) {
                $row['events'] = json_decode($row['events'] ?? '[]', true) ?? [];
            }

            Response::json($rows);
        } catch (\Throwable) {
            Response::json([]);
        }
    }

    public function create(): void
    {
        $body = $this->body();
        $name = $body['name'] ?? '';
        $url = $body['url'] ?? '';
        $secret = $body['secret'] ?? bin2hex(random_bytes(16));
        $events = $body['events'] ?? [];
        $description = $body['description'] ?? '';

        if (!$name || !$url) {
            Response::json(['error' => 'Name and URL are required'], 422);

            return;
        }

        try {
            $eventsJson = json_encode($events);
            DB::raw(
                'INSERT INTO webhooks (name, url, secret, events, is_active, description, created_at, updated_at) VALUES (?, ?, ?, ?, true, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
                [$name, $url, $secret, $eventsJson, $description]
            );

            SecurityLogger::track('webhook.created', ['name' => $name, 'url' => $url]);
            Webhook\WebhookManager::getInstance()->clearCache();

            Response::json(['success' => true, 'secret' => $secret]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(int $id): void
    {
        $body = $this->body();

        $fields = [];
        $params = [];

        if (isset($body['name'])) {
            $fields[] = 'name = ?';
            $params[] = $body['name'];
        }
        if (isset($body['url'])) {
            $fields[] = 'url = ?';
            $params[] = $body['url'];
        }
        if (isset($body['events'])) {
            $fields[] = 'events = ?';
            $params[] = json_encode($body['events']);
        }
        if (isset($body['is_active'])) {
            $fields[] = 'is_active = ?';
            $params[] = $body['is_active'] ? 'true' : 'false';
        }
        if (isset($body['description'])) {
            $fields[] = 'description = ?';
            $params[] = $body['description'];
        }

        if (!$fields) {
            Response::json(['error' => 'No fields to update'], 422);

            return;
        }

        $fields[] = 'updated_at = CURRENT_TIMESTAMP';
        $params[] = $id;

        try {
            DB::raw('UPDATE webhooks SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
            Webhook\WebhookManager::getInstance()->clearCache();

            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function delete(int $id): void
    {
        try {
            DB::raw('DELETE FROM webhooks WHERE id = ?', [$id]);
            SecurityLogger::track('webhook.deleted', ['id' => $id]);
            Webhook\WebhookManager::getInstance()->clearCache();

            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function deliveries(int $id): void
    {
        try {
            $rows = DB::raw(
                'SELECT * FROM webhook_deliveries WHERE webhook_id = ? ORDER BY id DESC LIMIT 50',
                [$id]
            )->fetchAll();

            Response::json($rows);
        } catch (\Throwable) {
            Response::json([]);
        }
    }

    public function toggle(int $id): void
    {
        try {
            DB::raw('UPDATE webhooks SET is_active = NOT is_active, updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$id]);
            Webhook\WebhookManager::getInstance()->clearCache();

            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function stats(): void
    {
        try {
            $total = DB::raw('SELECT COUNT(*) as cnt FROM webhooks')->fetchAll()[0]['cnt'] ?? 0;
            $active = DB::raw('SELECT COUNT(*) as cnt FROM webhooks WHERE is_active = true')->fetchAll()[0]['cnt'] ?? 0;
            $deliveries = DB::raw('SELECT COUNT(*) as cnt FROM webhook_deliveries')->fetchAll()[0]['cnt'] ?? 0;
            $failures = DB::raw(
                'SELECT COUNT(*) as cnt FROM webhook_deliveries WHERE status_code >= 400 OR status_code = 0'
            )->fetchAll()[0]['cnt'] ?? 0;

            Response::json([
                'total' => (int) $total,
                'active' => (int) $active,
                'totalDeliveries' => (int) $deliveries,
                'failures' => (int) $failures,
            ]);
        } catch (\Throwable) {
            Response::json(['total' => 0, 'active' => 0, 'totalDeliveries' => 0, 'failures' => 0]);
        }
    }

    public function retryDelivery(int $deliveryId): void
    {
        try {
            $delivery = DB::raw(
                'SELECT * FROM webhook_deliveries WHERE id = ?',
                [$deliveryId]
            )->fetchAll()[0] ?? null;

            if (!$delivery) {
                Response::json(['error' => 'Delivery not found'], 404);

                return;
            }

            $webhook = DB::raw(
                'SELECT * FROM webhooks WHERE id = ?',
                [$delivery['webhook_id']]
            )->fetchAll()[0] ?? null;

            if (!$webhook) {
                Response::json(['error' => 'Webhook not found'], 404);

                return;
            }

            $payload = json_decode($delivery['payload'] ?? '{}', true) ?? [];
            Webhook\WebhookManager::getInstance()->send(
                $webhook['url'],
                $payload,
                $webhook['secret'],
                $delivery['event'] ?? 'retry'
            );

            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }
}
