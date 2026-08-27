<?php

use App\Enums\RotatorStatus;
use App\Models\TrafficRotator;
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

test('creates a rotator owned by the caller', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);
    $response = $this->postJson(route('rotators.store'), [
        'name' => 'Onyx Traffic Network',
        'slug' => 'onyx-traffic-network',
        'default_destination_url' => 'https://sendonyx.com/fallback',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Onyx Traffic Network')
        ->assertJsonPath('data.status', RotatorStatus::ACTIVE->value);
    $this->assertDatabaseHas('traffic_rotators', [
        'slug' => 'onyx-traffic-network',
        'user_id' => $user->id,
        'default_destination_url' => 'https://sendonyx.com/fallback',
    ]);
});

test('derives the slug from the name when none is given', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson(route('rotators.store'), ['name' => 'Onyx Traffic Network']);

    $response->assertCreated()->assertJsonPath('data.slug', 'onyx-traffic-network');
});

test('assigns the rotator to the caller even when the payload names another owner', function () {
    $caller = User::factory()->create();
    $other = User::factory()->create();

    Sanctum::actingAs($caller);
    $response = $this->postJson(route('rotators.store'), [
        'name' => 'Hijack Attempt',
        'user_id' => $other->id,
    ]);

    $response->assertCreated();
    expect(TrafficRotator::query()->sole()->user_id)->toBe($caller->id);
});

test('returns 422 when the name is missing', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson(route('rotators.store'), []);

    $response->assertUnprocessable()->assertJsonValidationErrors([
        'name' => 'The name field is required.',
    ]);
});

test('returns 422 when the slug is already taken', function () {
    $user = User::factory()->create();
    TrafficRotator::factory()->create(['slug' => 'taken']);

    Sanctum::actingAs($user);
    $response = $this->postJson(route('rotators.store'), ['name' => 'Taken', 'slug' => 'taken']);

    $response->assertUnprocessable()->assertJsonValidationErrors([
        'slug' => 'The slug has already been taken.',
    ]);
});

test('returns 422 for a default destination url that is not http', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson(route('rotators.store'), [
        'name' => 'Scheme Check',
        'default_destination_url' => 'javascript:alert(1)',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('default_destination_url');
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

test('returns 404 when showing another user rotator', function () {
    $rotator = TrafficRotator::factory()->create();

    Sanctum::actingAs(User::factory()->create());
    $response = $this->getJson(route('rotators.show', $rotator));

    $response->assertNotFound();
});

test('updates only the fields the caller sends', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create(['name' => 'Original']);

    Sanctum::actingAs($user);
    $response = $this->patchJson(route('rotators.update', $rotator), [
        'status' => RotatorStatus::PAUSED->value,
    ]);

    $response->assertOk()->assertJsonPath('data.status', RotatorStatus::PAUSED->value);
    expect($rotator->refresh())
        ->status->toBe(RotatorStatus::PAUSED)
        ->name->toBe('Original');
});

test('keeps its own slug when updating a rotator', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create(['slug' => 'keeper']);

    Sanctum::actingAs($user);
    $response = $this->putJson(route('rotators.update', $rotator), [
        'name' => 'Renamed',
        'slug' => 'keeper',
    ]);

    $response->assertOk()->assertJsonPath('data.name', 'Renamed');
});

test('returns 422 for an unknown status', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();

    Sanctum::actingAs($user);
    $response = $this->putJson(route('rotators.update', $rotator), ['status' => 'archived']);

    $response->assertUnprocessable()->assertJsonValidationErrors('status');
});

test('returns 404 when updating another user rotator', function () {
    $rotator = TrafficRotator::factory()->create(['name' => 'Untouched']);

    Sanctum::actingAs(User::factory()->create());
    $response = $this->putJson(route('rotators.update', $rotator), ['name' => 'Taken Over']);

    $response->assertNotFound();
    expect($rotator->refresh()->name)->toBe('Untouched');
});

test('does not expose the internal primary key', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();

    Sanctum::actingAs($user);
    $response = $this->getJson(route('rotators.show', $rotator));

    $response->assertJsonMissingPath('data.id')->assertJsonMissingPath('data.user_id');
});
