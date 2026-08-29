<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrafficRotator;
use App\Support\Stats\RotatorTrafficBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class RotatorTrafficByPlanController extends Controller
{
    /**
     * Total a rotator's clicks per plan, busiest plan first.
     *
     * Lifetime figures: there is no range filter yet, and adding one later is
     * a `whereBetween` on the same query rather than a new endpoint.
     */
    public function __invoke(TrafficRotator $rotator, RotatorTrafficBuilder $traffic): JsonResponse
    {
        Gate::authorize('view', $rotator);

        return new JsonResponse(['data' => $traffic->byPlan($rotator)]);
    }
}
