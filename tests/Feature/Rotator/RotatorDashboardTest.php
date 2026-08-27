<?php

use App\Enums\RotatorStatus;
use App\Models\TrafficRotator;
use App\Models\TrafficRotatorClick;
use App\Models\TrafficRotatorDestination;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('rotator.index'))->assertRedirect(route('login'));
});

test('lists only the rotators owned by the user', function () {
    $user = User::factory()->create();
    $mine = TrafficRotator::factory()->for($user)->create();
    TrafficRotator::factory()->create();

    $this->actingAs($user)
        ->get(route('rotator.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('rotators/index')
            ->has('rotators', 1)
            ->where('rotators.0.uuid', $mine->uuid),
        );
});

test('counts the destinations and the views of each listed rotator', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();
    $destination = TrafficRotatorDestination::factory()->forRotator($rotator)->create();
    TrafficRotatorDestination::factory()->forRotator($rotator)->create();
    TrafficRotatorClick::factory()->count(2)->forDestination($destination)->create();

    $this->actingAs($user)
        ->get(route('rotator.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rotators.0.destinations_count', 2)
            ->where('rotators.0.views_count', 2),
        );
});

test('leaves bot clicks out of the listed view count', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();
    $destination = TrafficRotatorDestination::factory()->forRotator($rotator)->create();
    TrafficRotatorClick::factory()->forDestination($destination)->create();
    TrafficRotatorClick::factory()->forDestination($destination)->bot()->create();

    $this->actingAs($user)
        ->get(route('rotator.index'))
        ->assertInertia(fn (Assert $page) => $page->where('rotators.0.views_count', 1));
});

test('offers the create action only while the user owns no rotator', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('rotator.index'))
        ->assertInertia(fn (Assert $page) => $page->where('canCreateRotator', true));

    TrafficRotator::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('rotator.index'))
        ->assertInertia(fn (Assert $page) => $page->where('canCreateRotator', false));
});

test('renders the create form', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('rotator.create'))
        ->assertInertia(fn (Assert $page) => $page->component('rotators/create'));
});

test('refuses the create form to a user who already owns a rotator', function () {
    $user = User::factory()->create();
    TrafficRotator::factory()->for($user)->create();

    $this->actingAs($user)->get(route('rotator.create'))->assertForbidden();
});

test('creates a rotator owned by the user and redirects to its traffic', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->from(route('rotator.create'))
        ->post(route('rotator.store'), [
            'name' => 'Onyx Traffic Network',
            'slug' => 'onyx-traffic-network',
            'status' => RotatorStatus::PAUSED->value,
            'default_destination_url' => 'https://sendonyx.com/fallback',
        ]);

    $rotator = TrafficRotator::sole();

    $response->assertSessionHasNoErrors()->assertRedirect(route('rotator.show', $rotator));
    $this->assertDatabaseHas('traffic_rotators', [
        'user_id' => $user->id,
        'slug' => 'onyx-traffic-network',
        'status' => RotatorStatus::PAUSED->value,
        'default_destination_url' => 'https://sendonyx.com/fallback',
    ]);
});

test('rejects a second rotator', function () {
    $user = User::factory()->create();
    TrafficRotator::factory()->for($user)->create();

    $this->actingAs($user)
        ->from(route('rotator.index'))
        ->post(route('rotator.store'), [
            'name' => 'Second Network',
            'slug' => 'second-network',
        ])
        ->assertForbidden();

    $this->assertDatabaseCount('traffic_rotators', 1);
});

test('rejects a rotator with no name', function () {
    $response = $this->actingAs(User::factory()->create())
        ->from(route('rotator.create'))
        ->post(route('rotator.store'), ['slug' => 'onyx-traffic-network']);

    $response->assertRedirect(route('rotator.create'))
        ->assertSessionHasErrors(['name' => 'The name field is required.']);
    $this->assertDatabaseCount('traffic_rotators', 0);
});

test('shows the total views alongside the clicks each destination took', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->withDefaultUrl()->create();
    $first = TrafficRotatorDestination::factory()->forRotator($rotator)->create();
    $second = TrafficRotatorDestination::factory()->forRotator($rotator)->create();

    TrafficRotatorClick::factory()->count(3)->forDestination($first)->create();
    TrafficRotatorClick::factory()->forDestination($second)->create();
    // The rotator had nothing eligible to send this visitor to, so the click
    // belongs to the rotator's total but to no destination's.
    TrafficRotatorClick::factory()->fallback($rotator)->create();
    TrafficRotatorClick::factory()->forDestination($first)->bot()->create();

    $this->actingAs($user)
        ->get(route('rotator.show', $rotator))
        ->assertInertia(fn (Assert $page) => $page
            ->component('rotators/show')
            ->where('rotator.uuid', $rotator->uuid)
            ->where('totalViews', 5)
            ->has('destinations', 2)
            ->where('destinations.0.uuid', $first->uuid)
            ->where('destinations.0.views_count', 3)
            ->where('destinations.1.views_count', 1),
        );
});

test('never exposes the internal primary key of a rotator or a destination', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();
    TrafficRotatorDestination::factory()->forRotator($rotator)->create();

    $this->actingAs($user)
        ->get(route('rotator.show', $rotator))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('rotator.id')
            ->missing('destinations.0.id'),
        );
});

test('renders the edit form for a rotator the user owns', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('rotator.edit', $rotator))
        ->assertInertia(fn (Assert $page) => $page
            ->component('rotators/edit')
            ->where('rotator.uuid', $rotator->uuid),
        );
});

test('updates a rotator and redirects to its traffic', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();

    $response = $this->actingAs($user)
        ->from(route('rotator.edit', $rotator))
        ->patch(route('rotator.update', $rotator), [
            'name' => 'Renamed Network',
            'slug' => $rotator->slug,
            'status' => RotatorStatus::PAUSED->value,
            'default_destination_url' => null,
        ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('rotator.show', $rotator));
    expect($rotator->refresh())
        ->name->toBe('Renamed Network')
        ->status->toBe(RotatorStatus::PAUSED);
});

test('rejects a status the rotator has no case for', function () {
    $user = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($user)->create();

    $response = $this->actingAs($user)
        ->from(route('rotator.edit', $rotator))
        ->patch(route('rotator.update', $rotator), ['status' => 'archived']);

    $response->assertSessionHasErrors('status');
    expect($rotator->refresh()->status)->toBe(RotatorStatus::ACTIVE);
});

test('returns 404 rather than 403 for a rotator owned by someone else', function (string $name, string $method) {
    $rotator = TrafficRotator::factory()->create();

    $this->actingAs(User::factory()->create())
        ->call($method, route($name, $rotator))
        ->assertNotFound();
})->with([
    'show' => ['rotator.show', 'GET'],
    'edit' => ['rotator.edit', 'GET'],
    'update' => ['rotator.update', 'PATCH'],
]);
