<?php

namespace App\Support\Rotation;

use App\Models\TrafficRotator;
use App\Models\TrafficRotatorDestination;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;

/**
 * The redirect route's read path for the rotator it serves.
 *
 * Every hit would otherwise cost two queries, so the snapshot is cached and
 * both observers flush it on any write that could change rotation.
 */
final readonly class RotatorCache
{
    /**
     * The value stored to represent "there is no rotator".
     *
     * Cache::flexible() treats a null value as a miss, so a site with no
     * rotator would query the database on every request and never cache the
     * answer. Storing false makes the negative result cacheable.
     */
    private const MISS = false;

    public function __construct(
        private CacheFactory $cache,
        private string $store,
        private string $key,
        /** @var array{0: int, 1: int} */
        private array $ttl,
    ) {}

    /**
     * Get the snapshot of the rotator serving the redirect route.
     *
     * Only plain arrays cross the cache. Laravel's cache stores refuse to
     * unserialize classes unless the application opts back in, so a cached
     * object would return as __PHP_Incomplete_Class from every store that
     * serializes. The array store used in tests never serializes, which makes
     * this the one part of the hot path a passing suite cannot vouch for.
     */
    public function snapshot(): ?RotatorSnapshot
    {
        $cached = $this->repository()->flexible(
            $this->key,
            $this->ttl,
            fn (): array|false => $this->build(),
        );

        return is_array($cached) ? RotatorSnapshot::fromArray($cached) : null;
    }

    /**
     * Discard the cached snapshot.
     *
     * The companion timestamp Cache::flexible() writes alongside the value has
     * to go too. Leaving it behind is harmless but pointless, and removing the
     * value alone is what makes the next read a miss.
     */
    public function flush(): void
    {
        $repository = $this->repository();

        $repository->forget($this->key);
        $repository->forget(CacheRepository::FLEXIBLE_CREATED_KEY_PREFIX.$this->key);
    }

    /**
     * Read the rotator and its rotation candidates from the database.
     *
     * @return array<string, mixed>|false
     */
    private function build(): array|false
    {
        $rotator = TrafficRotator::query()->oldest('id')->first();

        if ($rotator === null) {
            return self::MISS;
        }

        $candidates = $rotator->activeDestinations()
            ->get(['id', 'url', 'weight'])
            ->mapWithKeys(fn (TrafficRotatorDestination $destination): array => [
                $destination->id => new DestinationCandidate(
                    $destination->id,
                    $destination->url,
                    $destination->weight,
                ),
            ])
            ->all();

        return (new RotatorSnapshot(
            $rotator->id,
            $rotator->default_destination_url,
            $candidates,
        ))->toArray();
    }

    /**
     * Resolve the cache store holding the snapshot.
     */
    private function repository(): Repository
    {
        return $this->cache->store($this->store);
    }
}
