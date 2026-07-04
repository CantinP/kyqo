<?php

namespace Kyqo\Http;

/**
 * HTTP Request
 *
 * Wraps PHP superglobals into an OOP interface with helpers for input,
 * file uploads, headers, JSON, validation, and route parameters.
 *
 * FIX AUDIT-3: method() only honours the _method override when the real
 * HTTP method is POST, preventing spoofing via GET query strings.
 */
class Request
{
    protected array $query;
    protected array $post;
    protected array $server;
    protected array $cookies;
    protected array $files;
    protected array $headers;
    protected array $routeParams = [];
    protected ?array $jsonPayload = null;
    protected array $mergedInput = [];

    public function __construct(
        array $query   = [],
        array $post    = [],
        array $server  = [],
        array $cookies = [],
        array $files   = []
    ) {
        $this->query   = $query;
        $this->post    = $post;
        $this->server  = $server;
        $this->cookies = $cookies;
        $this->files   = $files;
        $this->headers = $this->extractHeaders($server);
    }

    public static function capture(): static
    {
        return new static(
            $_GET,
            $_POST,
            $_SERVER,
            $_COOKIE,
            $_FILES
        );
    }

    // ---- Method & URI -------------------------------------------------------

    /**
     * FIX AUDIT-3: _method spoofing is only honoured when the real request
     * method is POST.  Honouring it on GET requests would allow attackers to
     * trigger state-changing routes via a crafted link / CSRF vector.
     */
    public function method(): string
    {
        $real = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');

        if ($real === 'POST') {
            $override = $this->post['_method'] ?? null;
            if ($override && in_array(strtoupper($override), ['PUT', 'PATCH', 'DELETE'], true)) {
                return strtoupper($override);
            }
        }

        return $real;
    }

    public function uri(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        return '/' . ltrim(strtok($uri, '?') ?: '/', '/');
    }

    public function path(): string { return $this->uri(); }
    public function url(): string  { return $this->scheme() . '://' . $this->host() . $this->uri(); }
    public function fullUrl(): string { return $this->scheme() . '://' . $this->host() . ($this->server['REQUEST_URI'] ?? '/'); }

