<?php

namespace Kyqo\Mail\Transports;

use Kyqo\Mail\Message;

/**
 * Log Transport — writes the mail headers + body to error_log instead of sending.
 * Perfect for local development without an SMTP server.
 */
class LogTransport
{
    public function send(Message $message): void
    {
        $to      = implode(', ', array_column($message->getTo(), 'address'));
        $subject = $message->getSubject() ?? '(no subject)';
        $body    = $message->getText() ?? $message->getHtml() ?? '';

        error_log(sprintf(
            "[Kyqo Mail Log]\nTo: %s\nSubject: %s\n%s\n",
            $to,
            $subject,
            $body
        ));
    }
}
