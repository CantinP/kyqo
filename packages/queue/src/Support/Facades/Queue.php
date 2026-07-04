<?php

namespace Kyqo\Queue\Support\Facades;

use Kyqo\Core\Support\Facades\Facade;

/**
 * @method static void push(mixed $job, string $queue = null)
 * @method static void later(int $delay, mixed $job, string $queue = null)
 * @method static mixed pop(string $queue = null)
 * @method static int size(string $queue = null)
 * @method static \Kyqo\Queue\QueueInterface connection(?string $name = null)
 */
class Queue extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'queue';
    }
}
