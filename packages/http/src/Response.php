<?php

namespace Kyqo\Http;

/**
 * Kyqo HTTP Response
 *
 * Represents and sends an outgoing HTTP response.
 * Supports status codes, headers, and body content.
 *
 * SECURITY: setHeader() strips newlines from header values to prevent
 * HTTP response splitting (CRLF injection).
 */
class Response
{
    protected int $status;
    protected array $headers;
    protected string $body;

    public function __construct(string $body = '', int $status = 200, array $headers = [])
    {
        $this->body    = $body;
        $this->status  = $status;
        $this->headers = [];

        // Use setHeader to sanitize all initial headers
        foreach ($headers as $key => $value) {
            $this->setHeader($key, $value);
        }
    }

    /**
     * Create a new response instance.
     */
    public static function make(string $body = '', int $status = 200, array $headers = []): static
    {
        return new static($body, $status, $headers);
    }

    /**
     * Create a JSON response.
     */
    public static function json(mixed $data, int $status = 200, array $headers = []): static
    {
        $headers['Content-Type'] = 'application/json';
        return new static(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $status,
            $headers
        );
    }

    /**
     * Create a redirect response.
     */
    public static function redirect(string $url, int $status = 302): static
    {
        // Sanitize redirect URL to prevent open redirect via javascript: or data: URIs
        $safeUrl = self::sanitizeRedirectUrl($url);
        return new static('', $status, ['Location' => $safeUrl]);
    }

    /**
     * Set a response header.
     * SECURITY: Strips CR/LF characters to prevent HTTP Response Splitting.
     */
    public function setHeader(string $key, string $value): static
    {
        // Strip CRLF injection
        $key   = preg_replace('/[\r\n]+/', '', $key);
        $value = preg_replace('/[\r\n]+/', '', $value);

        if ($key !== '') {
            $this->headers[$key] = $value;
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

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Send the response to the client.
     */
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $key => $value) {
                header("{$key}: {$value}", true);
            }
            // Always remove X-Powered-By at the PHP level too
            header_remove('X-Powered-By');
        }

        echo $this->body;
    }

    /**
     * SECURITY: Prevent open redirect to javascript:, data:, or protocol-less URIs.
     */
    protected static function sanitizeRedirectUrl(string $url): string
    {
        $stripped = ltrim($url);
        // Block javascript:, data:, vbscript: and similar dangerous schemes
        if (preg_match('/^(javascript|data|vbscript|file):/i', $stripped)) {
            return '/';
        }
        // Allow only http, https, or relative URLs
        if (!str_starts_with($stripped, '/') && !preg_match('/^https?:\/\//i', $stripped)) {
            return '/';
        }
        return $url;
    }
}
