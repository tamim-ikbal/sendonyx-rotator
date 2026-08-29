<?php

use App\Models\TrafficRotator;
use App\Models\TrafficRotatorClick;
use App\Models\TrafficRotatorDestination;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('returns 401 without a token', function (string $route) {
    $rotator = TrafficRotator::factory()->create();

    $this->getJson(route($route, $rotator))->assertUnauthorized();
})->with(['rotators.traffic-by-plans', 'rotators.traffic-by-members']);

test('returns 404 for another user rotator', function (string $route) {
    $rotator = TrafficRotator::factory()->create();

    Sanctum::actingAs(User::factory()->create());
    $this->getJson(route($route, $rotator))->assertNotFound();
})->with(['rotators.traffic-by-plans', 'rotators.traffic-by-members']);

test('totals clicks per plan, busiest plan first', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();
    $starter = TrafficRotatorDestination::factory()->forRotator($rotator)->forPlan('plan_starter')->create();
    $pro = TrafficRotatorDestination::factory()->forRotator($rotator)->forPlan('plan_pro')->create();
    TrafficRotatorClick::factory()->count(2)->forDestination($starter)->create();
    TrafficRotatorClick::factory()->count(5)->forDestination($pro)->create();

    Sanctum::actingAs($user);
    $response = $this->getJson(route('rotators.traffic-by-plans', $rotator));

    $response->assertOk()->assertExactJson(['data' => [
        ['plan_uid' => 'plan_pro', 'clicks' => 5],
        ['plan_uid' => 'plan_starter', 'clicks' => 2],
    ]]);
});

test('adds up the destinations sharing a plan', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();
    $first = TrafficRotatorDestination::factory()->forRotator($rotator)->forPlan('plan_pro')->create();
    $second = TrafficRotatorDestination::factory()->forRotator($rotator)->forPlan('plan_pro')->create();
    TrafficRotatorClick::factory()->count(3)->forDestination($first)->create();
    TrafficRotatorClick::factory()->count(4)->forDestination($second)->create();

    Sanctum::actingAs($user);
    $response = $this->getJson(route('rotators.traffic-by-plans', $rotator));

    $response->assertOk()->assertExactJson(['data' => [
        ['plan_uid' => 'plan_pro', 'clicks' => 7],
    ]]);
});

test('leaves unattributed traffic out of the plan breakdown', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();
    $attributed = TrafficRotatorDestination::factory()->forRotator($rotator)->forPlan('plan_pro')->create();
    $unattributed = TrafficRotatorDestination::factory()->forRotator($rotator)->create();
    TrafficRotatorClick::factory()->forDestination($attributed)->create();
    TrafficRotatorClick::factory()->count(6)->forDestination($unattributed)->create();
    TrafficRotatorClick::factory()->count(6)->fallback($rotator)->create();

    Sanctum::actingAs($user);
    $response = $this->getJson(route('rotators.traffic-by-plans', $rotator));

    $response->assertOk()->assertExactJson(['data' => [
        ['plan_uid' => 'plan_pro', 'clicks' => 1],
    ]]);
});

test('excludes bot clicks from the plan breakdown', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();
    $destination = TrafficRotatorDestination::factory()->forRotator($rotator)->forPlan('plan_pro')->create();
    TrafficRotatorClick::factory()->count(2)->forDestination($destination)->create();
    TrafficRotatorClick::factory()->count(9)->forDestination($destination)->bot()->create();
    TrafficRotatorClick::factory()->forDestination($destination)->unclassified()->create();

    Sanctum::actingAs($user);
    $response = $this->getJson(route('rotators.traffic-by-plans', $rotator));

    $response->assertOk()->assertJsonPath('data.0.clicks', 3);
});

test('reports no plans when nothing is attributed', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();

    Sanctum::actingAs($user);
    $response = $this->getJson(route('rotators.traffic-by-plans', $rotator));

    $response->assertOk()->assertExactJson(['data' => []]);
});

test('counts only the clicks on the rotator in the url', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();
    $other = TrafficRotator::factory()->for($user)->create();
    $mine = TrafficRotatorDestination::factory()->forRotator($rotator)->forPlan('plan_pro')->create();
    $theirs = TrafficRotatorDestination::factory()->forRotator($other)->forPlan('plan_pro')->create();
    TrafficRotatorClick::factory()->forDestination($mine)->create();
    TrafficRotatorClick::factory()->count(4)->forDestination($theirs)->create();

    Sanctum::actingAs($user);
    $response = $this->getJson(route('rotators.traffic-by-plans', $rotator));

    $response->assertOk()->assertExactJson(['data' => [
        ['plan_uid' => 'plan_pro', 'clicks' => 1],
    ]]);
});

