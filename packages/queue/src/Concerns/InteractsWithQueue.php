<?php

namespace Kyqo\Queue\Concerns;

trait InteractsWithQueue
{
    protected int $attempts = 0;

    public function attempts(): int { return $this->attempts; }
    public function incrementAttempts(): void { $this->attempts++; }

    public function release(int $delay = 0): void
    {
        // Release back to queue with optional delay.
        // Implemented by the queue driver wrapper around the job.
    }

    public function maxTries(): int { return $this->tries ?? 3; }
    public function timeout(): int  { return $this->timeout ?? 60; }
}
