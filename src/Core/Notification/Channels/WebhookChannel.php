<?php

namespace Fennec\Core\Notification\Channels;

use Fennec\Core\Notification\NotificationInterface;
use Fennec\Core\Queue\Job;
use Fennec\Core\Webhook\WebhookDeliveryJob;

/**
 * Canal de notification par webhook HTTP.
 *
 * Envoie une requete POST signee vers l'URL configuree dans la notification.
 */
class WebhookChannel
{
    public function send(mixed $notifiable, NotificationInterface $notification): void
    {
        $message = $notification->toWebhook();
        if ($message === null) {
            return;
        }

        $payload = $message->payload;
        if ($payload === []) {
            $payload = $notification->toDatabase();
        }

        Job::dispatch(WebhookDeliveryJob::class, [
            'webhook_id' => 0,
            'url' => $message->url,
            'secret' => $message->secret,
            'event' => $message->event,
            'payload' => $payload,
        ], 'webhooks');
    }
}
