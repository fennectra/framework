<?php

namespace App\Models;

use Fennec\Attributes\Table;
use Fennec\Core\DB;
use Fennec\Core\Model;
use Fennec\Core\Relations\HasMany;

#[Table('webhooks')]
class Webhook extends Model
{
    /** @var array<string, string> */
    protected static array $casts = [
        'is_active' => 'bool',
        'events' => 'json',
    ];

    /**
     * Les livraisons liees a ce webhook.
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, 'webhook_id');
    }

    /**
     * Retourne les webhooks actifs abonnes a un event donne.
     *
     * @return array<int, static>
     */
    public static function activeForEvent(string $event): array
    {
        $all = static::where('is_active', '=', true)->get();
        $matched = [];

        foreach ($all as $webhook) {
            $events = $webhook->getAttribute('events');
            if (is_string($events)) {
                $events = json_decode($events, true) ?: [];
            }
            if (in_array('*', $events, true) || in_array($event, $events, true)) {
                $matched[] = $webhook;
            }
        }

        return $matched;
    }

    /**
     * Statistiques de livraison groupees par webhook.
     */
    public static function stats(): array
    {
        $stmt = DB::raw(
            'SELECT w.id, w.name, w.url, w.is_active,
                    COUNT(wd.id) as total_deliveries,
                    COUNT(CASE WHEN wd.status = \'delivered\' THEN 1 END) as delivered,
                    COUNT(CASE WHEN wd.status = \'failed\' THEN 1 END) as failed,
                    COUNT(CASE WHEN wd.status = \'pending\' THEN 1 END) as pending
             FROM webhooks w
             LEFT JOIN webhook_deliveries wd ON wd.webhook_id = w.id
             GROUP BY w.id, w.name, w.url, w.is_active
             ORDER BY w.name'
        );

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}