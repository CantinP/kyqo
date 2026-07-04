<?php

namespace Kyqo\Http\Middleware;

use Kyqo\Http\Request;
use Kyqo\Http\Response;

/**
 * CSRF Token Middleware
 *
 * Automatically active on all non-GET/HEAD/OPTIONS routes that are not
 * in the $except list (api/*, webhooks/*).
 *
 * The CSRF token is stored in the session under '_kyqo_csrf_token' and
 * is also sent back to the browser as the XSRF-TOKEN cookie (readable
 * by JS / Axios / Angular).
 *
 * Forms must include:
 *   <?= csrf_field() ?>
 *   → <input type="hidden" name="_token" value="...">
 *
 * SPAs / AJAX must send one of:
 *   X-CSRF-TOKEN: {token}
 *   X-XSRF-TOKEN: {cookie value}
 */
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
            return $this->addCookieToResponse($next($request), $request);
        }

        if (!$this->tokensMatch($request)) {
            return Response::make(
                json_encode(['message' => 'CSRF token mismatch.']),
                419,
                ['Content-Type' => 'application/json']
            );
        }

        return $this->addCookieToResponse($next($request), $request);
    }

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function getToken(): string
    {
        self::ensureSession();
        if (!isset($_SESSION['_kyqo_csrf_token'])) {
            $_SESSION['_kyqo_csrf_token'] = self::generateToken();
        }
        return $_SESSION['_kyqo_csrf_token'];
    }

    public static function rotateToken(): string
    {
        self::ensureSession();
        $_SESSION['_kyqo_csrf_token'] = self::generateToken();
        return $_SESSION['_kyqo_csrf_token'];
    }

    private static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    protected function tokensMatch(Request $request): bool
    {
        self::ensureSession();

        $sessionToken = $_SESSION['_kyqo_csrf_token'] ?? null;
        $requestToken = $request->get('_token')
            ?? $request->header('x-csrf-token')
            ?? $request->header('x-xsrf-token');

        if (!$sessionToken || !$requestToken) {
            return false;
        }

        return hash_equals($sessionToken, (string) $requestToken);
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

    protected function addCookieToResponse(mixed $response, Request $request): mixed
    {
        if ($response instanceof Response) {
            $token  = self::getToken();
            $secure = $request->isSecure() ? '; Secure' : '';
            $response->addCookie(
                'XSRF-TOKEN=' . urlencode($token) . '; Path=/; SameSite=Strict' . $secure
            );
        }
        return $response;
    }
}
