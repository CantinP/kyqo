<?php

namespace Kyqo\Broadcasting\Broadcasters;

use Kyqo\Broadcasting\BroadcasterInterface;

/**
 * Pusher Broadcaster — uses the Pusher Channels HTTP API directly.
 * No SDK dependency required — raw HTTP via curl or file_get_contents.
 *
 * Compatible with Pusher-hosted clusters and open-source Soketi.
 */
class PusherBroadcaster implements BroadcasterInterface
{
    public function __construct(
        private string $key,
        private string $secret,
        private string $appId,
        private string $cluster = 'mt1',
        private bool   $useTls  = true
    ) {}

    public function broadcast(array $channels, string $event, array $payload = []): void
    {
        if (empty($channels)) return;

        $body = json_encode([
            'name'     => $event,
            'channels' => array_values($channels),
            'data'     => json_encode($payload),
        ]);

        $timestamp  = time();
        $bodyMd5    = md5($body);
        $path       = '/apps/' . $this->appId . '/events';
        $scheme     = $this->useTls ? 'https' : 'http';
        $host       = "api-{$this->cluster}.pusher.com";

        $toSign = implode("\n", [
            'POST',
            $path,
            $this->buildQueryString($timestamp, $bodyMd5),
        ]);

        $signature = hash_hmac('sha256', $toSign, $this->secret);
        $query     = $this->buildQueryString($timestamp, $bodyMd5) . '&auth_signature=' . $signature;
        $url       = "{$scheme}://{$host}{$path}?{$query}";

        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n",
                'content' => $body,
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);

        @file_get_contents($url, false, $context);
    }

    private function buildQueryString(int $timestamp, string $bodyMd5): string
    {
        return http_build_query([
            'auth_key'       => $this->key,
            'auth_timestamp' => $timestamp,
            'auth_version'   => '1.0',
            'body_md5'       => $bodyMd5,
        ]);
    }
}
