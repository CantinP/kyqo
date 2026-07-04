<?php

namespace Kyqo\Queue\Concerns;

/**
 * Dispatchable trait.
 *
 * Gives a Job class static dispatch() and dispatchSync() helpers.
 */
trait Dispatchable
{
    /**
     * Push the job onto the default queue.
     */
    public static function dispatch(mixed ...$args): static
    {
        $job = new static(...$args);
        $manager = \Kyqo\Core\Application::getInstance()->make(\Kyqo\Queue\QueueManager::class);
        $manager->connection()->push($job, $job->queue);
        return $job;
    }

    /**
     * Run the job synchronously (inline, no queue).
     */
    public static function dispatchSync(mixed ...$args): static
    {
        $job = new static(...$args);
        $job->handle();
        return $job;
    }

    /**
     * Push the job with a delay.
     */
    public static function dispatchAfter(int $seconds, mixed ...$args): static
    {
        $job = new static(...$args);
        $job->delay($seconds);
        $manager = \Kyqo\Core\Application::getInstance()->make(\Kyqo\Queue\QueueManager::class);
        $manager->connection()->later($seconds, $job, $job->queue);
        return $job;
    }
}
