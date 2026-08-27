<?php

use App\Http\Controllers\Settings\ApiTokenController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/tokens', [ApiTokenController::class, 'edit'])->name('api-tokens.edit');
    Route::post('settings/tokens', [ApiTokenController::class, 'store'])->name('api-tokens.store');
    Route::delete('settings/tokens/{token}', [ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
});
