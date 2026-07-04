<?php

namespace Kyqo\Mail;

use Kyqo\Mail\Transports\SmtpTransport;

/**
 * Mail Manager
 *
 * Manages mail transports and provides a simple send interface.
 *
 * Usage:
 *   Mail::send(function (Message $msg) {
 *       $msg->to('user@example.com')
 *           ->subject('Hello')
 *           ->html(view('emails.welcome', ['user' => $user]));
 *   });
 */
class MailManager
{
    protected array $config;
    protected array $transportInstances = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Send a message via a callback or a pre-built Message instance.
     */
    public function send(\Closure|Message $message): void
    {
        if ($message instanceof \Closure) {
            $msg = new Message();
            $message($msg);
        } else {
            $msg = $message;
        }

        // Apply global defaults
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

    /**
     * Alias for send() — sends a view-rendered HTML email.
     */
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
