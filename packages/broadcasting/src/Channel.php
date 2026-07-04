<?php

namespace Kyqo\Broadcasting;

/**
 * Represents a public broadcast channel.
 * For private/presence channels use PrivateChannel / PresenceChannel.
 */
class Channel
{
    public function __construct(public string $name) {}
}
