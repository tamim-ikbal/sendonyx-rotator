<?php

namespace App\Observers;

use App\Models\TrafficRotatorDestination;
use App\Support\Rotation\RotationStateStore;
use App\Support\Rotation\RotatorCache;

/**
 * Keeps the redirect hot path's caches honest when a destination changes.
 *
 * A weight edit, a pause or a new destination all change the rotation, and the
 * cursor from the previous candidate set means nothing against the new one.
 */
final readonly class TrafficRotatorDestinationObserver
{
    public function __construct(
        private RotatorCache $cache,
        private RotationStateStore $state,
    ) {}

    /**
     * Handle the TrafficRotatorDestination "saved" event.
     */
    public function saved(TrafficRotatorDestination $destination): void
    {
        $this->invalidate($destination);
    }

    /**
     * Handle the TrafficRotatorDestination "deleted" event.
     */
    public function deleted(TrafficRotatorDestination $destination): void
    {
        $this->invalidate($destination);
    }

    /**
     * Discard the snapshot before the cursor, never the other way around.
     *
     * Reversing the order lets a concurrent request rebuild the cursor from the
     * stale snapshot, reinstating the candidate set that just changed.
     */
    private function invalidate(TrafficRotatorDestination $destination): void
    {
        $this->cache->flush();

        $this->state->forget($destination->rotator_id);
    }
}
