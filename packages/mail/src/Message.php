<?php

namespace Kyqo\Mail;

/**
 * Mail Message
 *
 * Fluent builder for an email message.
 *
 * Usage:
 *   $msg = (new Message)
 *       ->to('user@example.com', 'Alice')
 *       ->subject('Welcome!')
 *       ->html('<h1>Hello Alice</h1>')
 *       ->text('Hello Alice');
 */
class Message
{
    protected array   $to      = [];
    protected array   $cc      = [];
    protected array   $bcc     = [];
    protected array   $replyTo = [];
    protected ?string $from    = null;
    protected ?string $fromName = null;
    protected ?string $subject = null;
    protected ?string $html    = null;
    protected ?string $text    = null;
    protected array   $attachments = [];
    protected array   $headers = [];

    public function from(string $address, string $name = ''): static
    {
        $this->from     = $address;
        $this->fromName = $name;
        return $this;
    }

    public function to(string $address, string $name = ''): static
    {
        $this->to[] = ['address' => $address, 'name' => $name];
        return $this;
    }

    public function cc(string $address, string $name = ''): static
    {
        $this->cc[] = ['address' => $address, 'name' => $name];
        return $this;
    }

    public function bcc(string $address, string $name = ''): static
    {
        $this->bcc[] = ['address' => $address, 'name' => $name];
        return $this;
    }

    public function replyTo(string $address, string $name = ''): static
    {
        $this->replyTo[] = ['address' => $address, 'name' => $name];
        return $this;
    }

    public function subject(string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function html(string $html): static
    {
        $this->html = $html;
        return $this;
    }

    public function text(string $text): static
    {
        $this->text = $text;
        return $this;
    }

    public function attach(string $path, string $name = '', string $mime = ''): static
    {
        $this->attachments[] = [
            'path' => $path,
            'name' => $name ?: basename($path),
            'mime' => $mime ?: 'application/octet-stream',
        ];
        return $this;
    }

    public function header(string $name, string $value): static
    {
        $this->headers[$name] = $value;
        return $this;
    }

    // Getters
    public function getTo(): array       { return $this->to; }
    public function getCc(): array       { return $this->cc; }
    public function getBcc(): array      { return $this->bcc; }
    public function getReplyTo(): array  { return $this->replyTo; }
    public function getFrom(): ?string   { return $this->from; }
    public function getFromName(): ?string { return $this->fromName; }
    public function getSubject(): ?string { return $this->subject; }
    public function getHtml(): ?string   { return $this->html; }
    public function getText(): ?string   { return $this->text; }
    public function getAttachments(): array { return $this->attachments; }
    public function getHeaders(): array  { return $this->headers; }
}
