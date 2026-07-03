<?php

namespace Kyqo\Queue;

/**
 * Contract for all queue drivers.
 */
interface QueueInterface
{
    public function push(object $job, ?string $queue = null): mixed;
    public function later(\DateTimeInterface|int $delay, object $job, ?string $queue = null): mixed;
    public function pop(?string $queue = null): ?object;
    public function size(?string $queue = null): int;
}
