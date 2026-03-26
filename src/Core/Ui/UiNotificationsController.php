<?php

namespace Fennec\Core\Ui;

use Fennec\Core\DB;
use Fennec\Core\Env;
use Fennec\Core\Response;

class UiNotificationsController
{
    use UiHelper;

    public function list(): void
    {
        try {
            $result = $this->paginate('notifications');

            foreach ($result['data'] as &$row) {
                $row['data'] = json_decode($row['data'] ?? '{}', true) ?? [];
            }

            Response::json($result);
        } catch (\Throwable) {
            Response::json(['data' => [], 'total' => 0, 'page' => 1, 'per_page' => 20, 'last_page' => 0]);
        }
    }

    public function stats(): void
    {
        try {
            $total = DB::raw('SELECT COUNT(*) as cnt FROM notifications')->fetchAll()[0]['cnt'] ?? 0;
            $unread = DB::raw('SELECT COUNT(*) as cnt FROM notifications WHERE read_at IS NULL')->fetchAll()[0]['cnt'] ?? 0;

            $byType = DB::raw(
                'SELECT type, COUNT(*) as cnt FROM notifications GROUP BY type ORDER BY cnt DESC'
            )->fetchAll();

            Response::json([
                'total' => (int) $total,
                'unread' => (int) $unread,
                'byType' => $byType,
                'channels' => $this->availableChannels(),
            ]);
        } catch (\Throwable) {
            Response::json(['total' => 0, 'unread' => 0, 'byType' => [], 'channels' => $this->availableChannels()]);
        }
    }

    public function channels(): void
    {
        Response::json($this->availableChannels());
    }

    private function availableChannels(): array
    {
        return [
            'database' => ['enabled' => true, 'label' => 'Database'],
            'mail' => ['enabled' => (bool) Env::get('MAIL_HOST'), 'label' => 'Email'],
            'slack' => ['enabled' => (bool) Env::get('SLACK_WEBHOOK_URL'), 'label' => 'Slack'],
            'webhook' => ['enabled' => true, 'label' => 'Webhook'],
        ];
    }
}
