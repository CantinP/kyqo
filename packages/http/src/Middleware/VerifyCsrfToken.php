<?php

namespace Kyqo\Http\Middleware;

use Kyqo\Http\Request;
use Kyqo\Http\Response;

class VerifyCsrfToken
{
    protected array $except = [
        'api/*',
        'webhooks/*',
    ];

    protected array $verbs = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, \Closure $next): mixed
    {
        if ($this->isReading($request) || $this->inExceptArray($request)) {
            return $this->addCookieToResponse($request, $next($request));
        }

        if (!$this->tokensMatch($request)) {
            return Response::make(
                json_encode(['message' => 'CSRF token mismatch.']),
                419,
                ['Content-Type' => 'application/json']
            );
        }

        self::rotateToken();

        return $this->addCookieToResponse($request, $next($request));
    }

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function getToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        if (!isset($_SESSION['_kyqo_csrf_token'])) {
            $_SESSION['_kyqo_csrf_token'] = self::generateToken();
        }

        return $_SESSION['_kyqo_csrf_token'];
    }

    public static function rotateToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $_SESSION['_kyqo_csrf_token'] = self::generateToken();
        return $_SESSION['_kyqo_csrf_token'];
    }

    protected function tokensMatch(Request $request): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $sessionToken = $_SESSION['_kyqo_csrf_token'] ?? null;

        $requestToken = $request->get('_token')
            ?? $request->header('x-csrf-token')
            ?? $request->header('x-xsrf-token');

        if (!$sessionToken || !$requestToken) {
            return false;
        }

        return hash_equals($sessionToken, $requestToken);
    }

    protected function isReading(Request $request): bool
    {
        return in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);
    }

    protected function inExceptArray(Request $request): bool
    {
        foreach ($this->except as $pattern) {
            if (fnmatch($pattern, ltrim($request->path(), '/'))) {
                return true;
            }
        }

        return false;
    }

    protected function addCookieToResponse(Request $request, mixed $response): mixed
    {
        if ($response instanceof Response) {
            $token = self::getToken();
            $response->setHeader(
                'Set-Cookie',
                'XSRF-TOKEN=' . urlencode($token) . '; Path=/; SameSite=Strict; HttpOnly; Secure'
            );
        }

        return $response;
    }
}
