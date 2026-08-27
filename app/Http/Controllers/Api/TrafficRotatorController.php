<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TrafficRotator\StoreRotatorRequest;
use App\Http\Requests\TrafficRotator\UpdateRotatorRequest;
use App\Http\Resources\TrafficRotatorResource;
use App\Models\TrafficRotator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TrafficRotatorController extends Controller
{
    /**
     * List the rotators owned by the authenticated user.
     *
     * The query starts from the user's own relation, so ownership is enforced
     * by the scope rather than by filtering results afterwards.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $rotators = $request->user()->rotators()
            ->withCount('destinations')
            ->orderBy('id')
            ->paginate();

        return TrafficRotatorResource::collection($rotators);
    }

    /**
     * Create a rotator owned by the authenticated user.
     */
    public function store(StoreRotatorRequest $request): JsonResponse
    {
        $rotator = new TrafficRotator($request->validated());

        // user_id is deliberately absent from the model's fillable set: it is
        // the ownership boundary the whole policy rests on, and a request must
        // never be able to name it.
        $rotator->user_id = $request->user()->id;

        $rotator->save();

        return TrafficRotatorResource::make($rotator->loadCount('destinations'))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    /**
     * Show a rotator together with its destinations.
     *
     * The destinations travel with the rotator because the API exposes no
     * destination index; this is the only way to enumerate them.
     */
    public function show(TrafficRotator $rotator): TrafficRotatorResource
    {
        Gate::authorize('view', $rotator);

        return TrafficRotatorResource::make($rotator->load('destinations'));
    }

    /**
     * Update a rotator.
     */
    public function update(UpdateRotatorRequest $request, TrafficRotator $rotator): TrafficRotatorResource
    {
        $rotator->update($request->validated());

        return TrafficRotatorResource::make($rotator->load('destinations'));
    }
}
