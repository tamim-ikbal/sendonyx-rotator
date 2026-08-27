<?php

use App\Http\Controllers\Rotator\RedirectController;
use Illuminate\Support\Facades\Route;

// The root of the site belongs to the rotator. The marketing page keeps the
// "home" route name, which the auth layouts and the profile deletion redirect
// both link to.
Route::get('/', RedirectController::class)->name('rotator.redirect');

Route::inertia('welcome', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
