<?php

namespace Kyqo\Core\Events;

/**
 * Base Listener class.
 *
 * All application listeners should extend this class and implement handle().
 *
 * Usage:
 *   class SendWelcomeEmail extends Listener
 *   {
 *       public function handle(UserRegistered $event): void
 *       {
 *           Mail::to($event->user->email)->send(new WelcomeMail($event->user));
 *       }
 *   }
 *
 *   // Registration (in a ServiceProvider or bootstrap):
 *   Event::listen(UserRegistered::class, SendWelcomeEmail::class);
 */
abstract class Listener
{
    /**
     * Handle the event.
     */
    abstract public function handle(Event $event): void;

    /**
     * Determine if this listener should queue the job.
     * Override to return true to push the handler onto the queue.
     */
    public function shouldQueue(): bool
    {
        return false;
    }
}
