<?php

use App\Http\Controllers\Docs\ApiDocsController;
use App\Http\Controllers\Rotator\RedirectController;
use App\Http\Controllers\Rotator\RotatorController;
use Illuminate\Support\Facades\Route;

// The root of the site belongs to the rotator. The marketing page keeps the
// "home" route name, which the auth layouts and the profile deletion redirect
// both link to.
Route::get('/', RedirectController::class)->name('rotator.redirect');

Route::inertia('welcome', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    // Singular `rotator.` names, because the plural belongs to the API. Both
    // sets would otherwise collide, and duplicate route names only surface as
    // a failure at route:cache time.
    Route::get('rotators', [RotatorController::class, 'index'])->name('rotator.index');
    Route::get('rotators/create', [RotatorController::class, 'create'])->name('rotator.create');
    Route::post('rotators', [RotatorController::class, 'store'])->name('rotator.store');
    Route::get('rotators/{rotator}', [RotatorController::class, 'show'])->name('rotator.show');
    Route::get('rotators/{rotator}/edit', [RotatorController::class, 'edit'])->name('rotator.edit');
    Route::patch('rotators/{rotator}', [RotatorController::class, 'update'])->name('rotator.update');

    Route::get('docs', ApiDocsController::class)->name('docs.index');
});

require __DIR__.'/settings.php';
