<?php

namespace Odan\Test;

use Odan\Cache\Simple\OpCache;

/**
 * OpCacheTest
 *
 * @coversDefaultClass \Odan\Cache\Simple\OpCache
 */
class OpCacheTest extends ArrayCacheTest
{
    protected $cache;

    protected function setUp()
    {
        $path = dirname(__DIR__) . '/tmp/opcache';
        if (!file_exists($path)) {
            mkdir($path, 0775, true);
        }
        $this->cache = new OpCache($path);
        $this->cache->clear();
    }

    /**
     * Test.
     */
    public function testInstanceOf()
    {
        $this->assertInstanceOf(OpCache::class, $this->cache);
    }

    public function testOpCacheExtension()
    {
        $this->assertTrue(function_exists('opcache_compile_file'));
    }
}
