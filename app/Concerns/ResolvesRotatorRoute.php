<?php

namespace App\Concerns;

use App\Models\TrafficRotator;
use App\Models\TrafficRotatorDestination;

/**
 * Typed accessors for the models a nested rotator route has already bound.
 *
 * Form requests need the bound parent both to authorise and to build rules
 * against it, and `route()` hands back mixed.
 */
trait ResolvesRotatorRoute
{
    /**
     * Get the rotator bound to the current route.
     */
    protected function rotator(): TrafficRotator
    {
        /** @var TrafficRotator $rotator */
        $rotator = $this->route('rotator');

        return $rotator;
    }

    /**
     * Get the destination bound to the current route.
     */
    protected function destination(): TrafficRotatorDestination
    {
        /** @var TrafficRotatorDestination $destination */
        $destination = $this->route('destination');

        return $destination;
    }
}
