<?php

use App\Enums\UserRole;
use App\Models\User;

test('super admins pass the super admin and admin checks', function () {
    $user = User::factory()->role(UserRole::SUPER_ADMIN)->create();

    expect($user->isSuperAdmin())->toBeTrue()
        ->and($user->isAdmin())->toBeTrue()
        ->and($user->isCustomer())->toBeFalse();
});

test('admins pass only the admin check', function () {
    $user = User::factory()->role(UserRole::ADMIN)->create();

    expect($user->isSuperAdmin())->toBeFalse()
        ->and($user->isAdmin())->toBeTrue()
        ->and($user->isCustomer())->toBeFalse();
});

test('customers pass only the customer check', function () {
    $user = User::factory()->role(UserRole::CUSTOMER)->create();

    expect($user->isSuperAdmin())->toBeFalse()
        ->and($user->isAdmin())->toBeFalse()
        ->and($user->isCustomer())->toBeTrue();
});

test('new users default to the customer role', function () {
    $user = User::query()->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password',
    ]);

    expect($user->fresh()->role)->toBe(UserRole::CUSTOMER);
});

test('the role attribute is not mass assignable', function () {
    $user = User::query()->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password',
        'role' => UserRole::SUPER_ADMIN,
    ]);

    expect($user->fresh()->role)->toBe(UserRole::CUSTOMER);
});
