<?php

namespace Odan\Test;

use Odan\Cache\Simple\OpCache;
use Psr\SimpleCache\CacheInterface;

/**
 * OpCacheTest
 *
 * @coversDefaultClass \Odan\Cache\Simple\OpCache
 */
class OpCacheTest extends ArrayCacheTest
{
    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var string
     */
    protected $path;

    protected function setUp()
    {
        $this->path = dirname(__DIR__) . '/tmp/opcache';
        if (!file_exists($this->path)) {
            mkdir($this->path, 0775, true);
        }

        $this->cache = new OpCache($this->path);
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

    public function testOpCacheFile()
    {
        $key = 'op_cache_test_key';
        $this->cache->set($key, 'value');
        sleep(1);

        $cacheFile = $this->getCacheFilename($key);
        $this->assertTrue(opcache_is_script_cached($cacheFile));
    }

    /**
     * Get cache filename.
     *
     * @param string $key Key
     * @return string Filename
     */
    protected function getCacheFilename($key)
    {
        $sha1 = sha1($key);
        $result = $this->path . '/' . substr($sha1, 0, 2) . '/' . substr($sha1, 2) . '.php';

        return $result;
    }
}
