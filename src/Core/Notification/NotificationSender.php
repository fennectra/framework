<?php

namespace Fennec\Core\Notification;

use Fennec\Core\Notification\Channels\DatabaseChannel;
use Fennec\Core\Notification\Channels\MailChannel;
use Fennec\Core\Notification\Channels\SlackChannel;
use Fennec\Core\Notification\Channels\WebhookChannel;

/**
 * Dispatche une notification vers les canaux déclarés par via().
 */
class NotificationSender
{
    private DatabaseChannel $database;
    private MailChannel $mail;
    private SlackChannel $slack;
    private WebhookChannel $webhook;

    public function __construct()
    {
        $this->database = new DatabaseChannel();
        $this->mail = new MailChannel();
        $this->slack = new SlackChannel();
        $this->webhook = new WebhookChannel();
    }

    /**
     * Envoie la notification sur chaque canal retourné par via().
     *
     * @param mixed                 $notifiable  Entité destinataire (Model avec id)
     * @param NotificationInterface $notification
     */
    public function send(mixed $notifiable, NotificationInterface $notification): void
    {
        foreach ($notification->via() as $channel) {
            $this->resolveChannel($channel)->send($notifiable, $notification);
        }
    }

    /**
     * Résout le canal à partir de son nom.
     */
    private function resolveChannel(string $name): DatabaseChannel|MailChannel|SlackChannel|WebhookChannel
    {
        return match ($name) {
            'database' => $this->database,
            'mail' => $this->mail,
            'slack' => $this->slack,
            'webhook' => $this->webhook,
            default => throw new \RuntimeException("Canal de notification inconnu : {$name}"),
        };
    }
}
