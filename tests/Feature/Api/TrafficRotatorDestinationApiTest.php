<?php

use App\Enums\DestinationStatus;
use App\Models\TrafficRotator;
use App\Models\TrafficRotatorDestination;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('returns 401 without a token', function () {
    $rotator = TrafficRotator::factory()->create();

    $this->postJson(route('rotators.destinations.store', $rotator), [
        'url' => 'https://offers.example.com/one',
    ])->assertUnauthorized();
});

test('adds a destination inheriting the owner of the rotator', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();

    Sanctum::actingAs($user);
    $response = $this->postJson(route('rotators.destinations.store', $rotator), [
        'url' => 'https://offers.example.com/one',
        'weight' => 3,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.weight', 3)
        ->assertJsonPath('data.status', DestinationStatus::ACTIVE->value);
    $this->assertDatabaseHas('traffic_rotator_destinations', [
        'rotator_id' => $rotator->id,
        'user_id' => $user->id,
        'url' => 'https://offers.example.com/one',
    ]);
});

test('stores the plan and customer a destination is attributed to', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();

    Sanctum::actingAs($user);
    $response = $this->postJson(route('rotators.destinations.store', $rotator), [
        'url' => 'https://offers.example.com/one',
        'plan_uid' => 'plan_pro',
        'customer_uid' => 'cus_4b7e',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.plan_uid', 'plan_pro')
        ->assertJsonPath('data.customer_uid', 'cus_4b7e');
    $this->assertDatabaseHas('traffic_rotator_destinations', [
        'rotator_id' => $rotator->id,
        'plan_uid' => 'plan_pro',
        'customer_uid' => 'cus_4b7e',
    ]);
});

test('leaves the plan and customer null when neither is sent', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();

    Sanctum::actingAs($user);
    $response = $this->postJson(route('rotators.destinations.store', $rotator), [
        'url' => 'https://offers.example.com/one',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.plan_uid', null)
        ->assertJsonPath('data.customer_uid', null);
});

test('returns 422 for an identifier longer than the column', function (string $field) {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();

    Sanctum::actingAs($user);
    $response = $this->postJson(route('rotators.destinations.store', $rotator), [
        'url' => 'https://offers.example.com/one',
        $field => str_repeat('a', 256),
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors($field);
})->with(['plan_uid', 'customer_uid']);

test('defaults a new destination to weight one', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();

    Sanctum::actingAs($user);
    $response = $this->postJson(route('rotators.destinations.store', $rotator), [
        'url' => 'https://offers.example.com/one',
    ]);

    $response->assertCreated()->assertJsonPath('data.weight', 1);
});

test('returns 404 when adding a destination to another user rotator', function () {
    $rotator = TrafficRotator::factory()->create();

    Sanctum::actingAs(User::factory()->create());
    $response = $this->postJson(route('rotators.destinations.store', $rotator), [
        'url' => 'https://offers.example.com/one',
    ]);

    $response->assertNotFound();
    $this->assertDatabaseCount('traffic_rotator_destinations', 0);
});

test('returns 422 when the url is missing', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();

    Sanctum::actingAs($user);
    $response = $this->postJson(route('rotators.destinations.store', $rotator), []);

    $response->assertUnprocessable()->assertJsonValidationErrors([
        'url' => 'The url field is required.',
    ]);
});

test('returns 422 for a url that is not http', function (string $url) {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();

    Sanctum::actingAs($user);
    $response = $this->postJson(route('rotators.destinations.store', $rotator), ['url' => $url]);

    $response->assertUnprocessable()->assertJsonValidationErrors('url');
})->with([
    'javascript' => 'javascript:alert(1)',
    'data' => 'data:text/html;base64,PHNjcmlwdD4=',
    'ftp' => 'ftp://files.example.com/offer',
    'not a url' => 'offers.example.com',
]);

test('returns 422 for a weight outside the priority tiers', function (int $weight) {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();

    Sanctum::actingAs($user);
    $response = $this->postJson(route('rotators.destinations.store', $rotator), [
        'url' => 'https://offers.example.com/one',
        'weight' => $weight,
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('weight');
})->with([0, 4]);

test('shows a destination', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();
    $destination = TrafficRotatorDestination::factory()->forRotator($rotator)
        ->create(['url' => 'https://offers.example.com/one']);

    Sanctum::actingAs($user);
    $response = $this->getJson(route('rotators.destinations.show', [$rotator, $destination]));

    $response->assertOk()
        ->assertJsonPath('data.uuid', $destination->uuid)
        ->assertJsonPath('data.url', 'https://offers.example.com/one');
});

test('returns 404 for a destination belonging to a different rotator', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();
    $sibling = TrafficRotator::factory()->for($user)->create();
    $destination = TrafficRotatorDestination::factory()->forRotator($sibling)->create();

    Sanctum::actingAs($user);
    $response = $this->getJson(route('rotators.destinations.show', [$rotator, $destination]));

    $response->assertNotFound();
});

test('returns 404 when showing a destination of another user rotator', function () {
    $rotator = TrafficRotator::factory()->create();
    $destination = TrafficRotatorDestination::factory()->forRotator($rotator)->create();

    Sanctum::actingAs(User::factory()->create());
    $response = $this->getJson(route('rotators.destinations.show', [$rotator, $destination]));

    $response->assertNotFound();
});

test('updates only the fields the caller sends', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();
    $destination = TrafficRotatorDestination::factory()->forRotator($rotator)
        ->weight(1)->create(['url' => 'https://offers.example.com/one']);

    Sanctum::actingAs($user);
    $response = $this->patchJson(route('rotators.destinations.update', [$rotator, $destination]), [
        'weight' => 3,
    ]);

    $response->assertOk()->assertJsonPath('data.weight', 3);
    expect($destination->refresh())
        ->weight->toBe(3)
        ->url->toBe('https://offers.example.com/one');
});

test('reattributes a destination to another plan', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();
    $destination = TrafficRotatorDestination::factory()->forRotator($rotator)
        ->forPlan('plan_starter')->forCustomer('cus_4b7e')->create();

    Sanctum::actingAs($user);
    $response = $this->patchJson(route('rotators.destinations.update', [$rotator, $destination]), [
        'plan_uid' => 'plan_pro',
    ]);

    $response->assertOk()->assertJsonPath('data.plan_uid', 'plan_pro');
    expect($destination->refresh())
        ->plan_uid->toBe('plan_pro')
        ->customer_uid->toBe('cus_4b7e');
});

test('detaches a destination from its plan when sent an explicit null', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();
    $destination = TrafficRotatorDestination::factory()->forRotator($rotator)
        ->forPlan('plan_starter')->create();

    Sanctum::actingAs($user);
    $response = $this->patchJson(route('rotators.destinations.update', [$rotator, $destination]), [
        'plan_uid' => null,
    ]);

    $response->assertOk()->assertJsonPath('data.plan_uid', null);
    expect($destination->refresh()->plan_uid)->toBeNull();
});

test('returns 404 when updating a destination of another user rotator', function () {
    $rotator = TrafficRotator::factory()->create();
    $destination = TrafficRotatorDestination::factory()->forRotator($rotator)->weight(1)->create();

    Sanctum::actingAs(User::factory()->create());
    $response = $this->putJson(route('rotators.destinations.update', [$rotator, $destination]), [
        'weight' => 3,
    ]);

    $response->assertNotFound();
    expect($destination->refresh()->weight)->toBe(1);
});

test('takes a paused destination out of the rotation immediately', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();
    TrafficRotatorDestination::factory()->forRotator($rotator)
        ->create(['url' => 'https://kept.example.com']);
    $paused = TrafficRotatorDestination::factory()->forRotator($rotator)
        ->create(['url' => 'https://retired.example.com']);
    $this->get(route('rotator.redirect'))->assertRedirect();

    Sanctum::actingAs($user);
    $this->patchJson(route('rotators.destinations.update', [$rotator, $paused]), [
        'status' => DestinationStatus::PAUSED->value,
    ])->assertOk();

    $this->get(route('rotator.redirect'))->assertRedirect('https://kept.example.com');
    $this->get(route('rotator.redirect'))->assertRedirect('https://kept.example.com');
});