    public function scheme(): string { return (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off') ? 'https' : 'http'; }
    public function host(): string   { return $this->server['HTTP_HOST'] ?? 'localhost'; }
    public function isSecure(): bool { return $this->scheme() === 'https'; }

    /**
     * FIX D4: X-Forwarded-For can be a comma-separated list; take only the first entry.
     */
    public function ip(): ?string
    {
        $xff = $this->server['HTTP_X_FORWARDED_FOR'] ?? null;
        if ($xff !== null) {
            return trim(explode(',', $xff)[0]);
        }
        return $this->server['REMOTE_ADDR'] ?? null;
    }

    // ---- Input --------------------------------------------------------------

    public function all(): array
    {
        return array_merge($this->query, $this->post, $this->mergedInput, $this->decodeJson());
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function get(string $key, mixed $default = null): mixed { return $this->input($key, $default); }

    public function only(array $keys): array
    {
        $all = $this->all();
        return array_intersect_key($all, array_flip($keys));
    }

    public function except(array $keys): array
    {
        $all = $this->all();
        foreach ($keys as $k) unset($all[$k]);
        return $all;
    }

    public function has(string ...$keys): bool
    {
        $all = $this->all();
        foreach ($keys as $k) {
            if (!array_key_exists($k, $all)) return false;
        }
        return true;
    }

    public function filled(string ...$keys): bool
    {
        foreach ($keys as $k) {
            $v = $this->input($k);
            if ($v === null || $v === '') return false;
        }
        return true;
    }

    public function missing(string $key): bool { return !$this->has($key); }

    public function merge(array $data): static
    {
        $this->mergedInput = array_merge($this->mergedInput, $data);
        return $this;
    }

    public function replace(array $data): static
    {
        $this->mergedInput = $data;
        return $this;
    }

    // ---- Typed helpers ------------------------------------------------------

    public function string(string $key, string $default = ''): string  { return (string) ($this->input($key) ?? $default); }
    public function integer(string $key, int $default = 0): int         { return (int)    ($this->input($key) ?? $default); }
    public function float(string $key, float $default = 0.0): float     { return (float)  ($this->input($key) ?? $default); }
    public function boolean(string $key, bool $default = false): bool   { return filter_var($this->input($key) ?? $default, FILTER_VALIDATE_BOOLEAN); }
    public function array(string $key, array $default = []): array      { $v = $this->input($key); return is_array($v) ? $v : $default; }

    // ---- JSON ---------------------------------------------------------------

    public function isJson(): bool { return str_contains($this->header('Content-Type', ''), 'application/json'); }
    public function wantsJson(): bool
    {
        $accept = $this->header('Accept', '');
        return str_contains($accept, '/json') || str_contains($accept, '+json');
    }

    protected function decodeJson(): array
    {
        if (!$this->isJson()) return [];
        if ($this->jsonPayload !== null) return $this->jsonPayload;
        $body = file_get_contents('php://input');
        $this->jsonPayload = is_string($body) ? (json_decode($body, true) ?? []) : [];
        return $this->jsonPayload;
    }

    public function json(string $key = null, mixed $default = null): mixed
    {
        $data = $this->decodeJson();
        if ($key === null) return $data;
        return $data[$key] ?? $default;
    }

    // ---- Headers ------------------------------------------------------------

    public function header(string $key, mixed $default = null): mixed
    {
        $key = strtolower($key);
        return $this->headers[$key] ?? $default;
    }

    public function headers(): array { return $this->headers; }

    public function bearerToken(): ?string
    {
        $auth = $this->header('authorization', '');
        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return null;
    }

    protected function extractHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $name = strtolower(str_replace('_', '-', $key));
                $headers[$name] = $value;
            }
        }
        return $headers;
    }

    // ---- Cookies & Files ----------------------------------------------------

    public function cookie(string $key, mixed $default = null): mixed { return $this->cookies[$key] ?? $default; }
    public function cookies(): array { return $this->cookies; }

    public function file(string $key): mixed { return $this->files[$key] ?? null; }
    public function hasFile(string $key): bool { return isset($this->files[$key]) && !empty($this->files[$key]['tmp_name']); }
    public function allFiles(): array { return $this->files; }

    // ---- Route params -------------------------------------------------------

    public function setRouteParams(array $params): static
    {
        $this->routeParams = $params;
        return $this;
    }

    public function route(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    // ---- Misc ---------------------------------------------------------------

    public function isMethod(string $method): bool { return $this->method() === strtoupper($method); }
    public function isGet(): bool    { return $this->isMethod('GET'); }
    public function isPost(): bool   { return $this->isMethod('POST'); }
    public function isPut(): bool    { return $this->isMethod('PUT'); }
    public function isPatch(): bool  { return $this->isMethod('PATCH'); }
    public function isDelete(): bool { return $this->isMethod('DELETE'); }
    public function isAjax(): bool   { return strtolower($this->header('x-requested-with', '')) === 'xmlhttprequest'; }

    public function expectsJson(): bool { return $this->wantsJson() || $this->isJson(); }

    /**
     * FIX D2: pass $validator->errors() (array) to ValidationException,
     * not the Validator object itself.
     */
    public function validate(array $rules): array
    {
        $factory   = \Kyqo\Core\Application::getInstance()->make(\Kyqo\Http\Validation\ValidatorFactory::class);
        $validator = $factory->make($this->all(), $rules);
        if ($validator->fails()) {
            throw new \Kyqo\Http\Validation\ValidationException($validator->errors());
        }
        return $validator->validated();
    }

    public function __toString(): string
    {
        return $this->method() . ' ' . $this->uri();
    }
}
