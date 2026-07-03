<?php

namespace Kyqo\Http;

class Request
{
    protected array  $query;
    protected array  $input;
    protected array  $files;
    protected array  $server;
    protected array  $headers;
    protected string $content;
    protected array  $customAttributes = [];

    protected int   $maxBodySize    = 10 * 1024 * 1024;
    protected array $allowedMethods = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

    public function __construct(
        array  $query   = [],
        array  $input   = [],
        array  $files   = [],
        array  $server  = [],
        string $content = ''
    ) {
        $this->query   = $query;
        $this->input   = $input;
        $this->files   = $files;
        $this->server  = $server;
        $this->headers = $this->parseHeaders($server);
        $this->content = $content;
    }

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
        $method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
        return in_array($method, $this->allowedMethods, true) ? $method : 'GET';
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
        return ($this->isSecure() ? 'https' : 'http') . '://' . $this->getValidatedHost() . $this->uri();
    }

    /**
     * Return the raw request body (used by ValidateBodySize for SEC-6).
     */
    public function rawBody(): string
    {
        return $this->content;
    }

    protected function getValidatedHost(): string
    {
        $host = $this->server['HTTP_HOST'] ?? 'localhost';
        return preg_match('/^[a-zA-Z0-9\-\.\[\]:]+$/', $host) ? $host : 'localhost';
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

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    public function all(): array  { return array_merge($this->query, $this->input); }

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

    public function query(?string $key = null, mixed $default = null): mixed
    {
        return is_null($key) ? $this->query : ($this->query[$key] ?? $default);
    }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        if (strlen($this->content) > $this->maxBodySize) {
            throw new \OverflowException('JSON body exceeds maximum allowed size.');
        }

        $data = json_decode($this->content, true);
        if (!is_array($data)) {
            $data = [];
        }

        return is_null($key) ? $data : ($data[$key] ?? $default);
    }

    public function header(string $key, mixed $default = null): mixed
    {
        return $this->headers[strtolower($key)] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('authorization', '');
        if (str_starts_with($auth, 'Bearer ')) {
            $token = substr($auth, 7);
            if (preg_match('/^[A-Za-z0-9\-_\.~\+\/]+=*$/', $token)) {
                return $token;
            }
        }
        return null;
    }

    public function ip(): ?string
    {
        $remoteAddr = $this->server['REMOTE_ADDR'] ?? null;

        if ($remoteAddr && !filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
            return null;
        }

        $trustedProxies = defined('KYQO_TRUSTED_PROXIES') ? KYQO_TRUSTED_PROXIES : [];

        if (!empty($trustedProxies) && $remoteAddr && in_array($remoteAddr, $trustedProxies, true)) {
            $forwarded = $this->header('x-forwarded-for');
            if ($forwarded) {
                $ips = array_map('trim', explode(',', $forwarded));
                foreach ($ips as $ip) {
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $ip;
                    }
                }
            }
        }

        return $remoteAddr;
    }

    public function userAgent(): ?string { return $this->header('user-agent'); }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->customAttributes[$key] = $value;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->customAttributes[$key] ?? $default;
    }

    protected function parseHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $header = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$header] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $headers[strtolower(str_replace('_', '-', $key))] = $value;
            }
        }
        return $headers;
    }
}
