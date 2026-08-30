<?php

use App\Enums\DeviceType;
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
 * Act as the owner of the destination and request its chart.
 */
function chart(TrafficRotatorDestination $destination, ?StatsRange $range = null): TestResponse
{
    Sanctum::actingAs($destination->rotator->user);

    $parameters = [$destination->rotator, $destination];

    if ($range !== null) {
        $parameters['range'] = $range->value;
    }

    return test()->getJson(route('rotators.destinations.chart', $parameters));
}

test('returns 401 without a token', function () {
    $destination = reportDestination();

    $this->getJson(route('rotators.destinations.chart', [$destination->rotator, $destination]))
        ->assertUnauthorized();
});

test('returns 404 for another user rotator', function () {
    $destination = reportDestination();

    Sanctum::actingAs(User::factory()->create());
    $response = $this->getJson(route('rotators.destinations.chart', [$destination->rotator, $destination]));

    $response->assertNotFound();
});

test('returns 404 for a destination belonging to a different rotator', function () {
    $destination = reportDestination();
    $sibling = TrafficRotator::factory()->for($destination->rotator->user)->create();

    Sanctum::actingAs($destination->rotator->user);
    $response = $this->getJson(route('rotators.destinations.chart', [$sibling, $destination]));

    $response->assertNotFound();
});

test('returns 422 for an unknown range', function () {
    $destination = reportDestination();

    Sanctum::actingAs($destination->rotator->user);
    $response = $this->getJson(route('rotators.destinations.chart', [
        $destination->rotator, $destination, 'range' => 'last_fortnight',
    ]));

    $response->assertUnprocessable()->assertJsonValidationErrors('range');
});

test('falls back to the last 30 days when no range is given', function () {
    $destination = reportDestination();

    $response = chart($destination);

    $response->assertOk()
        ->assertJsonPath('data.range.key', StatsRange::LAST_30_DAYS->value)
        ->assertJsonPath('data.range.start', '2026-07-29')
        ->assertJsonPath('data.range.end', '2026-08-27')
        ->assertJsonPath('data.range.granularity', 'day')
        ->assertJsonPath('data.destination.uuid', $destination->uuid);
});

test('leaves the headline figures to the stats endpoint', function () {
    $destination = reportDestination();

    $response = chart($destination);

    $response->assertJsonMissingPath('data.kpis');
});

test('zero fills the buckets with no clicks', function () {
    $destination = reportDestination();
    clicksAt($destination, '2026-08-25 10:00:00', 2);

    $response = chart($destination, StatsRange::LAST_7_DAYS);

    $response->assertJsonCount(7, 'data.series')
        ->assertJsonPath('data.series.0', ['bucket' => '2026-08-21', 'clicks' => 0, 'unique_visitors' => 0])
        ->assertJsonPath('data.series.4', ['bucket' => '2026-08-25', 'clicks' => 2, 'unique_visitors' => 2]);
});

test('buckets by the hour for today', function () {
    $destination = reportDestination();
    clicksAt($destination, '2026-08-27 09:15:00', 2);

    $response = chart($destination, StatsRange::TODAY);

    $response->assertJsonPath('data.range.granularity', 'hour')
        ->assertJsonCount(24, 'data.series')
        ->assertJsonPath('data.series.0.bucket', '2026-08-27 00:00')
        ->assertJsonPath('data.series.9', ['bucket' => '2026-08-27 09:00', 'clicks' => 2, 'unique_visitors' => 2]);
});

test('buckets by the month for this year', function () {
    $destination = reportDestination();
    clicksAt($destination, '2026-03-14 10:00:00', 3);
    clicksAt($destination, '2026-08-02 10:00:00');

    $response = chart($destination, StatsRange::THIS_YEAR);

    $response->assertJsonPath('data.range.granularity', 'month')
        ->assertJsonCount(8, 'data.series')
        ->assertJsonPath('data.series.0.bucket', '2026-01')
        ->assertJsonPath('data.series.2', ['bucket' => '2026-03', 'clicks' => 3, 'unique_visitors' => 3])
        ->assertJsonPath('data.series.7.bucket', '2026-08');
});

