<?php

use App\Http\Controllers\ApartmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

// Public Routes
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/apartments', [ApartmentController::class, 'index'])->name('apartments.index');
Route::get('/apartments/{slug}', [ApartmentController::class, 'show'])->name('apartments.show');
Route::post('/apartments/{id}/availability', [ApartmentController::class, 'checkAvailability'])
    ->middleware('throttle:30,1')
    ->name('apartments.availability');

// Guest Auth Routes — GET pages and POST actions throttled separately so
// a login brute-force doesn't lock out registrations (and vice versa).
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
});

Route::middleware(['guest', 'throttle:10,1'])->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware(['guest', 'throttle:5,1'])->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
});

// Authenticated User Routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/my-bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('/booking/{code}', [BookingController::class, 'show'])->name('bookings.show');
});

// Write actions throttled separately so a flood of bookings (which block
// calendar dates while pending) or payment simulations can't be abused.
// Named limiter (keyed per user, not per route path) is defined in
// AppServiceProvider.
Route::middleware(['auth', 'throttle:bookings'])->group(function () {
    Route::post('/booking', [BookingController::class, 'store'])->name('bookings.store');
    Route::post('/booking/{code}/simulate-payment', [PaymentController::class, 'simulatePayment'])->name('payments.simulate');
});

// Midtrans Webhook (Exempt from CSRF in bootstrap/app.php)
Route::post('/payment/midtrans-notification', [PaymentController::class, 'callback'])->name('payments.callback');
