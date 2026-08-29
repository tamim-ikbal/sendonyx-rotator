<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrafficRotator;
use App\Support\Stats\RotatorTrafficBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class RotatorTrafficByMemberController extends Controller
{
    /**
     * Total a rotator's clicks per customer, busiest customer first.
     *
     * The route says members and the payload says customer_uid: the column is
     * named for the identifier it stores, and the identifier comes from the
     * billing side. Lifetime figures, same as the plan breakdown.
     */
    public function __invoke(TrafficRotator $rotator, RotatorTrafficBuilder $traffic): JsonResponse
    {
        Gate::authorize('view', $rotator);

        return new JsonResponse(['data' => $traffic->byCustomer($rotator)]);
    }
}
