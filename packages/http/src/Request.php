<?php

namespace Kyqo\Http;

/**
 * HTTP Request
 *
 * FIX m2: capture() no longer reads php://input unboundedly into memory.
 * Before opening the stream, CONTENT_LENGTH is checked against the
 * configured $maxBodySize (default 10 MB). If the declared size already
 * exceeds the limit, the body is set to '' and the request continues —
 * ValidateBodySize middleware will then reject it with HTTP 413.
 * For streaming bodies without CONTENT_LENGTH the read is bounded via
 * stream_copy_to_stream with a hard cap of maxBodySize + 1 bytes, so a
 * truncated (oversized) body is written to a temp stream and returned as
 * a string; again ValidateBodySize will reject it on the actual length.
 *
 * This eliminates the RAM exhaustion window that existed between
 * Request::capture() and the ValidateBodySize middleware execution.
 */
class Request
{
    protected array  $query;
    protected array  $input;
    protected array  $files;
    protected array  $server;
    protected array  $headers;
    protected string $content;
    protected array  $customAttributes = [];

    protected int   $maxBodySize    = 10 * 1024 * 1024; // 10 MB
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

    /**
     * FIX m2: bounded php://input read.
     *
     * 1. If Content-Length is declared and already exceeds the cap, skip reading
     *    entirely (body = ''). ValidateBodySize will issue 413.
     * 2. Otherwise read at most (maxBodySize + 1) bytes via stream_copy_to_stream.
     *    The +1 ensures that an exactly-at-limit body is accepted while an
     *    over-limit body is detectable by ValidateBodySize (strlen > maxBodySize).
     * 3. For non-body methods (GET, HEAD, OPTIONS) no read is attempted.
     */
    public static function capture(): static
    {
        $server = $_SERVER;
        $method = strtoupper($server['REQUEST_METHOD'] ?? 'GET');

        $content = '';
        if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            $content = static::readBoundedInput(
                (int) ($server['CONTENT_LENGTH'] ?? -1),
                10 * 1024 * 1024   // same constant as $maxBodySize
            );
        }

        return new static(
            $_GET,
            $_POST,
            $_FILES,
            $server,
            $content
        );
    }

    /**
     * Read php://input with a hard memory cap.
     *
     * @param int $declaredLength  Value of Content-Length header (-1 if absent).
     * @param int $maxBytes        Maximum bytes to accept.
     * @return string              Raw body, possibly truncated (ValidateBodySize will reject if too long).
     */
    protected static function readBoundedInput(int $declaredLength, int $maxBytes): string
    {
        // Fast path: Content-Length already over cap — don't read at all.
        if ($declaredLength > $maxBytes) {
            return '';
        }

        $input = fopen('php://input', 'r');
        if ($input === false) {
            return '';
        }

        $temp = fopen('php://temp', 'w+b');
        if ($temp === false) {
            fclose($input);
            return '';
        }

        // Read at most maxBytes + 1 bytes.
        // If the body is exactly maxBytes the whole thing is stored;
        // if it's longer, ValidateBodySize will see strlen > maxBytes and reject.
        stream_copy_to_stream($input, $temp, $maxBytes + 1);
        fclose($input);

        rewind($temp);
        $content = stream_get_contents($temp);
        fclose($temp);

        return $content === false ? '' : $content;
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
        return ($this->isSecure() ? 'https' : 'http') . '://' . $this->host() . $this->uri();
    }

    public function host(): string
    {
        return $this->getValidatedHost();
    }

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
