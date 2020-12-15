<?php

namespace Odan\Cache\Test;

use Odan\Cache\Simple\ArrayCache;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

/**
 * OpCacheTest.
 *
 * @coversDefaultClass \Odan\Cache\Simple\ArrayCache
 */
class ArrayCacheTest extends TestCase
{
    /**
     * @var CacheInterface
     */
    protected $cache;

    protected function setUp(): void
    {
        $this->cache = new ArrayCache();
        $this->cache->clear();
    }

    /**
     * Test.
     */
    public function testInstanceOf(): void
    {
        $this->assertInstanceOf(ArrayCache::class, $this->cache);
    }

    /**
     * Test.
     */
    public function testHasByStringKey(): void
    {
        $this->cache->set('key', 'value');
        sleep(1);
        $this->assertTrue($this->cache->has('key'));
    }

    /**
     * Test exception.
     */
    public function testGetInvalidKey(): void
    {
        $this->expectException(\Psr\SimpleCache\InvalidArgumentException::class);

        $this->cache->get(1, 'value');
    }

    public function testGetByStringKey(): void
    {
        $this->cache->set('strkey', 'str key value');
        sleep(1);
        $this->assertEquals('str key value', $this->cache->get('strkey'));
    }

    /**
     * Test exception.
     */
    public function testSetByIntKey(): void
    {
        $this->expectException(\Psr\SimpleCache\InvalidArgumentException::class);
        $this->cache->set(2, 'value 2');
    }

    public function testDelete(): void
    {
        $this->cache->set('fordeletekey', 'value');
        sleep(1);
        $this->assertTrue($this->cache->has('fordeletekey'));
        $this->cache->delete('fordeletekey');
        $this->assertFalse($this->cache->has('fordeletekey'));
    }

    public function testHasStringKey(): void
    {
        $this->cache->set('key', 'value');
        sleep(1);
        $this->assertTrue($this->cache->has('key'));
    }

    public function testHasNotExistsStringKey(): void
    {
        $this->assertFalse($this->cache->has('somethingkey'));
    }

    public function testGetByStringKeyWithDefaultValue(): void
    {
        $this->assertEquals('default value', $this->cache->get('somenotexistingkey', 'default value'));
    }

    public function testGetByArrayKeyWithDefaultValue(): void
    {
        $this->assertEquals(['table', '3'], $this->cache->get('somenotexistingkey', ['table', '3']));
    }

    /**
     * Test.
     */
    public function testMultiple(): void
    {
        $values = ['key' => 'value', 'key2' => 'value2'];
        $keys = array_keys($values);

        $this->cache->setMultiple($values);
        sleep(1);
        $actual = $this->cache->getMultiple($keys);

        $this->assertSame($values, $actual);
    }
}
