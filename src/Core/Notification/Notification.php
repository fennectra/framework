<?php

namespace Fennec\Core\Notification;

use Fennec\Core\Notification\Messages\MailMessage;
use Fennec\Core\Notification\Messages\SlackMessage;
use Fennec\Core\Notification\Messages\WebhookMessage;

/**
 * Classe de base abstraite pour les notifications.
 *
 * Fournit des implémentations par défaut pour chaque canal.
 * Les sous-classes surchargent uniquement les canaux nécessaires.
 */
abstract class Notification implements NotificationInterface
{
    public function via(): array
    {
        return ['database'];
    }

    public function toDatabase(): array
    {
        return [];
    }

    public function toMail(): ?MailMessage
    {
        return null;
    }

    public function toSlack(): ?SlackMessage
    {
        return null;
    }

    public function toWebhook(): ?WebhookMessage
    {
        return null;
    }
}
