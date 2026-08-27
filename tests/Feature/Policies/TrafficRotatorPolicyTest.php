<?php

use App\Enums\UserRole;
use App\Models\TrafficRotator;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('grants the owner every rotator ability', function (string $ability) {
    $owner = User::factory()->create();
    $rotator = TrafficRotator::factory()->for($owner)->create();

    expect(Gate::forUser($owner)->allows($ability, $rotator))->toBeTrue();
})->with(['view', 'update']);

test('denies every rotator ability to anyone but the owner', function (string $ability) {
    $stranger = User::factory()->create();
    $rotator = TrafficRotator::factory()->create();

    expect(Gate::forUser($stranger)->allows($ability, $rotator))->toBeFalse();
})->with(['view', 'update']);

test('denies with a not found status so a stranger learns nothing', function (string $ability) {
    $stranger = User::factory()->create();
    $rotator = TrafficRotator::factory()->create();

    expect(Gate::forUser($stranger)->inspect($ability, $rotator)->status())->toBe(404);
})->with(['view', 'update']);

test('gives an administrator no access to a rotator they do not own', function (UserRole $role) {
    $administrator = User::factory()->role($role)->create();
    $rotator = TrafficRotator::factory()->create();

    expect(Gate::forUser($administrator)->allows('view', $rotator))->toBeFalse();
})->with([
    'admin' => UserRole::ADMIN,
    'super admin' => UserRole::SUPER_ADMIN,
]);
