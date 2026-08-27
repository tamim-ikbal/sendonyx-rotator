<?php

namespace App\Support\Rotation;

interface RotationStateStore
{
    /**
     * Advance the rotator's cursor and return the winning destination id.
     *
     * @param  array<int, int>  $weights  Destination id => weight, ordered by destination id ascending.
     */
    public function advance(int $rotatorId, array $weights): ?int;

    /**
     * Discard every stored cursor for the rotator.
     */
    public function forget(int $rotatorId): void;
}
