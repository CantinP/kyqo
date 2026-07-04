<?php

namespace Kyqo\Tests\Unit\Cache;

use Kyqo\Cache\Stores\FileStore;
use PHPUnit\Framework\TestCase;

class FileStoreTest extends TestCase
{
    private string   $dir;
    private FileStore $store;

    protected function setUp(): void
    {
        $this->dir   = sys_get_temp_dir() . '/kyqo_test_cache_' . uniqid();
        $this->store = new FileStore($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*.cache') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
    }

    public function testPutAndGet(): void
    {
        $this->store->put('foo', 'bar', 60);
        $this->assertSame('bar', $this->store->get('foo'));
    }

    public function testMissReturnsDefault(): void
    {
        $this->assertSame('x', $this->store->get('nope', 'x'));
    }

    public function testHasAndForget(): void
    {
        $this->store->put('k', 1, 60);
        $this->assertTrue($this->store->has('k'));
        $this->store->forget('k');
        $this->assertFalse($this->store->has('k'));
    }

    public function testFlush(): void
    {
        $this->store->put('a', 1, 60);
        $this->store->put('b', 2, 60);
        $this->store->flush();
        $this->assertFalse($this->store->has('a'));
    }

    public function testExpiredEntryIsEvicted(): void
    {
        $this->store->put('exp', 'val', -1);
        $this->assertNull($this->store->get('exp'));
        $this->assertFalse($this->store->has('exp'));
    }

    public function testNullValueStoredCorrectly(): void
    {
        $this->store->put('n', null, 60);
        $this->assertTrue($this->store->has('n'));
        $this->assertNull($this->store->get('n', 'sentinel'));
    }
}
