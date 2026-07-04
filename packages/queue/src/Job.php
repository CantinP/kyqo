<?php

namespace Kyqo\Queue;

/**
 * Base Job class.
 *
 * Extend this to create dispatchable jobs.
 *
 * Usage:
 *   class SendWelcomeEmail extends Job
 *   {
 *       public function __construct(protected User $user) {}
 *
 *       public function handle(): void
 *       {
 *           Mail::to($this->user->email)->send(new WelcomeMail($this->user));
 *       }
 *   }
 *
 *   SendWelcomeEmail::dispatch($user);
 *   SendWelcomeEmail::dispatchSync($user);
 */
abstract class Job
{
    use Concerns\Dispatchable;
    use Concerns\InteractsWithQueue;

    public string  $queue    = 'default';
    public int     $delay    = 0;
    public int     $tries    = 3;
    public int     $timeout  = 60;

    abstract public function handle(): void;

    public function failed(\Throwable $e): void
    {
        // Override to handle job failure.
    }

    public function onQueue(string $queue): static
    {
        $this->queue = $queue;
        return $this;
    }

    public function delay(int $seconds): static
    {
        $this->delay = $seconds;
        return $this;
    }
}
