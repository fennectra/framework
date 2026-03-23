<?php

namespace Fennec\Core\Notification\Channels;

use Fennec\Core\Env;
use Fennec\Core\Notification\NotificationInterface;

/**
 * Envoie la notification vers un webhook Slack.
 *
 * Variable d'environnement : SLACK_WEBHOOK_URL
 */
class SlackChannel
{
    public function send(mixed $notifiable, NotificationInterface $notification): void
    {
        $message = $notification->toSlack();
        if ($message === null) {
            return;
        }

        $webhookUrl = Env::get('SLACK_WEBHOOK_URL', '');
        if ($webhookUrl === '') {
            throw new \RuntimeException('SLACK_WEBHOOK_URL non configuree dans .env');
        }

        $payload = ['text' => $message->text];

        if ($message->channel !== null) {
            $payload['channel'] = $message->channel;
        }
        if ($message->username !== null) {
            $payload['username'] = $message->username;
        }
        if ($message->iconEmoji !== null) {
            $payload['icon_emoji'] = $message->iconEmoji;
        }

        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nContent-Length: " . strlen($json) . "\r\n",
                'content' => $json,
                'timeout' => 10,
            ],
        ]);

        $result = file_get_contents($webhookUrl, false, $context);

        if ($result === false) {
            throw new \RuntimeException('Echec de l\'envoi vers Slack');
        }
    }
}
