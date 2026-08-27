<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrafficRotatorResource;
use App\Models\TrafficRotator;
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
}
