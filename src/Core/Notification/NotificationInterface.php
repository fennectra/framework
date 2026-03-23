<?php

namespace Fennec\Core\Notification;

use Fennec\Core\Notification\Messages\MailMessage;
use Fennec\Core\Notification\Messages\SlackMessage;
use Fennec\Core\Notification\Messages\WebhookMessage;

interface NotificationInterface
{
    /**
     * Canaux de diffusion de la notification.
     *
     * @return string[] Ex: ['database', 'mail', 'slack', 'webhook']
     */
    public function via(): array;

    /**
     * Données pour le stockage en base.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(): array;

    /**
     * Représentation email de la notification.
     */
    public function toMail(): ?MailMessage;

    /**
     * Représentation Slack de la notification.
     */
    public function toSlack(): ?SlackMessage;

    /**
     * Représentation webhook de la notification.
     */
    public function toWebhook(): ?WebhookMessage;
}
