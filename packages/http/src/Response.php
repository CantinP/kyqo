<?php

namespace Kyqo\Http;

/**
 * Kyqo HTTP Response
 *
 * Represents and sends an HTTP response including status, headers and body.
 */
class Response
{
    protected mixed $content;
    protected int $status;
    protected array $headers;

    protected static array $statusTexts = [
        200 => 'OK', 201 => 'Created', 204 => 'No Content',
        301 => 'Moved Permanently', 302 => 'Found',
        400 => 'Bad Request', 401 => 'Unauthorized', 403 => 'Forbidden',
        404 => 'Not Found', 405 => 'Method Not Allowed', 422 => 'Unprocessable Entity',
        429 => 'Too Many Requests', 500 => 'Internal Server Error',
    ];

    public function __construct(mixed $content = '', int $status = 200, array $headers = [])
    {
        $this->content = $content;
        $this->status  = $status;
        $this->headers = array_merge(['Content-Type' => 'text/html; charset=UTF-8'], $headers);
    }

    public static function make(mixed $content = '', int $status = 200, array $headers = []): static
    {
        return new static($content, $status, $headers);
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): static
    {
        $headers['Content-Type'] = 'application/json';
        return new static(json_encode($data), $status, $headers);
    }

    public static function redirect(string $url, int $status = 302): static
    {
        return new static('', $status, ['Location' => $url]);
    }

    public function setContent(mixed $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function setStatus(int $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function setHeader(string $key, string $value): static
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function withHeaders(array $headers): static
    {
        foreach ($headers as $key => $value) {
            $this->headers[$key] = $value;
        }
        return $this;
    }

    public function getContent(): mixed  { return $this->content; }
    public function getStatus(): int     { return $this->status; }
    public function getHeaders(): array  { return $this->headers; }

    /**
     * Send the response to the client.
     */
    public function send(): void
    {
        $statusText = static::$statusTexts[$this->status] ?? 'Unknown';
        header("HTTP/1.1 {$this->status} {$statusText}", true, $this->status);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}", true);
        }

        echo $this->content;
    }
}
