<?php

use App\Enums\Granularity;
use App\Enums\StatsRange;
use App\Support\Stats\StatsDateRange;
use Carbon\CarbonImmutable;

/**
 * Build a window as it would be seen at midday on Thursday 27 August 2026.
 */
function windowFor(StatsRange $range, string $createdAt = '2026-01-01 00:00:00'): StatsDateRange
{
    return StatsDateRange::for(
        $range,
        CarbonImmutable::parse($createdAt, 'UTC'),
        'UTC',
        CarbonImmutable::parse('2026-08-27 12:00:00', 'UTC'),
    );
}

test('covers the current day in hourly buckets for today', function () {
    $window = windowFor(StatsRange::TODAY);

    expect($window->granularity)->toBe(Granularity::HOUR)
        ->and($window->start->toDateTimeString())->toBe('2026-08-27 00:00:00')
        ->and($window->bucketKeys())->toHaveCount(24)
        ->and($window->bucketKeys()[0])->toBe('2026-08-27 00:00')
        ->and($window->days())->toBe(1);
});

test('covers seven days including today for last 7 days', function () {
    $window = windowFor(StatsRange::LAST_7_DAYS);

    expect($window->start->toDateString())->toBe('2026-08-21')
        ->and($window->days())->toBe(7)
        ->and($window->bucketKeys())->toHaveCount(7)
        ->and($window->bucketKeys())->toBe([
            '2026-08-21', '2026-08-22', '2026-08-23', '2026-08-24',
            '2026-08-25', '2026-08-26', '2026-08-27',
        ]);
});

test('places the baseline immediately before the current period', function () {
    $window = windowFor(StatsRange::LAST_30_DAYS);

    expect($window->start->toDateString())->toBe('2026-07-29')
        ->and($window->previousStart->toDateString())->toBe('2026-06-29')
        ->and($window->days())->toBe(30);
});

test('anchors every weekly bucket to a monday', function () {
    $window = windowFor(StatsRange::LAST_6_MONTHS);

    $keys = $window->bucketKeys();

    expect($window->granularity)->toBe(Granularity::WEEK)
        ->and($keys[0])->toBe('2026-02-23')
        ->and(end($keys))->toBe('2026-08-24');
    foreach ($keys as $key) {
        expect(CarbonImmutable::parse($key)->dayName)->toBe('Monday');
    }
});

test('runs from january to the current month for this year', function () {
    $window = windowFor(StatsRange::THIS_YEAR);

    expect($window->granularity)->toBe(Granularity::MONTH)
        ->and($window->bucketKeys())->toBe([
            '2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06', '2026-07', '2026-08',
        ])
        ->and($window->days())->toBe(239);
});

test('collapses the baseline onto the start for all time', function () {
    $window = windowFor(StatsRange::ALL_TIME, '2026-05-12 09:30:00');

    expect($window->start->toDateTimeString())->toBe('2026-05-12 09:30:00')
        ->and($window->previousStart)->toEqual($window->start)
        ->and($window->windowStart)->toEqual($window->start);
});

test('never scans back past the moment the destination appeared', function () {
    $window = windowFor(StatsRange::LAST_30_DAYS, '2026-08-20 08:00:00');

    expect($window->previousStart->toDateString())->toBe('2026-06-29')
        ->and($window->windowStart->toDateTimeString())->toBe('2026-08-20 08:00:00');
});

test('keeps the baseline when the destination is older than it', function () {
    $window = windowFor(StatsRange::LAST_30_DAYS, '2025-01-01 00:00:00');

    expect($window->windowStart)->toEqual($window->previousStart);
});
