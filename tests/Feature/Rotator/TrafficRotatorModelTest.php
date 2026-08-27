<?php

use App\Models\TrafficRotator;
use App\Models\TrafficRotatorClick;
use App\Models\TrafficRotatorDestination;

test('a rotator generates a uuid while keeping an auto incrementing primary key', function () {
    $first = TrafficRotator::factory()->create();
    $second = TrafficRotator::factory()->create();

    expect($first->uuid)->toBeString()
        ->and($first->uuid)->toHaveLength(36)
        ->and($first->id)->toBeInt()
        ->and($second->id)->toBe($first->id + 1);
});

test('rotators and destinations are resolved by uuid in routes', function () {
    expect((new TrafficRotator)->getRouteKeyName())->toBe('uuid')
        ->and((new TrafficRotatorDestination)->getRouteKeyName())->toBe('uuid');
});

test('active destinations exclude paused ones and are ordered by id', function () {
    $rotator = TrafficRotator::factory()->create();

    $first = TrafficRotatorDestination::factory()->forRotator($rotator)->create();
    TrafficRotatorDestination::factory()->forRotator($rotator)->paused()->create();
    $third = TrafficRotatorDestination::factory()->forRotator($rotator)->create();

    expect($rotator->activeDestinations()->pluck('id')->all())
        ->toBe([$first->id, $third->id]);
});

test('the bot exclusion scope drops bots but keeps unclassified clicks', function () {
    $destination = TrafficRotatorDestination::factory()->create();

    TrafficRotatorClick::factory()->forDestination($destination)->count(3)->create();
    TrafficRotatorClick::factory()->forDestination($destination)->bot()->count(4)->create();
    TrafficRotatorClick::factory()->forDestination($destination)->unclassified()->count(2)->create();

    expect(TrafficRotatorClick::query()->excludingBots()->count())->toBe(5);
});

test('a fallback click is stored without a destination', function () {
    $rotator = TrafficRotator::factory()->create();

    $click = TrafficRotatorClick::factory()->fallback($rotator)->create();

    expect($click->destination_id)->toBeNull()
        ->and($click->rotator_id)->toBe($rotator->id);
});
