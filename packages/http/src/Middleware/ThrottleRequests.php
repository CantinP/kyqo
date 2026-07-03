<?php

namespace Kyqo\Http\Middleware;

use Kyqo\Http\Request;
use Kyqo\Http\Response;

class ThrottleRequests
{
    protected int $maxAttempts;
    protected int $decayMinutes;
    protected string $storePath;

    public function __construct(int $maxAttempts = 60, int $decayMinutes = 1)
    {
        $this->maxAttempts = $maxAttempts;
        $this->decayMinutes = $decayMinutes;
        $this->storePath = sys_get_temp_dir() . '/kyqo_throttle';

        if (!is_dir($this->storePath)) {
            mkdir($this->storePath, 0700, true);
        }
    }

    public function handle(Request $request, \Closure $next): mixed
    {
        $key = $this->resolveKey($request);
        $data = $this->getData($key);
        $now = time();

        if ($data['reset_at'] <= $now) {
            $data = ['count' => 0, 'reset_at' => $now + ($this->decayMinutes * 60)];
        }

        $data['count']++;
        $this->saveData($key, $data);

        $remaining = max(0, $this->maxAttempts - $data['count']);
        $retryAfter = $data['reset_at'] - $now;

        if ($data['count'] > $this->maxAttempts) {
            return Response::make(
                json_encode(['message' => 'Too Many Requests.', 'retry_after' => $retryAfter]),
                429,
                [
                    'Content-Type' => 'application/json',
                    'Retry-After' => (string) $retryAfter,
                    'X-RateLimit-Limit' => (string) $this->maxAttempts,
                    'X-RateLimit-Remaining' => '0',
                    'X-RateLimit-Reset' => (string) $data['reset_at'],
                ]
            );
        }

        $response = $next($request);

        if ($response instanceof Response) {
            $response->setHeader('X-RateLimit-Limit', (string) $this->maxAttempts);
            $response->setHeader('X-RateLimit-Remaining', (string) $remaining);
            $response->setHeader('X-RateLimit-Reset', (string) $data['reset_at']);
        }

        return $response;
    }

    protected function resolveKey(Request $request): string
    {
        $ip = $request->ip() ?? '0.0.0.0';
        $path = hash('sha256', $request->path());
        return hash('sha256', $ip . '|' . $path);
    }

    protected function getData(string $key): array
    {
        $file = $this->storePath . '/' . $key . '.json';
        if (file_exists($file)) {
            $data = json_decode((string) file_get_contents($file), true);
            if (is_array($data)) {
                return $data;
            }
        }

        return ['count' => 0, 'reset_at' => time() + ($this->decayMinutes * 60)];
    }

    protected function saveData(string $key, array $data): void
    {
        $file = $this->storePath . '/' . $key . '.json';
        file_put_contents($file, json_encode($data), LOCK_EX);
        @chmod($file, 0600);
    }
}
