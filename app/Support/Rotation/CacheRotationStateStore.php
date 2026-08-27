<?php

namespace App\Support\Rotation;

use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;

/**
 * Portable rotation state, running the algorithm in PHP over a cache store.
 *
 * This is the driver used wherever Redis is unavailable, including the test
 * suite and CI. Pointed at a lock capable store such as database or redis it is
 * cross process safe, just slower than the Lua path; pointed at the array store
 * it degrades to a single process, which is all a test run needs.
 */
final readonly class CacheRotationStateStore implements RotationStateStore
{
    public function __construct(
        private Repository $cache,
        private SmoothWeightedRoundRobin $algorithm,
        private int $ttlSeconds,
    ) {}

    /**
     * Advance the rotator's cursor and return the winning destination id.
     *
     * @param  array<int, int>  $weights
     */
    public function advance(int $rotatorId, array $weights): ?int
    {
        if ($weights === []) {
            return null;
        }

        $key = RotationStateKey::for($rotatorId, $weights);
        $lock = $this->lockProvider()?->lock($key.':lock', 5);

        if ($lock === null) {
            return $this->select($rotatorId, $key, $weights);
        }

        return $lock->block(3, fn (): ?int => $this->select($rotatorId, $key, $weights));
    }

    /**
     * Discard every stored cursor for the rotator.
     */
    public function forget(int $rotatorId): void
    {
        $index = RotationStateKey::index($rotatorId);

        /** @var array<int, string> $keys */
        $keys = $this->cache->get($index, []);

        foreach ($keys as $key) {
            $this->cache->forget($key);
        }

        $this->cache->forget($index);
    }

    /**
     * Read the stored cursor, advance it and write it back.
     *
     * @param  array<int, int>  $weights
     */
    private function select(int $rotatorId, string $key, array $weights): ?int
    {
        /** @var array<int, mixed> $state */
        $state = $this->cache->get($key, []);

        $result = $this->algorithm->advance($weights, $state);

        $this->cache->put($key, $result['currentWeights'], $this->ttlSeconds);

        $this->remember($rotatorId, $key);

        return $result['destinationId'];
    }

    /**
     * Track a cursor key so it can be forgotten later.
     *
     * Cache stores offer no key scanning, so the keys a rotator has used are
     * recorded against a companion index key.
     */
    private function remember(int $rotatorId, string $key): void
    {
        $index = RotationStateKey::index($rotatorId);

        /** @var array<int, string> $keys */
        $keys = $this->cache->get($index, []);

        if (in_array($key, $keys, true)) {
            return;
        }

        $keys[] = $key;

        $this->cache->put($index, $keys, $this->ttlSeconds);
    }

    /**
     * Resolve the lock provider backing the cache store, when it offers one.
     *
     * Without a lock provider the selection still runs, but concurrent workers
     * can interleave. That is acceptable for the single process test suite and
     * is the reason the Redis driver exists for production.
     */
    private function lockProvider(): ?LockProvider
    {
        $store = $this->cache instanceof CacheRepository ? $this->cache->getStore() : null;

        return $store instanceof LockProvider ? $store : null;
    }
}
