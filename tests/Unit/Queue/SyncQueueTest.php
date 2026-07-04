<?php

namespace Kyqo\Tests\Unit\Queue;

use Kyqo\Queue\Drivers\SyncQueue;
use PHPUnit\Framework\TestCase;

class SyncQueueTest extends TestCase
{
    public function testPushAndPop(): void
    {
        $queue = new SyncQueue();
        $job   = new class { public string $name = 'test'; };
        $queue->push($job);
        $popped = $queue->pop();
        $this->assertEquals($job, $popped);
    }

    public function testPopReturnsNullWhenEmpty(): void
    {
        $queue = new SyncQueue();
        $this->assertNull($queue->pop());
    }

    public function testSize(): void
    {
        $queue = new SyncQueue();
        $this->assertSame(0, $queue->size());
        $queue->push(new \stdClass());
        $this->assertSame(1, $queue->size());
    }
}