test('totals clicks per customer, busiest customer first', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();
    $quiet = TrafficRotatorDestination::factory()->forRotator($rotator)->forCustomer('cus_quiet')->create();
    $busy = TrafficRotatorDestination::factory()->forRotator($rotator)->forCustomer('cus_busy')->create();
    TrafficRotatorClick::factory()->forDestination($quiet)->create();
    TrafficRotatorClick::factory()->count(3)->forDestination($busy)->create();

    Sanctum::actingAs($user);
    $response = $this->getJson(route('rotators.traffic-by-members', $rotator));

    $response->assertOk()->assertExactJson(['data' => [
        ['customer_uid' => 'cus_busy', 'clicks' => 3],
        ['customer_uid' => 'cus_quiet', 'clicks' => 1],
    ]]);
});

test('leaves unattributed traffic out of the customer breakdown', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();
    $attributed = TrafficRotatorDestination::factory()->forRotator($rotator)->forCustomer('cus_busy')->create();
    $unattributed = TrafficRotatorDestination::factory()->forRotator($rotator)->create();
    TrafficRotatorClick::factory()->count(2)->forDestination($attributed)->create();
    TrafficRotatorClick::factory()->count(5)->forDestination($unattributed)->create();

    Sanctum::actingAs($user);
    $response = $this->getJson(route('rotators.traffic-by-members', $rotator));

    $response->assertOk()->assertExactJson(['data' => [
        ['customer_uid' => 'cus_busy', 'clicks' => 2],
    ]]);
});

test('leaves earned traffic on the plan that earned it when a destination moves', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();
    $destination = TrafficRotatorDestination::factory()->forRotator($rotator)->forPlan('plan_starter')->create();
    TrafficRotatorClick::factory()->count(4)->forDestination($destination)->create();

    $destination->update(['plan_uid' => 'plan_pro']);
    TrafficRotatorClick::factory()->count(1)->forDestination($destination->refresh())->create();

    Sanctum::actingAs($user);
    $response = $this->getJson(route('rotators.traffic-by-plans', $rotator));

    $response->assertOk()->assertExactJson(['data' => [
        ['plan_uid' => 'plan_starter', 'clicks' => 4],
        ['plan_uid' => 'plan_pro', 'clicks' => 1],
    ]]);
});

test('reports a plan no destination carries any more', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();
    $destination = TrafficRotatorDestination::factory()->forRotator($rotator)->forPlan('plan_pro')->create();
    TrafficRotatorClick::factory()->count(3)->forDestination($destination)
        ->attributedTo('plan_retired', 'cus_retired')->create();

    Sanctum::actingAs($user);

    $this->getJson(route('rotators.traffic-by-plans', $rotator))
        ->assertOk()
        ->assertExactJson(['data' => [['plan_uid' => 'plan_retired', 'clicks' => 3]]]);

    $this->getJson(route('rotators.traffic-by-members', $rotator))
        ->assertOk()
        ->assertExactJson(['data' => [['customer_uid' => 'cus_retired', 'clicks' => 3]]]);
});

test('does not backfill clicks recorded before a destination was attributed', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();
    $destination = TrafficRotatorDestination::factory()->forRotator($rotator)->create();
    TrafficRotatorClick::factory()->count(5)->forDestination($destination)->create();

    $destination->update(['plan_uid' => 'plan_pro']);

    Sanctum::actingAs($user);
    $response = $this->getJson(route('rotators.traffic-by-plans', $rotator));

    $response->assertOk()->assertExactJson(['data' => []]);
});

test('breaks a tie on the identifier so the order is stable', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();
    $b = TrafficRotatorDestination::factory()->forRotator($rotator)->forCustomer('cus_b')->create();
    $a = TrafficRotatorDestination::factory()->forRotator($rotator)->forCustomer('cus_a')->create();
    TrafficRotatorClick::factory()->count(2)->forDestination($b)->create();
    TrafficRotatorClick::factory()->count(2)->forDestination($a)->create();

    Sanctum::actingAs($user);
    $response = $this->getJson(route('rotators.traffic-by-members', $rotator));

    $response->assertOk()
        ->assertJsonPath('data.0.customer_uid', 'cus_a')
        ->assertJsonPath('data.1.customer_uid', 'cus_b');
});
