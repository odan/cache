# OpCache Adapter (PSR-16 )

A PSR-16 Simple Cache Implementation.

[![Latest Version on Packagist](https://img.shields.io/github/release/odan/cache.svg)](https://packagist.org/packages/odan/cache)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE)
[![Build Status](https://github.com/odan/cache/workflows/build/badge.svg)](https://github.com/odan/cache/actions)
[![Coverage Status](https://img.shields.io/scrutinizer/coverage/g/odan/cache.svg)](https://scrutinizer-ci.com/g/odan/cache/code-structure)
[![Quality Score](https://img.shields.io/scrutinizer/quality/g/odan/cache.svg)](https://scrutinizer-ci.com/g/odan/cache/?branch=master)
[![Total Downloads](https://img.shields.io/packagist/dt/odan/cache.svg)](https://packagist.org/packages/odan/cache/stats)

## Out-of-the-Box Bytecode Cache

PHP is an interpreted language. The default PHP runtime compiles PHP sourcecode to an intermediate representation called
PHP bytecode which is then executed. A bytecode cache stores this compiled representation of PHP sourcecode in shared
memory. This eliminates the need to load and compile sourcecode on each request which leads to a significant increase in
performance (up to 70% more requests per second).

The basic idea, when executing a PHP script is in two steps:

* First: the PHP code, written in plain-text, is **compiled to opcodes**
* Then: those **opcodes are executed**.

When you have one PHP script, as long as it is not modified, the opcodes will always be the same ; so, doing the
compilation phase each time that script is to be executed is kind of a waste of CPU-time.

To prevent that redundant-compilation, there are some opcode caching mechanism that you can use.

Once the PHP script has been compiled to opcodes, those will be kept in RAM -- and directly used from memory the next
time the script is to be executed ; preventing the compilation from being done again and again.

**Read more**

* <https://blog.graphiq.com/500x-faster-caching-than-redis-memcache-apc-in-php-hhvm-dcd26e8447ad#.tsokdw9d4>
* <https://github.com/TerryE/opcache/wiki/The-Zend-Engine-and-opcode-caching#opcode-caching-with-opcache>

## Requirements

* PHP 7.2+ or 8.0+

## Installation

```
composer require odan/cache
```

## Usage

```php
$cachePath = sys_get_temp_dir() . '/cache';

$cache = new \Odan\Cache\Simple\OpCache($cachePath);

// set a opcache value
$cache->set('foo', 'bar');

// get a opcache value
echo $cache->get('foo'); // bar
```

## Known issues

> Fatal error: Call to undefined method stdClass::__set_state()

If there are objects in the value, they will be written as `stdClass::__set_state()`. 
This is fine for objects where `__set_state()` can be added, but it can't be added to `stdClass`.

To fix this issue just serialize the value you are trying to cache:

```php
$cache->set('key', serialize($object));
```

Then unserialize the string back to the original value:

```php
$object = unserialize($cache->get('key'));
```

### Race conditions

If you're in a high-concurrency environment you should avoid using a filesystem cache. 
Multiple operations on the file system are very hard to make atomic in PHP.

## Similar components

* [laminas/laminas-cache](https://docs.laminas.dev/laminas-cache/psr16/) - See the Filesystem Adapter
* [Symonfy Php Files Cache Adapter](https://symfony.com/doc/current/components/cache/adapters/php_files_adapter.html)
