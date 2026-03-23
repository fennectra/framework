<?php

namespace Fennec\Core\Notification\Channels;

use Fennec\Core\DB;
use Fennec\Core\Notification\NotificationInterface;

/**
 * Persiste la notification en base dans la table `notifications`.
 *
 * Schema attendu :
 *   notifications (id SERIAL, notifiable_type VARCHAR, notifiable_id INT,
 *                  type VARCHAR, data JSONB, read_at TIMESTAMP NULL,
 *                  created_at TIMESTAMP)
 */
class DatabaseChannel
{
    public function send(mixed $notifiable, NotificationInterface $notification): void
    {
        $data = $notification->toDatabase();

        $notifiableType = is_object($notifiable) ? get_class($notifiable) : 'unknown';
        $notifiableId = is_object($notifiable) && isset($notifiable->id)
            ? $notifiable->id
            : ($notifiable['id'] ?? 0);

        DB::raw(
            'INSERT INTO notifications (notifiable_type, notifiable_id, type, data, read_at, created_at)
             VALUES (:notifiable_type, :notifiable_id, :type, :data, NULL, :created_at)',
            [
                'notifiable_type' => $notifiableType,
                'notifiable_id' => $notifiableId,
                'type' => get_class($notification),
                'data' => json_encode($data, JSON_THROW_ON_ERROR),
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );
    }
}
