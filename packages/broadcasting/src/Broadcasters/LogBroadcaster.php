<?php

namespace Kyqo\Broadcasting\Broadcasters;

use Kyqo\Broadcasting\BroadcasterInterface;

/**
 * Logs broadcast events — useful for local development.
 */
class LogBroadcaster implements BroadcasterInterface
{
    public function broadcast(array $channels, string $event, array $payload = []): void
    {
        $message = sprintf(
            '[Kyqo\Broadcasting] Event: %s | Channels: %s | Payload: %s',
            $event,
            implode(', ', $channels),
            json_encode($payload)
        );

        error_log($message);
    }
}
