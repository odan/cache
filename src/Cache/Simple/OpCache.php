<?php

namespace Odan\Cache\Simple;

use DateInterval;
use DateTime;
use FilesystemIterator;
use Odan\Cache\Exception\InvalidArgumentException;
use Psr\SimpleCache\CacheInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Traversable;

/**
 * OpCache (PSR-16).
 *
 * OPcache improves PHP performance by storing precompiled script bytecode
 * in shared memory, thereby removing the need for PHP to load and
 * parse scripts on each request.
 */
class OpCache implements CacheInterface
{
    /**
     * Cache path.
     *
     * @var string
     */
    protected $path = '';

    /**
     * Status.
     *
     * @var bool Status
     */
    protected $hasCompileFile = false;

    /**
     * File modification time.
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
     * Constructor.
     *
     * @param string $path Cache path
     */
    public function __construct($path = null)
    {
        if (isset($path)) {
            $this->path = $path;
        } else {
            $this->path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cache';
        }

        // A more atomic option when creating directories
        @mkdir($this->path, $this->chmod, true);

        $this->hasCompileFile = function_exists('opcache_compile_file') && !empty(opcache_get_status());
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null)
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException('Invalid key');
        }
        if ($ttl instanceof DateInterval) {
            // Converting to a TTL in seconds
            $ttl = (new DateTime('now'))->add($ttl)->getTimeStamp() - time();
        }

        $cacheFile = $this->getFilename($key);
        $path = dirname($cacheFile);

        // A more atomic option when creating directories
        @mkdir($path, $this->chmod, true);

        $cacheValue = $this->createCacheValue($key, $value, $ttl);
        $content = var_export($cacheValue, true);
        $content = '<?php return ' . $content . ';';

        // Acquire an exclusive lock on the file while proceeding to the writing.
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
            throw new InvalidArgumentException('Invalid key');
        }

        $filename = $this->getFilename($key);
        if (!file_exists($filename)) {
            return $default;
        }

        // Acquire a read lock (shared locked)
        $myfile = fopen($filename, 'rt');

        if ($myfile === false) {
            throw new RuntimeException(sprintf('File could not be read: %s', $filename));
        }

        flock($myfile, LOCK_SH);

        $cacheValue = include $filename;

        fclose($myfile);

        if ($this->isExpired($cacheValue['expires'])) {
            $this->delete($key);

            return $default;
        }

        return $cacheValue['value'] ?? $default;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple($keys, $default = null)
    {
        if (!is_array($keys) && !($keys instanceof Traversable)) {
            throw new InvalidArgumentException();
        }

        $result = [];
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
     *
     * @return string Filename
     */
    protected function getFilename(string $key): string
    {
        $sha1 = sha1($key);

        return $this->path . DIRECTORY_SEPARATOR . substr($sha1, 0, 2) . DIRECTORY_SEPARATOR . substr(
                $sha1,
                2
            ) . '.php';
    }

    /**
     * Creates a cache value object.
     *
     * @param string $key The cache key the file is stored under
     * @param mixed $value The data being stored
     * @param int|null $ttl The timestamp of when the data will expire. If null, the data won't expire.
     *
     * @return array Cache value
     */
    protected function createCacheValue($key, $value, $ttl = null): array
    {
        $created = time();

        return [
            'created' => $created,
            'key' => $key,
            'value' => $value,
            'ttl' => $ttl,
            'expires' => ($ttl) ? $created + $ttl : null,
        ];
    }

    /**
     * Checks if a value is expired.
     *
     * @param mixed $expires
     *
     * @return bool true if the value is expired
     */
    protected function isExpired($expires): bool
    {
        // value doesn't expire
        if (!$expires) {
            return false;
        }

        // if it's after the expire time
        return time() > $expires;
    }
}
