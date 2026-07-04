<?php

namespace Kyqo\Notifications\Messages;

/**
 * Fluent Slack message builder.
 *
 * Usage:
 *   return (new SlackMessage)
 *       ->content('Server alert!')
 *       ->attachment(fn ($a) => $a->title('CPU usage')->content('99%')->color('#ef4444'));
 */
class SlackMessage
{
    public string $content    = '';
    public ?string $from      = null;
    public ?string $emoji     = null;
    public array   $attachments = [];
    public ?string $channel   = null;

    public function content(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function from(string $username, string $emoji = null): static
    {
        $this->from  = $username;
        $this->emoji = $emoji;
        return $this;
    }

    public function to(string $channel): static
    {
        $this->channel = $channel;
        return $this;
    }

    public function attachment(\Closure $callback): static
    {
        $attachment    = new SlackAttachment();
        $callback($attachment);
        $this->attachments[] = $attachment;
        return $this;
    }

    public function toPayload(): array
    {
        $payload = ['text' => $this->content];
        if ($this->from)    $payload['username']   = $this->from;
        if ($this->emoji)   $payload['icon_emoji'] = $this->emoji;
        if ($this->channel) $payload['channel']    = $this->channel;
        if (!empty($this->attachments)) {
            $payload['attachments'] = array_map(fn ($a) => $a->toArray(), $this->attachments);
        }
        return $payload;
    }
}
