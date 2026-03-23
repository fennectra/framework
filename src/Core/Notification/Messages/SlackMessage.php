<?php

namespace Fennec\Core\Notification\Messages;

/**
 * Value object pour les notifications Slack.
 *
 * Setters fluents pour une construction lisible.
 */
class SlackMessage
{
    public string $text = '';
    public ?string $channel = null;
    public ?string $username = null;
    public ?string $iconEmoji = null;

    public function text(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    public function channel(string $channel): self
    {
        $this->channel = $channel;

        return $this;
    }

    public function username(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function iconEmoji(string $iconEmoji): self
    {
        $this->iconEmoji = $iconEmoji;

        return $this;
    }
}
