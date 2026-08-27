---
paths:
  - 'app/Support/Rotation/**'
---

# Rotation

## Cache plain arrays, never objects
`config/cache.php` sets `serializable_classes => false`, so Laravel refuses to unserialize ANY class out of a cache store. Caching an object returns `__PHP_Incomplete_Class` and a TypeError on every store that actually serializes (redis, database, file).

The suite cannot catch this: `phpunit.xml` pins `ROTATOR_CACHE_STORE=array`, and ArrayStore keeps live objects without ever serializing. It passed green while every request after the first 500'd in the browser.

`RotatorSnapshot`/`DestinationCandidate` therefore cross the cache via `toArray()`/`fromArray()`. Keep it that way for anything new you cache, and see `tests/Feature/Rotator/RotatorCacheTest.php` for the round-trip regression test that pins it.

## Two state stores, one tie-break contract
`RedisRotationStateStore` (Lua, atomic) is production; `CacheRotationStateStore` (PHP) exists only because CI runs no service containers and the suite must pass with neither Redis nor MySQL. `tests/Datasets/Rotation.php` asserts both produce identical sequences — that shared dataset is the behavioural contract. The cache driver is *not* atomic across processes, so CI proves the algorithm but not the concurrency guarantee; the Redis-gated tests cover that and skip cleanly.

Smooth WRR breaks ties on the first candidate, so `TrafficRotator::activeDestinations()` carries an explicit `orderBy('id')`. Remove it and the sequences stop being reproducible.

phpredis applies the key prefix to `KEYS[n]` but not `ARGV[n]`. State keys must travel in KEYS; destination ids must travel in ARGV.
