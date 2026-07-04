<?php

namespace Kyqo\Core\Events;

/**
 * Base Event class.
 *
 * All application events should extend this class.
 *
 * Usage:
 *   class UserRegistered extends Event
 *   {
 *       public function __construct(public readonly User $user) {}
 *   }
 *
 *   event(new UserRegistered($user));
 */
abstract class Event
{
    /**
     * Prevent further listeners from being called.
     */
    public bool $propagationStopped = false;

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}
