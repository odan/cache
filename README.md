# OpCache Adapter (PSR-16 )

PSR-16 Simple Cache Implementation

OPcache improves PHP performance by storing precompiled script bytecode
in shared memory, thereby removing the need for PHP to load and
parse scripts on each request.


[![Latest Version](https://img.shields.io/github/release/odan/cache.svg)](https://github.com/loadsys/odan/cache/releases)
[![Build Status](https://travis-ci.org/odan/cache.svg?branch=master)](https://travis-ci.org/odan/cache)
[![Crutinizer](https://img.shields.io/scrutinizer/g/odan/cache.svg)](https://scrutinizer-ci.com/g/odan/cache)
[![Coverage Status](https://scrutinizer-ci.com/g/odan/cache/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/odan/cache/code-structure)
[![Total Downloads](https://img.shields.io/packagist/dt/odan/cache.svg)](https://packagist.org/packages/odan/cache)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE.md)


## Installation

```
composer require odan/cache
```

## Usage

```php
$cachePath = sys_get_temp_dir() . '/opcache';

$cache = new \Odan\Cache\Simple\OpCache($cachePath);

// set a cache value
$cache->set('foo', 'bar');

// get a cache value
echo $cache->set('foo'); // bar
```
