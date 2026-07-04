<?php

namespace Kyqo\Mail;

use Kyqo\Mail\Transports\SmtpTransport;

/**
 * Mail Manager
 *
 * Supports both low-level Message/Closure sending and Mailable objects.
 *
 * Usage (Mailable):
 *   Mail::send(new WelcomeMail($user));
 *   Mail::to('user@example.com')->send(new WelcomeMail($user));
 *
 * Usage (Closure / Message):
 *   Mail::send(function (Message $msg) {
 *       $msg->to('user@example.com')->subject('Hello')->html('...');
 *   });
 */
class MailManager
{
    protected array $config;
    protected array $transportInstances = [];
    protected ?\Kyqo\View\Engine $viewEngine = null;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function setViewEngine(\Kyqo\View\Engine $engine): static
    {
        $this->viewEngine = $engine;
        return $this;
    }

    /**
     * Send a message via Mailable, Closure, or pre-built Message.
     */
    public function send(Mailable|\Closure|Message $message): void
    {
        if ($message instanceof Mailable) {
            $msg = $message->toMessage($this->viewEngine);
        } elseif ($message instanceof \Closure) {
            $msg = new Message();
            $message($msg);
        } else {
            $msg = $message;
        }

        if (empty($msg->getTo())) {
            throw new \RuntimeException('Mail: No recipient specified.');
        }

        if ($msg->getFrom() === null) {
            $msg->from(
                $this->config['from']['address'] ?? 'noreply@localhost',
                $this->config['from']['name']    ?? ''
            );
        }

        $this->resolveTransport()->send($msg);
    }

    public function to(string $address, string $name = ''): PendingMail
    {
        return (new PendingMail($this))->to($address, $name);
    }

    public function resolveTransport(string $mailer = null): SmtpTransport
    {
        $mailer ??= $this->config['default'] ?? $this->config['mailer'] ?? 'smtp';

        if (!isset($this->transportInstances[$mailer])) {
            $config = $this->config['mailers'][$mailer] ?? $this->config;
            $driver = $config['transport'] ?? $config['driver'] ?? $mailer;

            $this->transportInstances[$mailer] = match ($driver) {
                'smtp'  => new SmtpTransport(array_merge($this->config, $config)),
                'log'   => new Transports\LogTransport(),
                default => throw new \InvalidArgumentException("Mail driver [{$driver}] not supported."),
            };
        }

        return $this->transportInstances[$mailer];
    }
}
