<?php

namespace Kyqo\Broadcasting\Broadcasters;

use Kyqo\Broadcasting\BroadcasterInterface;

class NullBroadcaster implements BroadcasterInterface
{
    public function broadcast(array $channels, string $event, array $payload = []): void
    {
        // No-op — useful in testing
    }
}
