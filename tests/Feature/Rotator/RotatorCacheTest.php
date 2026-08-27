<?php

use App\Models\TrafficRotator;
use App\Models\TrafficRotatorDestination;
use App\Support\Rotation\RotatorCache;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Support\Facades\Cache;

/**
 * Read whatever the snapshot cache put in the store, without hydrating it.
 */
function cachedSnapshotPayload(): mixed
{
    return Cache::store(config()->string('rotator.cache_store'))
        ->get(config()->string('rotator.cache_key'));
}

test('caches the rotator as plain data rather than as objects', function () {
    $rotator = TrafficRotator::factory()->create();
    TrafficRotatorDestination::factory()->forRotator($rotator)->create();

    app(RotatorCache::class)->snapshot();

    $stored = cachedSnapshotPayload();

    // Laravel refuses to unserialize classes out of a cache store unless the
    // application opts back in, so an object in this payload would return as
    // __PHP_Incomplete_Class from redis, database and file while passing
    // against the non serializing array store the suite runs on.
    expect(unserialize(serialize($stored), ['allowed_classes' => false]))->toBe($stored);
});

test('survives a round trip through a store that really serializes', function () {
    $this->skipUnlessRedisIsAvailable();

    $rotator = TrafficRotator::factory()->withDefaultUrl('https://sendonyx.com/fallback')->create();
    $heavy = TrafficRotatorDestination::factory()->forRotator($rotator)->weight(3)->create();
    $light = TrafficRotatorDestination::factory()->forRotator($rotator)->weight(1)->create();

    $cache = new RotatorCache(app(CacheFactory::class), 'redis', 'rotator:test:v1', [300, 3600]);

    $cache->flush();
    $cache->snapshot();

    $snapshot = $cache->snapshot();

    expect($snapshot?->rotatorId)->toBe($rotator->id)
        ->and($snapshot?->defaultDestinationUrl)->toBe('https://sendonyx.com/fallback')
        ->and($snapshot?->weights())->toBe([$heavy->id => 3, $light->id => 1])
        ->and($snapshot?->candidate($light->id)?->url)->toBe($light->url);

    $cache->flush();
});

test('caches the absence of a rotator instead of querying for it again', function () {
    $cache = app(RotatorCache::class);

    expect($cache->snapshot())->toBeNull();

    // Cache::flexible() treats null as a miss, so a site with no rotator would
    // hit the database on every request unless the negative answer is stored
    // as something else.
    expect(cachedSnapshotPayload())->toBeFalse();
});

test('forgets the snapshot and the timestamp that keeps it fresh', function () {
    $rotator = TrafficRotator::factory()->create();
    TrafficRotatorDestination::factory()->forRotator($rotator)->create();

    $cache = app(RotatorCache::class);
    $cache->snapshot();

    $cache->flush();

    expect(cachedSnapshotPayload())->toBeNull()
        ->and(Cache::store(config()->string('rotator.cache_store'))
            ->get('illuminate:cache:flexible:created:'.config()->string('rotator.cache_key')))
        ->toBeNull();
});
