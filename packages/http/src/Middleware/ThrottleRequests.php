<?php

namespace Kyqo\Http\Middleware;

use Kyqo\Http\Request;
use Kyqo\Http\Response;

/**
 * Rate Limiting Middleware
 *
 * FIX SEC-3: Read + write are now protected by an exclusive flock() so
 *            concurrent requests cannot both read count=N and both pass.
 * MINOR FIX: Expired throttle files are pruned on each request (probabilistic).
 */
class ThrottleRequests
{
    protected int    $maxAttempts;
    protected int    $decayMinutes;
    protected string $storePath;

    /** Probability (1 in N) of running the GC sweep on any given request. */
    protected int $gcDivisor = 100;

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
        // Probabilistic GC: prune expired files ~1% of requests
        if (random_int(1, $this->gcDivisor) === 1) {
            $this->pruneExpired();
        }

        $key  = $this->resolveKey($request);
        $file = $this->storePath . '/' . $key . '.json';
        $now  = time();

        // FIX SEC-3: Atomic read+increment via exclusive file lock
        $fh = fopen($file, 'c+');
        if ($fh === false) {
            // Cannot open throttle file — fail open (do not block the request)
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

        @chmod($file, 0600);

        $remaining  = max(0, $this->maxAttempts - $data['count']);
        $retryAfter = max(0, $data['reset_at'] - $now);

        if ($data['count'] > $this->maxAttempts) {
            return Response::make(
                json_encode(['message' => 'Too Many Requests.', 'retry_after' => $retryAfter]),
                429,
                [
                    'Content-Type'       => 'application/json',
                    'Retry-After'        => (string) $retryAfter,
                    'X-RateLimit-Limit'  => (string) $this->maxAttempts,
                    'X-RateLimit-Remaining' => '0',
                    'X-RateLimit-Reset'  => (string) $data['reset_at'],
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

    protected function resolveKey(Request $request): string
    {
        $ip   = $request->ip() ?? '0.0.0.0';
        $path = hash('sha256', $request->path());
        return hash('sha256', $ip . '|' . $path);
    }

    /**
     * Delete throttle files whose window has expired.
     */
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
