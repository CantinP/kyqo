<?php

namespace Kyqo\Tests\Unit\Cache;

use Kyqo\Cache\CacheManager;
use PHPUnit\Framework\TestCase;

class CacheManagerTest extends TestCase
{
    private function manager(): CacheManager
    {
        return new CacheManager([
            'default' => 'array',
            'stores'  => [
                'array' => ['driver' => 'array'],
            ],
        ]);
    }

    public function testDefaultStore(): void
    {
        $m = $this->manager();
        $m->put('x', 42, 60);
        $this->assertSame(42, $m->get('x'));
    }

    public function testRemember(): void
    {
        $m = $this->manager();
        $calls = 0;
        $val = $m->remember('key', 60, function () use (&$calls) {
            $calls++;
            return 'computed';
        });
        $this->assertSame('computed', $val);
        $m->remember('key', 60, function () use (&$calls) {
            $calls++;
            return 'recomputed';
        });
        $this->assertSame(1, $calls, 'callback must only be called once');
    }

    public function testUndefinedStoreThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $m = new CacheManager(['default' => 'nope', 'stores' => []]);
        $m->get('k');
    }

    public function testUnsupportedDriverThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $m = new CacheManager([
            'default' => 'bad',
            'stores'  => ['bad' => ['driver' => 'magic']],
        ]);
        $m->get('k');
    }
}
