<?php

namespace App\Observers;

use App\Models\TrafficRotator;
use App\Support\Rotation\RotationStateStore;
use App\Support\Rotation\RotatorCache;

/**
 * Keeps the redirect hot path's caches honest when a rotator changes.
 */
final readonly class TrafficRotatorObserver
{
    public function __construct(
        private RotatorCache $cache,
        private RotationStateStore $state,
    ) {}

    /**
     * Handle the TrafficRotator "saved" event.
     */
    public function saved(TrafficRotator $rotator): void
    {
        $this->invalidate($rotator);
    }

    /**
     * Handle the TrafficRotator "deleted" event.
     */
    public function deleted(TrafficRotator $rotator): void
    {
        $this->invalidate($rotator);
    }

    /**
     * Discard everything the redirect route has memoised about the rotator.
     *
     * The order matters. Flushing the rotation cursor first would leave a
     * window in which a concurrent request rebuilds it from the snapshot that
     * has not been flushed yet, putting the stale candidate set straight back.
     */
    private function invalidate(TrafficRotator $rotator): void
    {
        $this->cache->flush();

        $this->state->forget($rotator->id);
    }
}
