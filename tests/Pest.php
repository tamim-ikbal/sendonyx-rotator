<?php

use App\Models\TrafficRotator;
use App\Models\TrafficRotatorClick;
use App\Models\TrafficRotatorDestination;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create a destination whose rotator belongs to a freshly created owner.
 */
function reportDestination(string $createdAt = '2026-01-01 00:00:00'): TrafficRotatorDestination
{
    $rotator = TrafficRotator::factory()->for(User::factory())->create();

    return TrafficRotatorDestination::factory()->forRotator($rotator)->create([
        'created_at' => CarbonImmutable::parse($createdAt, 'UTC'),
    ]);
}

/**
 * Record clicks against a destination at a given moment.
 *
 * @return Collection<int, TrafficRotatorClick>
 */
function clicksAt(TrafficRotatorDestination $destination, string $moment, int $count = 1): Collection
{
    return TrafficRotatorClick::factory()
        ->count($count)
        ->forDestination($destination)
        ->at(CarbonImmutable::parse($moment, 'UTC'))
        ->create();
}
