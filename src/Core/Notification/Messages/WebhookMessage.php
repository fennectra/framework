<?php

namespace Fennec\Core\Notification\Messages;

/**
 * Value object pour les notifications webhook.
 *
 * Setters fluents pour une construction lisible.
 */
class WebhookMessage
{
    public string $url = '';
    public string $secret = '';
    public string $event = '';
    public array $payload = [];
    public array $headers = [];

    public function url(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function secret(string $secret): self
    {
        $this->secret = $secret;

        return $this;
    }

    public function event(string $event): self
    {
        $this->event = $event;

        return $this;
    }

    public function payload(array $payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    public function headers(array $headers): self
    {
        $this->headers = $headers;

        return $this;
    }
}
