<?php

namespace Odan\Cache\Test;

use Odan\Cache\Simple\OpCache;
use Psr\SimpleCache\CacheInterface;

/**
 * OpCacheTest.
 *
 * @coversDefaultClass \Odan\Cache\Simple\OpCache
 *
 * Setup
 *
 * php.ini
 *
 * linux
 * zend_extension="opcache"
 *
 * windows:
 * zend_extension="c:\xampp\php\ext\php_opcache.dll"
 *
 * [opcache]
 * opcache.enable=1
 * opcache.enable_cli=1
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

    protected function setUp(): void
    {
        $this->path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'opcache';
        if (!file_exists($this->path)) {
            mkdir($this->path, 0775, true);
        }

        $this->cache = new OpCache($this->path);
        $this->cache->clear();
    }

    /**
     * Test.
     */
    public function testInstanceOf(): void
    {
        $this->assertInstanceOf(OpCache::class, $this->cache);
    }

    public function testOpCacheExtension(): void
    {
        // php.ini:
        // zend_extension=opcache
        // opcache.enable=1
        // opcache.enable_cli=1
        $this->assertTrue(function_exists('opcache_compile_file'));

        // If the opcache is disabled, this functions returns false.
        $this->assertNotFalse(opcache_get_status());
    }

    public function testOpCacheFile(): void
    {
        $key = 'op_cache_test_key';
        $cacheFile = $this->getCacheFilename($key);

        $status = opcache_get_status();
        $this->assertFalse(isset($status['scripts'][$cacheFile]));

        $this->cache->set($key, 'value');
        sleep(1);

        $cacheFile = $this->getCacheFilename($key);
        $this->assertFileExists($cacheFile);
        $this->assertTrue(opcache_is_script_cached($cacheFile));

        $status2 = opcache_get_status();
        $this->assertTrue(isset($status2['scripts'][$cacheFile]));

        $this->cache->delete($key);
        $this->assertFileNotExists($cacheFile);
    }

    /**
     * Get cache filename.
     *
     * @param string $key Key
     *
     * @return string Filename
     */
    protected function getCacheFilename(string $key): string
    {
        $sha1 = sha1($key);

        return $this->path . DIRECTORY_SEPARATOR . substr($sha1, 0, 2) . DIRECTORY_SEPARATOR . substr(
                $sha1,
                2
            ) . '.php';
    }
}
