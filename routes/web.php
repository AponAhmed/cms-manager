<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use App\Http\Controllers\SiteController;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

// Site management routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('sites', SiteController::class)->except(['edit', 'update']);
    Route::get('sites/{site}/logs', [SiteController::class, 'logs'])->name('sites.logs');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
