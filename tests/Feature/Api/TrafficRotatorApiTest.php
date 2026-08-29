<?php

use App\Models\TrafficRotator;
use App\Models\TrafficRotatorClick;
use App\Models\TrafficRotatorDestination;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('returns 401 without a token', function () {
    $this->getJson(route('rotators.index'))->assertUnauthorized();
});

test('accepts a personal access token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('dashboard')->plainTextToken;

    $response = $this->withToken($token)->getJson(route('rotators.index'));

    $response->assertOk();
});

test('lists only the rotators owned by the caller', function () {
    $user = User::factory()->create();
    $mine = TrafficRotator::factory()->for($user)->create();
    TrafficRotator::factory()->create();

    Sanctum::actingAs($user);
    $response = $this->getJson(route('rotators.index'));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $mine->uuid);
});

test('counts the destinations of each listed rotator', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();
    TrafficRotatorDestination::factory()->count(3)->forRotator($rotator)->create();

    Sanctum::actingAs($user);
    $response = $this->getJson(route('rotators.index'));

    $response->assertJsonPath('data.0.destinations_count', 3);
});

test('shows a rotator with its destinations', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();
    $destination = TrafficRotatorDestination::factory()->forRotator($rotator)->weight(3)->create();

    Sanctum::actingAs($user);
    $response = $this->getJson(route('rotators.show', $rotator));

    $response->assertOk()
        ->assertJsonPath('data.uuid', $rotator->uuid)
        ->assertJsonPath('data.destinations.0.uuid', $destination->uuid)
        ->assertJsonPath('data.destinations.0.weight', 3);
});

test('reports the lifetime traffic of the rotator it shows', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();
    $destination = TrafficRotatorDestination::factory()->forRotator($rotator)->create();
    TrafficRotatorClick::factory()->count(2)->forDestination($destination)->fromVisitor('visitor-one')->create();
    TrafficRotatorClick::factory()->forDestination($destination)->fromVisitor('visitor-two')->create();
    TrafficRotatorClick::factory()->fallback($rotator)->fromVisitor('visitor-two')->create();

    Sanctum::actingAs($user);
    $response = $this->getJson(route('rotators.show', $rotator));

    $response->assertOk()
        ->assertJsonPath('data.total_clicks', 4)
        ->assertJsonPath('data.unique_visitors', 2);
});

test('excludes bot clicks from the traffic it reports', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();
    $destination = TrafficRotatorDestination::factory()->forRotator($rotator)->create();
    TrafficRotatorClick::factory()->forDestination($destination)->fromVisitor('human')->create();
    TrafficRotatorClick::factory()->forDestination($destination)->fromVisitor('unclassified')->unclassified()->create();
    TrafficRotatorClick::factory()->count(9)->forDestination($destination)->fromVisitor('crawler')->bot()->create();

    Sanctum::actingAs($user);
    $response = $this->getJson(route('rotators.show', $rotator));

    $response->assertJsonPath('data.total_clicks', 2)
        ->assertJsonPath('data.unique_visitors', 2);
});

test('counts only the clicks on the rotator it shows', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();
    $other = TrafficRotator::factory()->for($user)->create();
    TrafficRotatorClick::factory()->fallback($rotator)->create();
    TrafficRotatorClick::factory()->count(6)->fallback($other)->create();

    Sanctum::actingAs($user);
    $response = $this->getJson(route('rotators.show', $rotator));

    $response->assertJsonPath('data.total_clicks', 1);
});

test('reports zero traffic for a rotator that has had none', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();

    Sanctum::actingAs($user);
    $response = $this->getJson(route('rotators.show', $rotator));

    $response->assertJsonPath('data.total_clicks', 0)
        ->assertJsonPath('data.unique_visitors', 0);
});

test('omits the traffic totals from the rotator list', function () {
    $user = User::factory()->create();
    TrafficRotator::factory()->for($user)->create();

    Sanctum::actingAs($user);
    $response = $this->getJson(route('rotators.index'));

    $response->assertOk()
        ->assertJsonMissingPath('data.0.total_clicks')
        ->assertJsonMissingPath('data.0.unique_visitors');
});

test('returns 404 when showing another user rotator', function () {
    $rotator = TrafficRotator::factory()->create();

    Sanctum::actingAs(User::factory()->create());
    $response = $this->getJson(route('rotators.show', $rotator));

    $response->assertNotFound();
});

test('does not expose the internal primary key', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();

    Sanctum::actingAs($user);
    $response = $this->getJson(route('rotators.show', $rotator));

    $response->assertJsonMissingPath('data.id')->assertJsonMissingPath('data.user_id');
});
