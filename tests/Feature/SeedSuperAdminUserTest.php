<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('the migration seeds a verified super admin', function () {
    $user = User::query()->where('email', 'admin@sendonyx.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Super Admin')
        ->and($user->role)->toBe(UserRole::SUPER_ADMIN)
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('password', $user->password))->toBeTrue();
});
