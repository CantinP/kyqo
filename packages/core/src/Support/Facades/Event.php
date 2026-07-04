<?php

namespace Kyqo\Core\Support\Facades;

/**
 * @method static void listen(string|array $events, \Closure|string $listener)
 * @method static array fire(string|object $event, mixed $payload = [])
 * @method static bool hasListeners(string $event)
 */
class Event extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'events';
    }
}
