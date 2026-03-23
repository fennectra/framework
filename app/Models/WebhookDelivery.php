<?php

namespace App\Models;

use Fennec\Attributes\Table;
use Fennec\Core\DB;
use Fennec\Core\Model;
use Fennec\Core\Relations\BelongsTo;

#[Table('webhook_deliveries')]
class WebhookDelivery extends Model
{
    /** @var array<string, string> */
    protected static array $casts = [
        'webhook_id' => 'int',
        'http_status' => 'int',
        'attempt' => 'int',
    ];

    /**
     * Le webhook parent.
     */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class, 'webhook_id');
    }

    /**
     * Retourne les dernieres livraisons echouees.
     */
    public static function recentFailures(int $limit = 20): array
    {
        $stmt = DB::raw(
            'SELECT wd.id, wd.webhook_id, wd.event, wd.url, wd.status,
                    wd.http_status, wd.attempt, wd.response_body, wd.created_at,
                    w.name as webhook_name
             FROM webhook_deliveries wd
             LEFT JOIN webhooks w ON w.id = wd.webhook_id
             WHERE wd.status = \'failed\'
             ORDER BY wd.created_at DESC
             LIMIT :limit',
            ['limit' => $limit]
        );

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Vue d'ensemble des totaux par statut (pending, delivered, failed).
     */
    public static function statsOverview(): array
    {
        $stmt = DB::raw(
            'SELECT
                COUNT(*) as total,
                COUNT(CASE WHEN status = \'pending\' THEN 1 END) as pending,
                COUNT(CASE WHEN status = \'delivered\' THEN 1 END) as delivered,
                COUNT(CASE WHEN status = \'failed\' THEN 1 END) as failed
             FROM webhook_deliveries'
        );

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [
            'total' => 0,
            'pending' => 0,
            'delivered' => 0,
            'failed' => 0,
        ];
    }
}