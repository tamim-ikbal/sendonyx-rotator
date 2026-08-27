<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TrafficRotator\StoreDestinationRequest;
use App\Http\Requests\TrafficRotator\UpdateDestinationRequest;
use App\Http\Resources\TrafficRotatorDestinationResource;
use App\Models\TrafficRotator;
use App\Models\TrafficRotatorDestination;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Destinations are always addressed through their rotator.
 *
 * The routes bind them with scoped bindings, so a destination belonging to a
 * different rotator never reaches these methods; and the parent rotator is the
 * only thing authorised, since a destination is owned by whoever owns it.
 */
class TrafficRotatorDestinationController extends Controller
{
    /**
     * Add a destination to a rotator.
     */
    public function store(StoreDestinationRequest $request, TrafficRotator $rotator): JsonResponse
    {
        $destination = new TrafficRotatorDestination($request->validated());

        // Neither key is fillable: the rotator comes from the url and the owner
        // is inherited, so a request can never point a destination at someone
        // else's rotator or reassign one it owns.
        $destination->rotator_id = $rotator->id;
        $destination->user_id = $rotator->user_id;

        $destination->save();

        return TrafficRotatorDestinationResource::make($destination)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    /**
     * Show a single destination.
     */
    public function show(TrafficRotator $rotator, TrafficRotatorDestination $destination): TrafficRotatorDestinationResource
    {
        Gate::authorize('view', $rotator);

        return TrafficRotatorDestinationResource::make($destination);
    }

    /**
     * Update a destination.
     */
    public function update(
        UpdateDestinationRequest $request,
        TrafficRotator $rotator,
        TrafficRotatorDestination $destination,
    ): TrafficRotatorDestinationResource {
        $destination->update($request->validated());

        return TrafficRotatorDestinationResource::make($destination);
    }
}
