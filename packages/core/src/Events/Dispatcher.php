<?php

namespace Kyqo\Core\Events;

/**
 * Kyqo Event Dispatcher
 *
 * Supports Closure listeners, class-string listeners (resolved via the
 * container when available), and object events dispatched by class name.
 *
 * Propagation is stopped automatically when $event->propagationStopped === true
 * (only for Event subclasses).
 */
class Dispatcher
{
    protected array $listeners = [];
    protected array $wildcards = [];

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
     * Forget all listeners for the given event.
     */
    public function forget(string $event): void
    {
        unset($this->listeners[$event]);
    }

    /**
     * Fire an event and collect all listener responses.
     * Supports both string events and Event objects.
     * Propagation is stopped when $event->propagationStopped === true.
     */
    public function fire(string|object $event, mixed $payload = []): array
    {
        [$eventName, $payload] = $this->parseEventAndPayload($event, $payload);

        $responses  = [];
        $eventObj   = is_object($event) ? $event : null;

        foreach ($this->getListeners($eventName) as $listener) {
            if ($eventObj instanceof Event && $eventObj->propagationStopped) break;

            try {
                $responses[] = ['result' => $this->callListener($listener, $eventObj ?? $event, $payload)];
            } catch (\Throwable $e) {
                error_log(sprintf(
                    '[Kyqo\Events] Listener threw %s for event "%s": %s in %s:%d',
                    get_class($e), $eventName, $e->getMessage(), $e->getFile(), $e->getLine()
                ));
                $responses[] = ['error' => $e->getMessage()];
            }
        }

        return $responses;
    }

    /** Alias for fire() — matches Laravel’s API. */
    public function dispatch(string|object $event, mixed $payload = []): array
    {
        return $this->fire($event, $payload);
    }

    public function hasListeners(string $event): bool
    {
        return !empty($this->listeners[$event]) || $this->hasWildcardListeners($event);
    }

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

    // ── Internal ────────────────────────────────────────────────────────────

    protected function callListener(\Closure|string $listener, mixed $event, array $payload): mixed
    {
        if ($listener instanceof \Closure) {
            return $listener($event, $payload);
        }

        // Class-string listener: resolve and call handle()
        try {
            $app      = \Kyqo\Core\Application::getInstance();
            $instance = $app !== null ? $app->make($listener) : new $listener();
        } catch (\Throwable) {
            $instance = new $listener();
        }

        if (is_object($event)) {
            return $instance->handle($event);
        }

        return $instance->handle($event, ...$payload);
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
