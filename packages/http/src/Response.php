<?php

namespace Kyqo\Http;

/**
 * Kyqo HTTP Response
 *
 * FIX SEC-2: Multiple Set-Cookie headers are now supported via $cookieHeaders[]
 *            array so cookies are never silently overwritten.
 */
class Response
{
    protected int    $status;
    protected array  $headers;
    protected array  $cookieHeaders = [];   // SEC-2: dedicated cookie bucket
    protected string $body;

    public function __construct(string $body = '', int $status = 200, array $headers = [])
    {
        $this->body   = $body;
        $this->status = $status;
        $this->headers = [];

        foreach ($headers as $key => $value) {
            $this->setHeader($key, $value);
        }
    }

    public static function make(string $body = '', int $status = 200, array $headers = []): static
    {
        return new static($body, $status, $headers);
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): static
    {
        $headers['Content-Type'] = 'application/json';
        return new static(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $status,
            $headers
        );
    }

    public static function redirect(string $url, int $status = 302): static
    {
        $safeUrl = self::sanitizeRedirectUrl($url);
        return new static('', $status, ['Location' => $safeUrl]);
    }

    /**
     * Set a response header.
     * Set-Cookie is handled separately via addCookie().
     * CRLF injection stripped on all values.
     */
    public function setHeader(string $key, string $value): static
    {
        $key   = preg_replace('/[\r\n]+/', '', $key);
        $value = preg_replace('/[\r\n]+/', '', $value);

        if ($key === '') {
            return $this;
        }

        // SEC-2: Route Set-Cookie to its own list
        if (strtolower($key) === 'set-cookie') {
            return $this->addCookie($value);
        }

        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * Add a Set-Cookie header without overwriting previous ones.
     */
    public function addCookie(string $cookieString): static
    {
        $cookieString = preg_replace('/[\r\n]+/', '', $cookieString);
        if ($cookieString !== '') {
            $this->cookieHeaders[] = $cookieString;
        }
        return $this;
    }

    public function getHeader(string $key, ?string $default = null): ?string
    {
        return $this->headers[$key] ?? $default;
    }

    public function withStatus(int $status): static
    {
        $clone = clone $this;
        $clone->status = $status;
        return $clone;
    }

    public function status(): int   { return $this->status; }
    public function body(): string  { return $this->body; }
    public function headers(): array { return $this->headers; }
    public function cookies(): array { return $this->cookieHeaders; }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);

            foreach ($this->headers as $key => $value) {
                header("{$key}: {$value}", true);
            }

            // SEC-2: each cookie is sent as a separate header (replace=false)
            foreach ($this->cookieHeaders as $cookie) {
                header('Set-Cookie: ' . $cookie, false);
            }

            header_remove('X-Powered-By');
        }

        echo $this->body;
    }

    protected static function sanitizeRedirectUrl(string $url): string
    {
        $stripped = ltrim($url);
        if (preg_match('/^(javascript|data|vbscript|file):/i', $stripped)) {
            return '/';
        }
        if (!str_starts_with($stripped, '/') && !preg_match('/^https?:\/\//i', $stripped)) {
            return '/';
        }
        return $url;
    }
}
