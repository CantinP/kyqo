<?php

namespace Kyqo\Http;

/**
 * Kyqo HTTP Request
 *
 * Represents and wraps an incoming HTTP request. Provides clean access
 * to inputs, headers, files, query params, body, method and URI.
 */
class Request
{
    protected array $query;
    protected array $input;
    protected array $files;
    protected array $server;
    protected array $headers;
    protected string $content;

    public function __construct(
        array $query = [],
        array $input = [],
        array $files = [],
        array $server = [],
        string $content = ''
    ) {
        $this->query   = $query;
        $this->input   = $input;
        $this->files   = $files;
        $this->server  = $server;
        $this->headers = $this->parseHeaders($server);
        $this->content = $content;
    }

    /**
     * Create a request from PHP superglobals.
     */
    public static function capture(): static
    {
        return new static(
            $_GET,
            $_POST,
            $_FILES,
            $_SERVER,
            file_get_contents('php://input') ?: ''
        );
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function uri(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $pos = strpos($uri, '?');
        return $pos !== false ? substr($uri, 0, $pos) : $uri;
    }

    public function path(): string
    {
        return '/' . trim($this->uri(), '/');
    }

    public function url(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        $host   = $this->server['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . $this->uri();
    }

    public function isSecure(): bool
    {
        return ($this->server['HTTPS'] ?? 'off') !== 'off';
    }

    public function isMethod(string $method): bool
    {
        return $this->method() === strtoupper($method);
    }

    public function isAjax(): bool
    {
        return ($this->server['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    public function wantsJson(): bool
    {
        $accept = $this->header('accept', '');
        return str_contains($accept, '/json') || str_contains($accept, '+json');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->input[$key] ?? $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->input);
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    public function except(array $keys): array
    {
        return array_diff_key($this->all(), array_flip($keys));
    }

    public function has(string $key): bool
    {
        return isset($this->input[$key]) || isset($this->query[$key]);
    }

    public function filled(string $key): bool
    {
        return $this->has($key) && $this->get($key) !== null && $this->get($key) !== '';
    }

    public function query(string $key = null, mixed $default = null): mixed
    {
        if (is_null($key)) return $this->query;
        return $this->query[$key] ?? $default;
    }

    public function json(string $key = null, mixed $default = null): mixed
    {
        $data = json_decode($this->content, true) ?? [];
        if (is_null($key)) return $data;
        return $data[$key] ?? $default;
    }

    public function header(string $key, mixed $default = null): mixed
    {
        return $this->headers[strtolower($key)] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('authorization', '');
        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return null;
    }

    public function ip(): ?string
    {
        return $this->server['REMOTE_ADDR'] ?? null;
    }

    public function userAgent(): ?string
    {
        return $this->header('user-agent');
    }

    protected function parseHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $header = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$header] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                $headers[strtolower(str_replace('_', '-', $key))] = $value;
            }
        }
        return $headers;
    }
}
