<?php

namespace Kyqo\Core\Events;

/**
 * Kyqo Event Dispatcher
 *
 * Registers event listeners, wildcards, and fires events throughout
 * the application. Inspired by Laravel's event system.
 */
class Dispatcher
{
    protected array $listeners = [];
    protected array $wildcards = [];

    /**
     * Register an event listener.
     */
    public function listen(string|array $events, \Closure|string $listener): void
    {
        foreach ((array) $events as $event) {
            if (str_contains($event, '*')) {
                $this->wildcards[$event][] = $listener;
            } else {
                $this->listeners[$event][] = $listener;
            }
        }
    }

    /**
     * Fire an event and return all responses.
     */
    public function fire(string|object $event, mixed $payload = []): array
    {
        [$event, $payload] = $this->parseEventAndPayload($event, $payload);

        $responses = [];

        foreach ($this->getListeners($event) as $listener) {
            $response = $listener($event, $payload);
            $responses[] = $response;
        }

        return $responses;
    }

    /**
     * Determine if any listeners are registered for the event.
     */
    public function hasListeners(string $event): bool
    {
        return !empty($this->listeners[$event]) || $this->hasWildcardListeners($event);
    }

    /**
     * Get all listeners for an event including wildcards.
     */
    public function getListeners(string $event): array
    {
        $listeners = $this->listeners[$event] ?? [];

        foreach ($this->wildcards as $pattern => $wilds) {
            if (fnmatch($pattern, $event)) {
                $listeners = array_merge($listeners, $wilds);
            }
        }

        return $listeners;
    }

    protected function hasWildcardListeners(string $event): bool
    {
        foreach (array_keys($this->wildcards) as $pattern) {
            if (fnmatch($pattern, $event)) return true;
        }
        return false;
    }

    protected function parseEventAndPayload(string|object $event, mixed $payload): array
    {
        if (is_object($event)) {
            [$payload, $event] = [[$event], get_class($event)];
        }
        return [$event, is_array($payload) ? $payload : [$payload]];
    }
}
