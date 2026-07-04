<?php

namespace Kyqo\Tests\Unit\Cache;

use Kyqo\Cache\Stores\ArrayStore;
use PHPUnit\Framework\TestCase;

class ArrayStoreTest extends TestCase
{
    private ArrayStore $store;

    protected function setUp(): void
    {
        $this->store = new ArrayStore();
    }

    public function testPutAndGet(): void
    {
        $this->store->put('key', 'value', 60);
        $this->assertSame('value', $this->store->get('key'));
    }

    public function testGetReturnsDefaultWhenMissing(): void
    {
        $this->assertSame('default', $this->store->get('missing', 'default'));
        $this->assertNull($this->store->get('missing'));
    }

    public function testHas(): void
    {
        $this->assertFalse($this->store->has('key'));
        $this->store->put('key', 'val', 60);
        $this->assertTrue($this->store->has('key'));
    }

    public function testForget(): void
    {
        $this->store->put('key', 'val', 60);
        $this->store->forget('key');
        $this->assertFalse($this->store->has('key'));
    }

    public function testFlush(): void
    {
        $this->store->put('a', 1, 60);
        $this->store->put('b', 2, 60);
        $this->store->flush();
        $this->assertFalse($this->store->has('a'));
        $this->assertFalse($this->store->has('b'));
    }

    public function testExpiry(): void
    {
        $this->store->put('key', 'val', -1); // already expired
        $this->assertNull($this->store->get('key'));
        $this->assertFalse($this->store->has('key'));
    }

    public function testStoresNull(): void
    {
        $this->store->put('null_key', null, 60);
        $this->assertTrue($this->store->has('null_key'));
        $this->assertNull($this->store->get('null_key', 'not-null'));
    }
}
