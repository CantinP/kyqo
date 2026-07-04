<?php

namespace Kyqo\Tests\Unit\Queue;

use Kyqo\Queue\QueueManager;
use PHPUnit\Framework\TestCase;

class QueueManagerTest extends TestCase
{
    private function manager(): QueueManager
    {
        return new QueueManager([
            'default'     => 'sync',
            'connections' => [
                'sync' => ['driver' => 'sync'],
            ],
        ]);
    }

    public function testDispatchCallsHandle(): void
    {
        $manager = $this->manager();
        $result  = null;

        $job = new class($result) {
            public function __construct(private mixed &$out) {}
            public function handle(): void { $this->out = 'done'; }
        };

        $manager->dispatch($job);
        $this->assertSame('done', $result);
    }

    public function testDispatchThrowsWhenHandleMissing(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->manager()->dispatch(new \stdClass());
    }

    public function testDispatchThrowsWhenHandleNotPublic(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $job = new class {
            protected function handle(): void {}
        };
        $this->manager()->dispatch($job);
    }

    public function testUndefinedConnectionThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $m = new QueueManager([
            'default'     => 'nope',
            'connections' => [],
        ]);
        $m->push(new \stdClass());
    }
}
