<?php

namespace Fennec\Core\Notification;

use Fennec\Core\DB;

/**
 * Trait pour les Models recevant des notifications.
 *
 * Le model doit exposer une propriete $id (ou un champ 'id' si array-accessible).
 */
trait HasNotifications
{
    /**
     * Envoie une notification a cette entite.
     */
    public function notify(NotificationInterface $notification): void
    {
        $sender = new NotificationSender();
        $sender->send($this, $notification);
    }

    /**
     * Recupere toutes les notifications de cette entite.
     *
     * @return array<int, array<string, mixed>>
     */
    public function notifications(): array
    {
        $stmt = DB::raw(
            'SELECT * FROM notifications WHERE notifiable_type = :type AND notifiable_id = :id ORDER BY created_at DESC',
            [
                'type' => static::class,
                'id' => $this->getNotifiableId(),
            ]
        );

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Recupere les notifications non lues.
     *
     * @return array<int, array<string, mixed>>
     */
    public function unreadNotifications(): array
    {
        $stmt = DB::raw(
            'SELECT * FROM notifications WHERE notifiable_type = :type AND notifiable_id = :id AND read_at IS NULL ORDER BY created_at DESC',
            [
                'type' => static::class,
                'id' => $this->getNotifiableId(),
            ]
        );

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Marque une notification comme lue.
     */
    public function markNotificationRead(int $id): void
    {
        DB::raw(
            'UPDATE notifications SET read_at = :read_at WHERE id = :id AND notifiable_type = :type AND notifiable_id = :nid',
            [
                'read_at' => date('Y-m-d H:i:s'),
                'id' => $id,
                'type' => static::class,
                'nid' => $this->getNotifiableId(),
            ]
        );
    }

    private function getNotifiableId(): int
    {
        if (isset($this->id)) {
            return (int) $this->id;
        }
        if (is_array($this) && isset($this['id'])) {
            return (int) $this['id'];
        }

        return 0;
    }
}
