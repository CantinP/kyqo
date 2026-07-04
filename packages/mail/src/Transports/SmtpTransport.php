<?php

namespace Kyqo\Mail\Transports;

use Kyqo\Mail\Message;

/**
 * SMTP Transport
 *
 * Sends mail via a raw SMTP connection using PHP streams.
 * Supports STARTTLS, AUTH LOGIN / AUTH PLAIN, plain text + HTML multipart,
 * and file attachments.
 *
 * FIX AUDIT-1: send() body is now wrapped in try/finally so the socket
 * is always closed — even when expect() or any other helper throws.
 *
 * FIX AUDIT-2: attachment paths are validated against an allowed root
 * directory (config key 'attachment_root', defaults to sys_get_temp_dir()).
 * Any path that resolves outside that root throws an exception instead of
 * silently reading an arbitrary file.
 *
 * For production use with OAuth / advanced TLS needs, swap this transport
 * for one backed by a library such as Symfony Mailer or PHPMailer.
 */
class SmtpTransport
{
    protected $socket = null;

    public function __construct(protected array $config) {}

    public function send(Message $message): void
    {
        $host       = $this->config['host']       ?? 'localhost';
        $port       = (int) ($this->config['port'] ?? 587);
        $encryption = strtolower($this->config['encryption'] ?? '');
        $username   = $this->config['username']   ?? '';
        $password   = $this->config['password']   ?? '';
        $fromAddr   = $message->getFrom()     ?? $this->config['from']['address'] ?? 'noreply@localhost';
        $fromName   = $message->getFromName() ?? $this->config['from']['name']    ?? '';
        $timeout    = (int) ($this->config['timeout'] ?? 30);

        $host_str = ($encryption === 'ssl') ? 'ssl://' . $host : $host;

        $this->socket = fsockopen($host_str, $port, $errno, $errstr, $timeout);
        if (!$this->socket) {
            throw new \RuntimeException("SMTP: Cannot connect to {$host}:{$port} — {$errstr} ({$errno})");
        }

        // FIX AUDIT-1: always close the socket, even on exception.
        try {
            $this->expect(220);
            $this->write('EHLO ' . ($this->config['ehlo'] ?? gethostname()));
            $ehlo = $this->readAll();

            if ($encryption === 'tls') {
                $this->write('STARTTLS');
                $this->expect(220);
                if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('SMTP: STARTTLS negotiation failed.');
                }
                $this->write('EHLO ' . ($this->config['ehlo'] ?? gethostname()));
                $ehlo = $this->readAll();
            }

            if (!empty($username) && str_contains($ehlo, 'AUTH')) {
                $this->write('AUTH LOGIN');
                $this->expect(334);
                $this->write(base64_encode($username));
                $this->expect(334);
                $this->write(base64_encode($password));
                $this->expect(235);
            }

            $this->write('MAIL FROM:<' . $fromAddr . '>');
            $this->expect(250);

            $allRecipients = array_merge(
                $message->getTo(),
                $message->getCc(),
                $message->getBcc()
            );

            if (empty($allRecipients)) {
                throw new \RuntimeException('SMTP: No recipients specified.');
            }

            foreach ($allRecipients as $rcpt) {
                $this->write('RCPT TO:<' . $rcpt['address'] . '>');
                $this->expect(250);
            }

            $this->write('DATA');
            $this->expect(354);
            $this->write($this->buildRaw($message, $fromAddr, $fromName));
            $this->write('.');
            $this->expect(250);
            $this->write('QUIT');
        } finally {
            if (is_resource($this->socket)) {
                fclose($this->socket);
            }
            $this->socket = null;
        }
    }

    protected function buildRaw(Message $msg, string $fromAddr, string $fromName): string
    {
        $boundary     = 'kyqo_' . bin2hex(random_bytes(8));
        $altBoundary  = 'kyqo_alt_' . bin2hex(random_bytes(8));
        $hasHtml      = $msg->getHtml() !== null;
        $hasText      = $msg->getText() !== null;
        $hasAttach    = !empty($msg->getAttachments());

        $fromHeader   = $fromName ? $this->encodeHeader($fromName) . ' <' . $fromAddr . '>' : $fromAddr;
        $headers      = "From: {$fromHeader}\r\n";
        $headers     .= 'To: '   . $this->recipientList($msg->getTo())   . "\r\n";
        if ($msg->getCc())      $headers .= 'Cc: '       . $this->recipientList($msg->getCc())      . "\r\n";
        if ($msg->getReplyTo()) $headers .= 'Reply-To: ' . $this->recipientList($msg->getReplyTo()) . "\r\n";
        $headers .= 'Subject: ' . $this->encodeHeader((string) $msg->getSubject()) . "\r\n";
        $headers .= 'Date: '    . date('r') . "\r\n";
        $headers .= 'MIME-Version: 1.0' . "\r\n";
        foreach ($msg->getHeaders() as $name => $value) {
            $headers .= "{$name}: {$value}\r\n";
        }

        // Build body
        if ($hasAttach) {
            $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
            $body = "--{$boundary}\r\n";
            if ($hasHtml && $hasText) {
                $body .= "Content-Type: multipart/alternative; boundary=\"{$altBoundary}\"\r\n\r\n";
                $body .= "--{$altBoundary}\r\n";
                $body .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n";
                $body .= quoted_printable_encode($msg->getText()) . "\r\n";
                $body .= "--{$altBoundary}\r\n";
                $body .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n";
                $body .= quoted_printable_encode($msg->getHtml()) . "\r\n";
                $body .= "--{$altBoundary}--\r\n";
            } elseif ($hasHtml) {
                $body .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n";
                $body .= quoted_printable_encode($msg->getHtml()) . "\r\n";
            } else {
                $body .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n";
                $body .= quoted_printable_encode((string) $msg->getText()) . "\r\n";
            }
            foreach ($msg->getAttachments() as $att) {
                // FIX AUDIT-2: validate attachment path against allowed root.
                $content = base64_encode($this->readAttachment($att['path']));
                $body .= "--{$boundary}\r\n";
                $body .= "Content-Type: {$att['mime']}; name=\"{$att['name']}\"\r\n";
                $body .= "Content-Disposition: attachment; filename=\"{$att['name']}\"\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
                $body .= chunk_split($content) . "\r\n";
            }
            $body .= "--{$boundary}--";
        } elseif ($hasHtml && $hasText) {
            $headers .= "Content-Type: multipart/alternative; boundary=\"{$altBoundary}\"\r\n";
            $body  = "--{$altBoundary}\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n";
            $body .= quoted_printable_encode($msg->getText()) . "\r\n";
            $body .= "--{$altBoundary}\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n";
            $body .= quoted_printable_encode($msg->getHtml()) . "\r\n";
            $body .= "--{$altBoundary}--";
        } elseif ($hasHtml) {
            $headers .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n";
            $body = quoted_printable_encode($msg->getHtml());
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n";
            $body = quoted_printable_encode((string) $msg->getText());
        }

        return $headers . "\r\n" . $body;
    }

    /**
     * FIX AUDIT-2: Read an attachment file, ensuring its real path is within
     * the configured allowed root to prevent path-traversal attacks.
     *
     * Config key: 'attachment_root' (defaults to sys_get_temp_dir()).
     *
     * @throws \RuntimeException if the path is outside the allowed root or unreadable.
     */
    protected function readAttachment(string $path): string
    {
        $allowedRoot = rtrim($this->config['attachment_root'] ?? sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        $real        = realpath($path);

        if ($real === false) {
            throw new \RuntimeException("SMTP attachment: file not found or unreadable: [{$path}]");
        }

        if (!str_starts_with($real, $allowedRoot . DIRECTORY_SEPARATOR)
            && $real !== $allowedRoot
        ) {
            throw new \RuntimeException(
                "SMTP attachment: path [{$real}] is outside the allowed root [{$allowedRoot}]."
            );
        }

        $contents = file_get_contents($real);
        if ($contents === false) {
            throw new \RuntimeException("SMTP attachment: could not read file [{$real}]");
        }

        return $contents;
    }

    protected function recipientList(array $recipients): string
    {
        return implode(', ', array_map(fn ($r) => $r['name']
            ? $this->encodeHeader($r['name']) . ' <' . $r['address'] . '>'
            : $r['address'],
        $recipients));
    }

    protected function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }

    protected function write(string $data): void
    {
        fwrite($this->socket, $data . "\r\n");
    }

    protected function read(): string
    {
        return (string) fgets($this->socket, 1024);
    }

    protected function readAll(): string
    {
        $response = '';
        while ($line = fgets($this->socket, 1024)) {
            $response .= $line;
            if ($line[3] === ' ') break;
        }
        return $response;
    }

    protected function expect(int $code): string
    {
        $response = $this->readAll();
        if (!str_starts_with(trim($response), (string) $code)) {
            throw new \RuntimeException("SMTP: Expected {$code}, got: {$response}");
        }
        return $response;
    }
}
