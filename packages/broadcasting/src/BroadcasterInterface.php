<?php

namespace Kyqo\Broadcasting;

interface BroadcasterInterface
{
    /**
     * Broadcast an event to a set of channels.
     *
     * @param string[] $channels
     * @param string   $event
     * @param array    $payload
     */
    public function broadcast(array $channels, string $event, array $payload = []): void;
}
