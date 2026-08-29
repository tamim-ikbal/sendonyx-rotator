<?php

use App\Http\Controllers\Api\DestinationChartController;
use App\Http\Controllers\Api\DestinationStatsController;
use App\Http\Controllers\Api\RotatorTrafficByMemberController;
use App\Http\Controllers\Api\RotatorTrafficByPlanController;
use App\Http\Controllers\Api\TrafficRotatorController;
use App\Http\Controllers\Api\TrafficRotatorDestinationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('rotators', TrafficRotatorController::class)
        ->only(['index', 'show']);

    // Breakdowns of a rotator's own traffic. They bind only the rotator, so
    // they stay outside the scoped group the destination routes need.
    Route::get('rotators/{rotator}/traffic-by-plans', RotatorTrafficByPlanController::class)
        ->name('rotators.traffic-by-plans');

    Route::get('rotators/{rotator}/traffic-by-members', RotatorTrafficByMemberController::class)
        ->name('rotators.traffic-by-members');

    // Scoped bindings are what turn a destination belonging to another rotator
    // into a 404 at the router, before a controller or a policy sees it.
    Route::scopeBindings()->group(function (): void {
        Route::apiResource('rotators.destinations', TrafficRotatorDestinationController::class)
            ->only(['store', 'show', 'update']);

        Route::get('rotators/{rotator}/destinations/{destination}/stats', DestinationStatsController::class)
            ->name('rotators.destinations.stats');

        Route::get('rotators/{rotator}/destinations/{destination}/chart', DestinationChartController::class)
            ->name('rotators.destinations.chart');
    });
});
