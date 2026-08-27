<?php

use App\Enums\StatsRange;
use App\Models\TrafficRotator;
use App\Models\TrafficRotatorClick;
use App\Models\TrafficRotatorDestination;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // Midday on Thursday 27 August 2026. Every window below is stated as the
    // dates it should produce from here, not derived from the implementation.
    $this->travelTo(CarbonImmutable::parse('2026-08-27 12:00:00', 'UTC'));
});

/**
 * Act as the owner of the destination and request its headline figures.
 */
function stats(TrafficRotatorDestination $destination, ?StatsRange $range = null): TestResponse
{
    Sanctum::actingAs($destination->rotator->user);

    $parameters = [$destination->rotator, $destination];

    if ($range !== null) {
        $parameters['range'] = $range->value;
    }

    return test()->getJson(route('rotators.destinations.stats', $parameters));
}

test('returns 401 without a token', function () {
    $destination = reportDestination();

    $this->getJson(route('rotators.destinations.stats', [$destination->rotator, $destination]))
        ->assertUnauthorized();
});

test('returns 404 for another user rotator', function () {
    $destination = reportDestination();

    Sanctum::actingAs(User::factory()->create());
    $response = $this->getJson(route('rotators.destinations.stats', [$destination->rotator, $destination]));

    $response->assertNotFound();
});

test('returns 404 for a destination belonging to a different rotator', function () {
    $destination = reportDestination();
    $sibling = TrafficRotator::factory()->for($destination->rotator->user)->create();

    Sanctum::actingAs($destination->rotator->user);
    $response = $this->getJson(route('rotators.destinations.stats', [$sibling, $destination]));

    $response->assertNotFound();
});

test('returns 422 for an unknown range', function () {
    $destination = reportDestination();

    Sanctum::actingAs($destination->rotator->user);
    $response = $this->getJson(route('rotators.destinations.stats', [
        $destination->rotator, $destination, 'range' => 'last_fortnight',
    ]));

    $response->assertUnprocessable()->assertJsonValidationErrors('range');
});

test('falls back to all time when no range is given', function () {
    $destination = reportDestination('2026-05-12 00:00:00');

    $response = stats($destination);

    $response->assertOk()
        ->assertJsonPath('data.range.key', StatsRange::ALL_TIME->value)
        ->assertJsonPath('data.range.start', '2026-05-12')
        ->assertJsonPath('data.range.end', '2026-08-27')
        ->assertJsonPath('data.destination.uuid', $destination->uuid);
});

test('counts every click since the destination appeared by default', function () {
    $destination = reportDestination('2026-01-01 00:00:00');
    clicksAt($destination, '2026-02-10 10:00:00', 2);
    clicksAt($destination, '2026-08-10 10:00:00', 3);

    $response = stats($destination);

    $response->assertJsonPath('data.kpis.clicks_received.value', 5);
});

test('leaves the series and the tiles to the chart endpoint', function () {
    $destination = reportDestination();

    $response = stats($destination);

    $response->assertJsonMissingPath('data.series')
        ->assertJsonMissingPath('data.tiles');
});

test('counts only the clicks inside the selected range', function () {
    $destination = reportDestination();
    clicksAt($destination, '2026-08-10 10:00:00', 3);
    clicksAt($destination, '2026-07-10 10:00:00');
    clicksAt($destination, '2026-05-01 10:00:00');

    $response = stats($destination, StatsRange::LAST_30_DAYS);

    $response->assertJsonPath('data.kpis.clicks_received.value', 3)
        ->assertJsonPath('data.kpis.clicks_received.previous_value', 1)
        ->assertJsonPath('data.kpis.clicks_received.indicator.position', 'up');
});

test('counts a repeat visitor once', function () {
    $destination = reportDestination();
    TrafficRotatorClick::factory()->count(3)->forDestination($destination)
        ->at(CarbonImmutable::parse('2026-08-10 10:00:00', 'UTC'))
        ->fromVisitor('aaaabbbbccccddddeeeeffff00001111')
        ->create();
    clicksAt($destination, '2026-08-11 10:00:00');

    $response = stats($destination, StatsRange::LAST_30_DAYS);

    $response->assertJsonPath('data.kpis.clicks_received.value', 4)
        ->assertJsonPath('data.kpis.unique_visitors.value', 2);
});

test('counts traffic received across the whole rotator', function () {
    $destination = reportDestination();
    $sibling = TrafficRotatorDestination::factory()->forRotator($destination->rotator)->create();
    clicksAt($destination, '2026-08-10 10:00:00', 3);
    clicksAt($sibling, '2026-08-10 11:00:00', 5);

    $response = stats($destination, StatsRange::LAST_30_DAYS);

    $response->assertJsonPath('data.kpis.clicks_received.value', 3)
        ->assertJsonPath('data.kpis.traffic_received.value', 8);
});

test('ignores rotator traffic from before the destination existed', function () {
    $destination = reportDestination('2026-08-20 00:00:00');
    $sibling = TrafficRotatorDestination::factory()->forRotator($destination->rotator)->create();
    clicksAt($sibling, '2026-08-01 10:00:00', 4);
    clicksAt($sibling, '2026-08-22 10:00:00', 2);
    clicksAt($destination, '2026-08-22 11:00:00');

    $response = stats($destination, StatsRange::LAST_30_DAYS);

    $response->assertJsonPath('data.kpis.traffic_received.value', 3);
});

test('excludes bot clicks but keeps unclassified ones', function () {
    $destination = reportDestination();
    $moment = CarbonImmutable::parse('2026-08-10 10:00:00', 'UTC');
    TrafficRotatorClick::factory()->count(2)->forDestination($destination)->at($moment)->create();
    TrafficRotatorClick::factory()->forDestination($destination)->at($moment)->bot()->create();
    TrafficRotatorClick::factory()->forDestination($destination)->at($moment)->unclassified()->create();

    $response = stats($destination, StatsRange::LAST_30_DAYS);

    $response->assertJsonPath('data.kpis.clicks_received.value', 3);
});

test('reports a flat indicator with no rate for all time', function () {
    $destination = reportDestination('2026-05-12 00:00:00');
    clicksAt($destination, '2026-06-01 10:00:00', 4);

    $response = stats($destination, StatsRange::ALL_TIME);

    $response->assertJsonPath('data.kpis.clicks_received.value', 4)
        ->assertJsonPath('data.kpis.clicks_received.previous_value', 0)
        ->assertJsonPath('data.kpis.clicks_received.indicator.position', 'flat')
        ->assertJsonPath('data.kpis.clicks_received.indicator.rate', null);
});

test('reports how long the destination has been active', function () {
    $destination = reportDestination('2026-05-12 00:00:00');

    $response = stats($destination, StatsRange::LAST_30_DAYS);

    $response->assertJsonPath('data.kpis.active_since', ['date' => '2026-05-12', 'days' => 107]);
});
