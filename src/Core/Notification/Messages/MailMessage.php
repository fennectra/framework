<?php

namespace Fennec\Core\Notification\Messages;

/**
 * Value object pour les notifications email.
 *
 * Setters fluents pour une construction lisible.
 */
class MailMessage
{
    public string $to = '';
    public string $from = '';
    public string $subject = '';
    public string $body = '';
    public string $replyTo = '';

    public function to(string $to): self
    {
        $this->to = $to;

        return $this;
    }

    public function from(string $from): self
    {
        $this->from = $from;

        return $this;
    }

    public function subject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function body(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function replyTo(string $replyTo): self
    {
        $this->replyTo = $replyTo;

        return $this;
    }
}
