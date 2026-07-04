<?php

namespace Kyqo\Mail;

/**
 * Fluent pending mail builder.
 *
 * Mail::to('user@example.com')->send(new WelcomeMail($user));
 * Mail::to('user@example.com')->queue(new WelcomeMail($user));
 */
class PendingMail
{
    protected array $to  = [];
    protected array $cc  = [];
    protected array $bcc = [];

    public function __construct(protected MailManager $manager) {}

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

    public function send(Message $message): void
    {
        foreach ($this->to  as $r) $message->to($r['address'],  $r['name']);
        foreach ($this->cc  as $r) $message->cc($r['address'],  $r['name']);
        foreach ($this->bcc as $r) $message->bcc($r['address'], $r['name']);
        $this->manager->send($message);
    }
}
