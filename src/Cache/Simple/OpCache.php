<?php

namespace Odan\Cache\Simple;

use DateInterval;
use DateTime;
use FilesystemIterator;
use Odan\Cache\Exception\InvalidArgumentException;
use Psr\SimpleCache\CacheInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Traversable;

/**
 * OpCache (PSR-16)
 *
 * OPcache improves PHP performance by storing precompiled script bytecode
 * in shared memory, thereby removing the need for PHP to load and
 * parse scripts on each request.
 */
class OpCache implements CacheInterface
{
    /**
     * Cache path
     *
     * @var string
     */
    protected $path = '';

    /**
     * Status
     *
     * @var bool Status
     */
    protected $hasCompileFile = false;

    /**
     * File modification time
     *
     * @var int File modification time in seconds
     */
    protected $fileModifiedOffset = 86400;

    /**
     * Chmod mode.
     *
     * @var int Mode
     */
    protected $chmod = 0775;

    /**
     * Constructor
     *
     * @param string $path Cache path
     */
    public function __construct($path = null)
    {
        if (isset($path)) {
            $this->path = $path;
        } else {
            $this->path = sys_get_temp_dir() . '/cache';
        }
        if (!file_exists($this->path)) {
            mkdir($this->path, $this->chmod, true);
        }

        $this->hasCompileFile = function_exists('opcache_compile_file') && !empty(opcache_get_status());
    }

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

        $cacheFile = $this->getFilename($key);
        $path = dirname($cacheFile);
        if (!is_dir($path)) {
            mkdir($path, $this->chmod, true);
        }

        $cacheValue = $this->createCacheValue($key, $value, $ttl);
        $content = var_export($cacheValue, true);
        $content = '<?php return ' . $content . ';';

        // Acquire an exclusive lock on the file while proceeding to the writing.
        // In other words, a flock() call happens between the fopen() call and the fwrite() call.
        file_put_contents($cacheFile, $content, LOCK_EX);

        // opcache will only compile and cache files older than the script execution start.
        // set a date before the script execution date, then opcache will compile and cache the generated file.
        touch($cacheFile, time() - $this->fileModifiedOffset);

        // This php extension is not enabled by default on windows. We must check it.
        if ($this->hasCompileFile) {
            opcache_invalidate($cacheFile);
            opcache_compile_file($cacheFile);
        }

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
        $filename = $this->getFilename($key);
        if (!file_exists($filename)) {
            return $default;
        }

        // Acquire a read lock (shared locked)
        $myfile = fopen($filename, 'rt');
        flock($myfile, LOCK_SH);

        $cacheValue = include $filename;

        fclose($myfile);

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
        $filename = $this->getFilename($key);
        if (!file_exists($filename)) {
            return false;
        }

        $cacheValue = include $filename;

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
        $filename = $this->getFilename($key);
        if (file_exists($filename)) {
            unlink($filename);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $iterator = new RecursiveDirectoryIterator($this->path, FilesystemIterator::SKIP_DOTS);
        foreach (new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST) as $path) {
            $path->isDir() && !$path->isLink() ? rmdir($path->getPathname()) : unlink($path->getPathname());
        }

        return true;
    }

    /**
     * Get cache filename.
     *
     * @param string $key Key
     * @return string Filename
     */
    protected function getFilename($key)
    {
        $sha1 = sha1($key);
        $result = $this->path . '/' . substr($sha1, 0, 2) . '/' . substr($sha1, 2) . '.php';

        return $result;
    }

    /**
     * Creates a cache value object.
     *
     * @param string $key The cache key the file is stored under.
     * @param mixed $value The data being stored
     * @param int|null $ttl The timestamp of when the data will expire. If null, the data won't expire.
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
     * Checks if a value is expired.
     *
     * @return bool True if the value is expired.
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
