<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TrafficRotator\DestinationChartRequest;
use App\Http\Resources\TrafficRotatorDestinationResource;
use App\Models\TrafficRotator;
use App\Models\TrafficRotatorDestination;
use App\Support\Stats\DestinationStatsBuilder;
use Illuminate\Http\JsonResponse;

class DestinationChartController extends Controller
{
    /**
     * Serve the analytics a destination's dashboard panel renders.
     */
    public function __invoke(
        DestinationChartRequest $request,
        TrafficRotator $rotator,
        TrafficRotatorDestination $destination,
        DestinationStatsBuilder $stats,
    ): JsonResponse {
        return new JsonResponse([
            'data' => [
                'destination' => TrafficRotatorDestinationResource::make($destination),
                ...$stats->build($destination, $request->statsRange()),
            ],
        ]);
    }
}
