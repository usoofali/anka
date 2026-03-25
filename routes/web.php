<?php

use App\Http\Controllers\Auth\RegisterGeoOptionsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ShipperController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::view('/terms', 'pages.legal.terms')->name('terms');
Route::view('/privacy', 'pages.legal.privacy')->name('privacy');

Route::middleware(['web', 'throttle:120,1'])->group(function (): void {
    Route::get('/register/geo/countries', [RegisterGeoOptionsController::class, 'countries'])->name('register.geo.countries');
    Route::get('/register/geo/states', [RegisterGeoOptionsController::class, 'states'])->name('register.geo.states');
    Route::get('/register/geo/cities', [RegisterGeoOptionsController::class, 'cities'])->name('register.geo.cities');
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');

    Route::get('/shippers/{shipper}', [ShipperController::class, 'show'])->name('shippers.show');
});

require __DIR__.'/settings.php';
