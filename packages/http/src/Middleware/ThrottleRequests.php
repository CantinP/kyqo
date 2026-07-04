<?php

namespace Kyqo\Http\Middleware;

use Kyqo\Http\Request;
use Kyqo\Http\Response;

/**
 * ThrottleRequests — rate-limiting middleware.
 *
 * Limits the number of requests a client can make within a time window.
 * Uses APCu when available, falls back to a file-based counter.
 *
 * Usage in routes:
 *   $router->middleware('throttle:60,1')->group(function () { ... });
 *
 * Registration in app/Http/Kernel.php:
 *   protected array $routeMiddleware = [
 *       'throttle' => \Kyqo\Http\Middleware\ThrottleRequests::class,
 *   ];
 *
 * The parameter format is  "maxAttempts,decayMinutes" (both optional, defaults: 60,1).
 */
class ThrottleRequests
{
    private int    $maxAttempts;
    private int    $decaySeconds;
    private string $storageDir;

    public function __construct(int $maxAttempts = 60, int $decayMinutes = 1)
    {
        $this->maxAttempts  = $maxAttempts;
        $this->decaySeconds = $decayMinutes * 60;

        // Fallback file-based store
        $base = function_exists('app') ? app()->storagePath('framework/throttle') : sys_get_temp_dir() . '/kyqo_throttle';
        $this->storageDir = $base;
    }

    public function handle(Request $request, \Closure $next, int $maxAttempts = 0, int $decayMinutes = 0): Response
    {
        if ($maxAttempts > 0) $this->maxAttempts  = $maxAttempts;
        if ($decayMinutes > 0) $this->decaySeconds = $decayMinutes * 60;

        $key = $this->resolveKey($request);

        if ($this->tooManyAttempts($key)) {
            return $this->buildTooManyAttemptsResponse($key);
        }

        $this->hit($key);

        $response = $next($request);

        return $this->addHeaders($response, $key);
    }

    // ── Key ─────────────────────────────────────────────────────────────────

    protected function resolveKey(Request $request): string
    {
        return 'throttle:' . sha1($request->ip() . '|' . $request->path());
    }

    // ── Storage ──────────────────────────────────────────────────────────

    protected function tooManyAttempts(string $key): bool
    {
        return $this->getAttempts($key) >= $this->maxAttempts;
    }

    protected function hit(string $key): void
    {
        if (function_exists('apcu_fetch') && ini_get('apc.enabled')) {
            $current = apcu_fetch($key);
            if ($current === false) {
                apcu_store($key, 1, $this->decaySeconds);
            } else {
                apcu_inc($key);
            }
            return;
        }

        // File fallback
        $this->fileHit($key);
    }

    protected function getAttempts(string $key): int
    {
        if (function_exists('apcu_fetch') && ini_get('apc.enabled')) {
            $val = apcu_fetch($key);
            return $val === false ? 0 : (int) $val;
        }
        return $this->fileAttempts($key);
    }

    protected function getExpiresAt(string $key): int
    {
        if (function_exists('apcu_key_info') && ini_get('apc.enabled')) {
            $info = apcu_key_info($key);
            return $info ? (int) ($info['creation_time'] + $info['ttl']) : time() + $this->decaySeconds;
        }
        return $this->fileExpiresAt($key);
    }

    // ── File fallback ─────────────────────────────────────────────────────

    private function filePath(string $key): string
    {
        if (!is_dir($this->storageDir)) @mkdir($this->storageDir, 0755, true);
        return $this->storageDir . '/' . sha1($key) . '.throttle';
    }

    private function fileAttempts(string $key): int
    {
        $file = $this->filePath($key);
        if (!file_exists($file)) return 0;
        [$count, $expiresAt] = explode('|', file_get_contents($file));
        if (time() > (int) $expiresAt) { @unlink($file); return 0; }
        return (int) $count;
    }

    private function fileExpiresAt(string $key): int
    {
        $file = $this->filePath($key);
        if (!file_exists($file)) return time() + $this->decaySeconds;
        [, $expiresAt] = explode('|', file_get_contents($file));
        return (int) $expiresAt;
    }

    private function fileHit(string $key): void
    {
        $file      = $this->filePath($key);
        $count     = $this->fileAttempts($key);
        $expiresAt = $count === 0 ? time() + $this->decaySeconds : $this->fileExpiresAt($key);
        file_put_contents($file, ($count + 1) . '|' . $expiresAt, LOCK_EX);
    }

    // ── Response helpers ──────────────────────────────────────────────────

    protected function buildTooManyAttemptsResponse(string $key): Response
    {
        $retryAfter = max(0, $this->getExpiresAt($key) - time());
        $response   = new Response(
            json_encode(['message' => 'Too Many Requests']),
            429,
            ['Content-Type' => 'application/json', 'Retry-After' => (string) $retryAfter]
        );
        return $response;
    }

    protected function addHeaders(Response $response, string $key): Response
    {
        $remaining = max(0, $this->maxAttempts - $this->getAttempts($key));
        $response->header('X-RateLimit-Limit',     (string) $this->maxAttempts);
        $response->header('X-RateLimit-Remaining', (string) $remaining);
        return $response;
    }
}
