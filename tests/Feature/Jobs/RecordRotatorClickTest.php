<?php

use App\Jobs\RecordRotatorClick;
use App\Models\TrafficRotator;
use App\Models\TrafficRotatorClick;
use App\Models\TrafficRotatorDestination;

test('writes nothing when the rotator was deleted before the worker ran', function () {
    $rotator = TrafficRotator::factory()->create();
    $destination = TrafficRotatorDestination::factory()->forRotator($rotator)->create();

    $rotatorId = $rotator->id;
    $destinationId = $destination->id;
    $rotator->delete();

    RecordRotatorClick::dispatchSync($rotatorId, $destinationId, str_repeat('a', 32), hash('sha256', 'ip'), null, null);

    expect(TrafficRotatorClick::query()->count())->toBe(0);
});

test('writes nothing when the destination was deleted before the worker ran', function () {
    $rotator = TrafficRotator::factory()->create();
    $destination = TrafficRotatorDestination::factory()->forRotator($rotator)->create();

    $destinationId = $destination->id;
    $destination->delete();

    RecordRotatorClick::dispatchSync($rotator->id, $destinationId, str_repeat('a', 32), hash('sha256', 'ip'), null, null);

    expect(TrafficRotatorClick::query()->count())->toBe(0);
});

test('still logs a fallback hit after its rotator loses every destination', function () {
    $rotator = TrafficRotator::factory()->withDefaultUrl()->create();

    RecordRotatorClick::dispatchSync($rotator->id, null, str_repeat('a', 32), hash('sha256', 'ip'), null, null);

    expect(TrafficRotatorClick::query()->sole()->destination_id)->toBeNull();
});

test('stamps the click with the attribution the destination carries', function () {
    $rotator = TrafficRotator::factory()->create();
    $destination = TrafficRotatorDestination::factory()->forRotator($rotator)
        ->forPlan('plan_pro')->forCustomer('cus_4b7e')->create();

    RecordRotatorClick::dispatchSync($rotator->id, $destination->id, str_repeat('a', 32), hash('sha256', 'ip'), null, null);

    expect(TrafficRotatorClick::query()->sole())
        ->plan_uid->toBe('plan_pro')
        ->customer_uid->toBe('cus_4b7e');
});

test('keeps the attribution a click was stamped with after the destination moves', function () {
    $rotator = TrafficRotator::factory()->create();
    $destination = TrafficRotatorDestination::factory()->forRotator($rotator)->forPlan('plan_starter')->create();

    RecordRotatorClick::dispatchSync($rotator->id, $destination->id, str_repeat('a', 32), hash('sha256', 'ip'), null, null);
    $destination->update(['plan_uid' => 'plan_pro']);

    expect(TrafficRotatorClick::query()->sole()->plan_uid)->toBe('plan_starter');
});

test('records an unattributed destination without attribution', function () {
    $rotator = TrafficRotator::factory()->create();
    $destination = TrafficRotatorDestination::factory()->forRotator($rotator)->create();

    RecordRotatorClick::dispatchSync($rotator->id, $destination->id, str_repeat('a', 32), hash('sha256', 'ip'), null, null);

    expect(TrafficRotatorClick::query()->sole())
        ->plan_uid->toBeNull()
        ->customer_uid->toBeNull();
});

test('records a fallback hit without attribution', function () {
    $rotator = TrafficRotator::factory()->withDefaultUrl()->create();

    RecordRotatorClick::dispatchSync($rotator->id, null, str_repeat('a', 32), hash('sha256', 'ip'), null, null);

    expect(TrafficRotatorClick::query()->sole())
        ->plan_uid->toBeNull()
        ->customer_uid->toBeNull();
});

test('cuts an oversized referrer down to what the column holds', function () {
    $destination = TrafficRotatorDestination::factory()->create();

    RecordRotatorClick::dispatchSync(
        $destination->rotator_id,
        $destination->id,
        str_repeat('a', 32),
        hash('sha256', 'ip'),
        null,
        'https://example.com/?q='.str_repeat('x', 3000),
    );

    expect(TrafficRotatorClick::query()->sole()->referrer)->toHaveLength(2048);
});
