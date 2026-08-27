<?php

namespace App\Support\Rotation;

/**
 * Turns a rotator snapshot into the destination for one visitor.
 *
 * The fallback chain is active destination, then the rotator's default url,
 * then nothing at all, which the caller renders as a 404.
 */
final readonly class DestinationResolver
{
    public function __construct(
        private RotationStateStore $state,
    ) {}

    /**
     * Pick where this visitor should be sent.
     */
    public function resolve(RotatorSnapshot $snapshot): ?RotationDecision
    {
        $candidate = $this->pick($snapshot);

        if ($candidate !== null) {
            return new RotationDecision($snapshot->rotatorId, $candidate->id, $candidate->url);
        }

        if ($snapshot->defaultDestinationUrl !== null) {
            return new RotationDecision($snapshot->rotatorId, null, $snapshot->defaultDestinationUrl);
        }

        return null;
    }

    /**
     * Advance the rotation cursor and resolve the winning candidate.
     *
     * The store is asked for an id rather than a candidate, and the id is looked
     * back up here, so a cursor left over from a stale candidate set can never
     * produce a destination the snapshot no longer contains.
     */
    private function pick(RotatorSnapshot $snapshot): ?DestinationCandidate
    {
        if (! $snapshot->hasCandidates()) {
            return null;
        }

        $destinationId = $this->state->advance($snapshot->rotatorId, $snapshot->weights());

        return $destinationId === null ? null : $snapshot->candidate($destinationId);
    }
}
