<?php

namespace App\Support\Rotation;

final class RotationStateKey
{
    /**
     * Build the state key for a rotator and its current candidate set.
     *
     * The fingerprint of destination ids and weights is part of the key, so any
     * weight edit, pause, addition or removal starts a fresh cycle on its own.
     * That covers write paths an observer cannot see, such as a bulk update or
     * a raw query.
     *
     * @param  array<int, int>  $weights  Destination id => weight.
     */
    public static function for(int $rotatorId, array $weights): string
    {
        ksort($weights);

        $pairs = [];

        foreach ($weights as $id => $weight) {
            $pairs[] = $id.':'.$weight;
        }

        return self::prefix($rotatorId).md5(implode('|', $pairs));
    }

    /**
     * Build the key of the set indexing every state key for a rotator.
     */
    public static function index(int $rotatorId): string
    {
        return self::prefix($rotatorId).'index';
    }

    /**
     * Build the shared prefix for a rotator's state keys.
     *
     * The braces are a Redis Cluster hash tag, keeping every cursor for one
     * rotator on a single slot.
     */
    private static function prefix(int $rotatorId): string
    {
        return 'wrr:{'.$rotatorId.'}:';
    }
}
