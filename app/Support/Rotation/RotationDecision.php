<?php

namespace App\Support\Rotation;

/**
 * Where a single visitor is being sent, and what that should be logged as.
 *
 * A null destination id means the rotator had no eligible destination and fell
 * back to its default url. That is recorded rather than discarded so leaked
 * traffic stays visible in the statistics.
 */
final readonly class RotationDecision
{
    public function __construct(
        public int $rotatorId,
        public ?int $destinationId,
        public string $url,
    ) {}
}
