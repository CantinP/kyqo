<?php

namespace Kyqo\Notifications\Messages;

/**
 * Fluent mail message for notifications.
 *
 * Usage:
 *   return (new MailMessage)
 *       ->subject('Your account is ready')
 *       ->greeting('Hello!')
 *       ->line('Thanks for signing up.')
 *       ->action('Visit Dashboard', 'https://app.example.com')
 *       ->line('If you have questions, just reply.');
 */
class MailMessage
{
    public string  $subject    = '';
    public string  $greeting   = '';
    public array   $lines      = [];
    public array   $actionLines = [];
    public ?string $from       = null;
    public ?string $fromName   = null;
    public string  $level      = 'info'; // info | success | error

    public function subject(string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function greeting(string $greeting): static
    {
        $this->greeting = $greeting;
        return $this;
    }

    public function line(string $line): static
    {
        $this->lines[] = $line;
        return $this;
    }

    public function action(string $text, string $url): static
    {
        $this->actionLines[] = ['text' => $text, 'url' => $url];
        return $this;
    }

    public function from(string $address, string $name = ''): static
    {
        $this->from     = $address;
        $this->fromName = $name;
        return $this;
    }

    public function level(string $level): static
    {
        $this->level = $level;
        return $this;
    }

    public function success(): static { return $this->level('success'); }
    public function error(): static   { return $this->level('error'); }

    /**
     * Render as plain HTML email body.
     */
    public function toHtml(): string
    {
        $color = match ($this->level) {
            'success' => '#22c55e',
            'error'   => '#ef4444',
            default   => '#3b82f6',
        };

        $html = "<div style='font-family:sans-serif;max-width:600px;margin:auto;padding:24px'>";

        if ($this->greeting) {
            $html .= "<h2 style='color:{$color}'>" . htmlspecialchars($this->greeting) . "</h2>";
        }

        foreach ($this->lines as $line) {
            $html .= "<p>" . htmlspecialchars($line) . "</p>";
        }

        foreach ($this->actionLines as $action) {
            $url  = htmlspecialchars($action['url']);
            $text = htmlspecialchars($action['text']);
            $html .= "<p><a href='{$url}' style='background:{$color};color:#fff;padding:10px 20px;border-radius:4px;text-decoration:none'>{$text}</a></p>";
        }

        $html .= "</div>";
        return $html;
    }
}