test('files a weekly bucket under the monday that opened the week', function () {
    $destination = reportDestination();
    clicksAt($destination, '2026-08-12 10:00:00', 2);

    $response = chart($destination, StatsRange::LAST_6_MONTHS);

    $response->assertJsonPath('data.range.granularity', 'week');

    $series = collect($response->json('data.series'));

    expect($series->firstWhere('bucket', '2026-08-10'))
        ->toBe(['bucket' => '2026-08-10', 'clicks' => 2, 'unique_visitors' => 2])
        ->and($series->pluck('bucket')->first())->toBe('2026-02-23')
        ->and($series->sum('clicks'))->toBe(2);
});

test('excludes bot clicks from the series but keeps unclassified ones', function () {
    $destination = reportDestination();
    $moment = CarbonImmutable::parse('2026-08-25 10:00:00', 'UTC');
    TrafficRotatorClick::factory()->count(2)->forDestination($destination)->at($moment)->create();
    TrafficRotatorClick::factory()->forDestination($destination)->at($moment)->bot()->create();
    TrafficRotatorClick::factory()->forDestination($destination)->at($moment)->unclassified()->create();

    $response = chart($destination, StatsRange::LAST_7_DAYS);

    $response->assertJsonPath('data.series.4.clicks', 3);
});

test('reports the click through rate against the rotator traffic', function () {
    $destination = reportDestination();
    $sibling = TrafficRotatorDestination::factory()->forRotator($destination->rotator)->create();
    clicksAt($destination, '2026-08-10 10:00:00', 3);
    clicksAt($sibling, '2026-08-10 11:00:00', 5);

    $response = chart($destination, StatsRange::LAST_30_DAYS);

    $response->assertJsonPath('data.tiles.click_through_rate.value', 37.5)
        ->assertJsonPath('data.tiles.click_through_rate.unit', 'percent');
});

test('averages the clicks over the days in the range', function () {
    $destination = reportDestination();
    clicksAt($destination, '2026-08-10 10:00:00', 3);

    $response = chart($destination, StatsRange::LAST_30_DAYS);

    $response->assertJsonPath('data.tiles.avg_daily_clicks.value', 0.1)
        ->assertJsonPath('data.tiles.avg_daily_clicks.unit', 'count');
});

test('reports the leading device as a share of the visitors', function () {
    $destination = reportDestination();
    $moment = CarbonImmutable::parse('2026-08-10 10:00:00', 'UTC');
    TrafficRotatorClick::factory()->count(5)->forDestination($destination)->at($moment)
        ->device(DeviceType::DESKTOP)->create();
    TrafficRotatorClick::factory()->count(3)->forDestination($destination)->at($moment)
        ->device(DeviceType::MOBILE)->create();

    $response = chart($destination, StatsRange::LAST_30_DAYS);

    $response->assertJsonPath('data.tiles.top_device', [
        'name' => DeviceType::DESKTOP->value,
        'visitor_rate' => 62.5,
    ]);
});

test('reports the leading country by name rather than by code', function () {
    $destination = reportDestination();
    $moment = CarbonImmutable::parse('2026-08-10 10:00:00', 'UTC');
    TrafficRotatorClick::factory()->count(3)->forDestination($destination)->at($moment)
        ->fromCountry('DE')->create();
    TrafficRotatorClick::factory()->forDestination($destination)->at($moment)
        ->fromCountry('FR')->create();

    $response = chart($destination, StatsRange::LAST_30_DAYS);

    $response->assertJsonPath('data.tiles.top_country', [
        'name' => 'Germany',
        'visitor_rate' => 75,
    ]);
});

test('reports a null country while nothing populates one', function () {
    $destination = reportDestination();
    TrafficRotatorClick::factory()->count(2)->forDestination($destination)
        ->at(CarbonImmutable::parse('2026-08-10 10:00:00', 'UTC'))
        ->fromCountry(null)
        ->create();

    $response = chart($destination, StatsRange::LAST_30_DAYS);

    $response->assertJsonPath('data.tiles.top_country', ['name' => null, 'visitor_rate' => null]);
});
