<?php

use App\Support\Rotation\CacheRotationStateStore;
use App\Support\Rotation\RedisRotationStateStore;
use App\Support\Rotation\RotationStateKey;
use App\Support\Rotation\RotationStateStore;
use App\Support\Rotation\SmoothWeightedRoundRobin;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Support\Facades\Redis;

/**
 * Drive a store for the given number of selections.
 *
 * @param  array<int, int>  $weights
 * @return array<int, int|null>
 */
function drive(RotationStateStore $store, array $weights, int $picks): array
{
    $sequence = [];

    for ($i = 0; $i < $picks; $i++) {
        $sequence[] = $store->advance(1, $weights);
    }

    return $sequence;
}

function cacheStore(): CacheRotationStateStore
{
    return new CacheRotationStateStore(
        app(CacheFactory::class)->store('array'),
        new SmoothWeightedRoundRobin,
        86400,
    );
}

function redisStore(): RedisRotationStateStore
{
    return new RedisRotationStateStore(app(RedisFactory::class), 'default', 86400);
}

test('the cache store reproduces the golden sequence', function (array $weights, int $picks, array $expected) {
    expect(drive(cacheStore(), $weights, $picks))->toBe($expected);
})->with('smooth weighted round robin sequences');

test('the redis store reproduces the golden sequence', function (array $weights, int $picks, array $expected) {
    $this->skipUnlessRedisIsAvailable();
    Redis::connection()->flushdb();

    expect(drive(redisStore(), $weights, $picks))->toBe($expected);
})->with('smooth weighted round robin sequences');

test('a weight change starts a fresh cycle', function () {
    $store = cacheStore();

    // Consume two of the three picks in the 2/1 cycle.
    expect(drive($store, [1 => 2, 2 => 1], 2))->toBe([1, 2]);

    // Reweighting produces a different fingerprint, so the cursor restarts
    // rather than resuming mid cycle.
    expect(drive($store, [1 => 1, 2 => 2], 2))->toBe([2, 1]);
});

test('the cache store forgets every cursor for a rotator', function () {
    $store = cacheStore();

    $store->advance(1, [1 => 2, 2 => 1]);
    $store->advance(1, [1 => 1, 2 => 1]);

    $store->forget(1);

    // A forgotten cursor restarts, so the first pick of the 2/1 cycle returns.
    expect($store->advance(1, [1 => 2, 2 => 1]))->toBe(1);
});

test('the redis store expires and forgets its cursors', function () {
    $this->skipUnlessRedisIsAvailable();
    Redis::connection()->flushdb();

    $store = redisStore();
    $store->advance(7, [1 => 2, 2 => 1]);

    $key = RotationStateKey::for(7, [1 => 2, 2 => 1]);

    expect(Redis::connection()->ttl($key))->toBeGreaterThan(86000)
        ->and(Redis::connection()->exists($key))->toBe(1);

    $store->forget(7);

    expect(Redis::connection()->exists($key))->toBe(0);
});

test('the redis store recovers from corrupted state', function () {
    $this->skipUnlessRedisIsAvailable();
    Redis::connection()->flushdb();

    $store = redisStore();
    $weights = [1 => 3, 2 => 1, 3 => 1];
    $key = RotationStateKey::for(1, $weights);

    Redis::connection()->hset($key, '1', 'not-a-number');
    Redis::connection()->hset($key, '2', '999999');

    // The sum-to-zero invariant is broken, so the cycle restarts cleanly.
    expect(drive($store, $weights, 5))->toBe([1, 2, 1, 3, 1]);
});

test('both stores agree on the resolved state key', function () {
    expect(RotationStateKey::for(1, [1 => 3, 2 => 1]))
        ->toBe(RotationStateKey::for(1, [2 => 1, 1 => 3]));
});

test('an empty weight set selects nothing', function () {
    expect(cacheStore()->advance(1, []))->toBeNull();
});
