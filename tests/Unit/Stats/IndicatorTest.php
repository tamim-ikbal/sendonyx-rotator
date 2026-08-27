<?php

use App\Enums\IndicatorPosition;
use App\Support\Stats\Indicator;

test('reports the movement between two periods', function (
    float $current,
    float $previous,
    IndicatorPosition $position,
    ?float $rate,
) {
    $indicator = Indicator::between($current, $previous);

    expect($indicator)->position->toBe($position)->rate->toBe($rate);
})->with([
    'growth' => [120.0, 100.0, IndicatorPosition::UP, 20.0],
    'decline' => [80.0, 100.0, IndicatorPosition::DOWN, 20.0],
    'unchanged' => [100.0, 100.0, IndicatorPosition::FLAT, 0.0],
    'collapse to nothing' => [0.0, 100.0, IndicatorPosition::DOWN, 100.0],
    'growth from nothing' => [5.0, 0.0, IndicatorPosition::UP, 100.0],
    'nothing either period' => [0.0, 0.0, IndicatorPosition::FLAT, 0.0],
]);

test('reports the rate as a magnitude, leaving direction to the position', function () {
    $indicator = Indicator::between(50.0, 200.0);

    expect($indicator)->rate->toBe(75.0)->position->toBe(IndicatorPosition::DOWN);
});

test('reports a null rate when the range has no baseline', function () {
    $indicator = Indicator::between(4200.0, 0.0, hasBaseline: false);

    expect($indicator)->position->toBe(IndicatorPosition::FLAT)->rate->toBeNull();
});

test('serialises to the shape the dashboard reads', function () {
    expect(Indicator::between(110.0, 100.0)->toArray())
        ->toBe(['rate' => 10.0, 'position' => 'up']);
});
