<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TrafficRotator\DestinationStatsRequest;
use App\Http\Resources\TrafficRotatorDestinationResource;
use App\Models\TrafficRotator;
use App\Models\TrafficRotatorDestination;
use App\Support\Stats\DestinationStatsBuilder;
use Illuminate\Http\JsonResponse;

class DestinationStatsController extends Controller
{
    /**
     * Serve the headline figures a destination's dashboard panel shows.
     */
    public function __invoke(
        DestinationStatsRequest $request,
        TrafficRotator $rotator,
        TrafficRotatorDestination $destination,
        DestinationStatsBuilder $stats,
    ): JsonResponse {
        return new JsonResponse([
            'data' => [
                'destination' => TrafficRotatorDestinationResource::make($destination),
                ...$stats->stats($destination, $request->statsRange()),
            ],
        ]);
    }
}
