<?php

namespace App\Policies;

use App\Models\TrafficRotator;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * The single authorisation point for rotators and everything nested under one.
 *
 * Destinations have no policy of their own: a destination is owned by whoever
 * owns its rotator, so reading one is a `view` on the parent and writing one
 * (including creating it) is an `update` on the parent.
 */
class TrafficRotatorPolicy
{
    /**
     * Determine whether the user can create a rotator.
     *
     * The product is limited to a single rotator per user for now, so the
     * ability is denied rather than the second rotator being silently ignored.
     */
    public function create(User $user): Response
    {
        return $user->rotators()->doesntExist()
            ? Response::allow()
            : Response::deny(__('You can only have one rotator.'));
    }

    /**
     * Determine whether the user can view the rotator.
     */
    public function view(User $user, TrafficRotator $rotator): Response
    {
        return $this->owns($user, $rotator);
    }

    /**
     * Determine whether the user can update the rotator or its destinations.
     */
    public function update(User $user, TrafficRotator $rotator): Response
    {
        return $this->owns($user, $rotator);
    }

    /**
     * Deny a request from anyone but the owner, without confirming the record exists.
     *
     * A 403 tells the caller that the uuid they guessed is real. Rotator uuids
     * are the only handle the API exposes, so cross-owner requests answer 404
     * exactly as an unknown uuid would.
     */
    private function owns(User $user, TrafficRotator $rotator): Response
    {
        return $user->id === $rotator->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
