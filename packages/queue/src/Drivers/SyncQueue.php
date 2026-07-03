<?php

namespace Kyqo\Queue\Drivers;

use Kyqo\Queue\QueueInterface;

/**
 * Synchronous queue driver.
 *
 * Executes jobs immediately in the same process.
 * Useful for local development and testing.
 */
class SyncQueue implements QueueInterface
{
    public function push(object $job, ?string $queue = null): mixed
    {
        return $this->execute($job);
    }

    public function later(\DateTimeInterface|int $delay, object $job, ?string $queue = null): mixed
    {
        // Sync driver ignores delay — execute immediately
        return $this->execute($job);
    }

    public function pop(?string $queue = null): ?object
    {
        // Sync queue has no persistent storage
        return null;
    }

    public function size(?string $queue = null): int
    {
        return 0;
    }

    protected function execute(object $job): mixed
    {
        if (method_exists($job, 'handle')) {
            return $job->handle();
        }
        return null;
    }
}
