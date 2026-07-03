<?php

namespace Kyqo\Core\Events;

/**
 * Kyqo Event Dispatcher
 *
 * MINOR-FINAL-5: Listener exceptions are caught and logged individually.
 * Remaining listeners are still called even if one throws.
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
     * Fire an event and collect all listener responses.
     *
     * FIX MINOR-FINAL-5: Each listener is wrapped in a try/catch.
     * A failing listener logs an error but does NOT interrupt remaining listeners.
     *
     * @return array  [['result' => mixed]|['error' => string], ...]
     */
    public function fire(string|object $event, mixed $payload = []): array
    {
        [$event, $payload] = $this->parseEventAndPayload($event, $payload);

        $responses = [];

        foreach ($this->getListeners($event) as $listener) {
            try {
                $responses[] = ['result' => $listener($event, $payload)];
            } catch (\Throwable $e) {
                error_log(
                    sprintf(
                        '[Kyqo\Events] Listener threw %s for event "%s": %s in %s:%d',
                        get_class($e),
                        $event,
                        $e->getMessage(),
                        $e->getFile(),
                        $e->getLine()
                    )
                );
                $responses[] = ['error' => $e->getMessage()];
            }
        }

        return $responses;
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
