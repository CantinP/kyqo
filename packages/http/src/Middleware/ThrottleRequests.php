<?php

namespace Kyqo\Http\Middleware;

use Kyqo\Http\Request;
use Kyqo\Http\Response;

/**
 * Rate Limiting Middleware
 *
 * SEC-3 FIX (maintained): Atomic read+write via flock(LOCK_EX).
 * SEC-V4-2 FIX: umask(0077) is applied BEFORE fopen() so the file is
 *               created with 0600 from the start — no race window.
 * MINOR FIX (maintained): Probabilistic GC sweep of expired entries.
 *
 * FIX AUDIT-6: Rate-limit key now prefers the authenticated user ID over
 *              the raw IP address when the request is authenticated.
 *              This prevents a single user from bypassing the limit by
 *              rotating IPs, and avoids penalising all users behind a NAT.
 *
 *              Resolution order:
 *                1. auth()->id()  — if Auth is available and user is logged in
 *                2. $request->ip() — fallback for unauthenticated requests
 */
class ThrottleRequests
{
    protected int    $maxAttempts;
    protected int    $decayMinutes;
    protected string $storePath;
    protected int    $gcDivisor = 100;

    public function __construct(int $maxAttempts = 60, int $decayMinutes = 1)
    {
        $this->maxAttempts  = $maxAttempts;
        $this->decayMinutes = $decayMinutes;
        $this->storePath    = sys_get_temp_dir() . '/kyqo_throttle';

        if (!is_dir($this->storePath)) {
            mkdir($this->storePath, 0700, true);
        }
    }

    public function handle(Request $request, \Closure $next): mixed
    {
        if (random_int(1, $this->gcDivisor) === 1) {
            $this->pruneExpired();
        }

        $key  = $this->resolveKey($request);
        $file = $this->storePath . '/' . $key . '.json';
        $now  = time();

        $oldUmask = umask(0077);
        $fh = fopen($file, 'c+');
        umask($oldUmask);

        if ($fh === false) {
            return $next($request);
        }

        flock($fh, LOCK_EX);

        $raw  = stream_get_contents($fh);
        $data = is_string($raw) ? json_decode($raw, true) : null;

        if (!is_array($data) || ($data['reset_at'] ?? 0) <= $now) {
            $data = ['count' => 0, 'reset_at' => $now + ($this->decayMinutes * 60)];
        }

        $data['count']++;

        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($data));
        fflush($fh);
        flock($fh, LOCK_UN);
        fclose($fh);

        $remaining  = max(0, $this->maxAttempts - $data['count']);
        $retryAfter = max(0, $data['reset_at'] - $now);

        if ($data['count'] > $this->maxAttempts) {
            return Response::make(
                json_encode(['message' => 'Too Many Requests.', 'retry_after' => $retryAfter]),
                429,
                [
                    'Content-Type'          => 'application/json',
                    'Retry-After'           => (string) $retryAfter,
                    'X-RateLimit-Limit'     => (string) $this->maxAttempts,
                    'X-RateLimit-Remaining' => '0',
                    'X-RateLimit-Reset'     => (string) $data['reset_at'],
                ]
            );
        }

        $response = $next($request);

        if ($response instanceof Response) {
            $response->setHeader('X-RateLimit-Limit',     (string) $this->maxAttempts);
            $response->setHeader('X-RateLimit-Remaining', (string) $remaining);
            $response->setHeader('X-RateLimit-Reset',     (string) $data['reset_at']);
        }

        return $response;
    }

    /**
     * FIX AUDIT-6: Prefer authenticated user ID over IP.
     *
     * Uses the 'auth' alias from the Application container.
     * If Auth is unavailable or the user is a guest, falls back to IP.
     */
    protected function resolveKey(Request $request): string
    {
        $identity = null;

        try {
            $auth     = \Kyqo\Core\Application::getInstance()->make('auth');
            $userId   = $auth->id();
            if ($userId !== null) {
                $identity = 'user:' . $userId;
            }
        } catch (\Throwable) {
            // Auth not available — use IP.
        }

        if ($identity === null) {
            $identity = 'ip:' . ($request->ip() ?? '0.0.0.0');
        }

        $path = hash('sha256', $request->path());
        return hash('sha256', $identity . '|' . $path);
    }

    protected function pruneExpired(): void
    {
        $now   = time();
        $files = glob($this->storePath . '/*.json') ?: [];
        foreach ($files as $file) {
            $data = @json_decode((string) @file_get_contents($file), true);
            if (!is_array($data) || ($data['reset_at'] ?? 0) <= $now) {
                @unlink($file);
            }
        }
    }
}
