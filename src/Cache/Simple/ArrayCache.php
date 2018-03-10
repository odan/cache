<?php

namespace Odan\Cache\Simple;

use DateInterval;
use DateTime;
use Odan\Cache\Exception\InvalidArgumentException;
use Psr\SimpleCache\CacheInterface;
use Traversable;

/**
 * ArrayCache (PSR-16)
 */
class ArrayCache implements CacheInterface
{
    /**
     * Array cache
     *
     * @var array
     */
    protected $data = array();

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null)
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException();
        }

        if ($ttl instanceof DateInterval) {
            // Converting to a TTL in seconds
            $ttl = (new DateTime('now'))->add($ttl)->getTimeStamp() - time();
        }

        $cacheValue = $this->createCacheValue($key, $value, (int)$ttl);
        $this->data[$key] = $cacheValue;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, $default = null)
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException();
        }
        if (!array_key_exists($key, $this->data)) {
            return $default;
        }

        $cacheValue = $this->data[$key];
        if ($this->isExpired($cacheValue['expires'])) {
            $this->delete($key);

            return $default;
        }
        $result = isset($cacheValue['value']) ? $cacheValue['value'] : $default;

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple($keys, $default = null)
    {
        if (!is_array($keys) && !($keys instanceof Traversable)) {
            throw new InvalidArgumentException();
        }

        $result = array();
        foreach ((array)$keys as $key) {
            $result[$key] = $this->has($key) ? $this->get($key) : $default;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple($values, $ttl = null)
    {
        if (!is_array($values) && !($values instanceof Traversable)) {
            throw new InvalidArgumentException();
        }

        if ($ttl instanceof DateInterval) {
            // Converting to a TTL in seconds
            $ttl = (new DateTime('now'))->add($ttl)->getTimeStamp() - time();
        }

        foreach ((array)$values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple($keys)
    {
        if (!is_array($keys) && !($keys instanceof Traversable)) {
            throw new InvalidArgumentException();
        }

        foreach ((array)$keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function has($key)
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException();
        }
        if (!array_key_exists($key, $this->data)) {
            return false;
        }
        $cacheValue = $this->data[$key];
        if ($this->isExpired($cacheValue['expires'])) {
            $this->delete($key);

            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        unset($this->data[$key]);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->data = array();

        return true;
    }

    /**
     * Creates a FileSystemCacheValue object.
     *
     * @param string $key The cache key the file is stored under.
     * @param mixed $value The data being stored
     * @param int $ttl The timestamp of when the data will expire. If null, the data won't expire.
     * @return array Cache value
     */
    protected function createCacheValue($key, $value, $ttl = null)
    {
        $created = time();

        return array(
            'created' => $created,
            'key' => $key,
            'value' => $value,
            'ttl' => $ttl,
            'expires' => ($ttl) ? $created + $ttl : null
        );
    }

    /**
     * Checks if a value is expired
     * @return bool True if the value is expired.  False if it is not.
     */
    protected function isExpired($expires)
    {
        // value doesn't expire
        if (!$expires) {
            return false;
        }

        // if it's after the expire time
        return time() > $expires;
    }
}
