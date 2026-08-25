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

// Halaman legal: isinya statis, jadi Route::view sudah cukup — tidak ada
// controller yang perlu dibuat hanya untuk me-render dua Blade.
Route::view('/syarat-ketentuan', 'legal.terms')->name('legal.terms');
Route::view('/kebijakan-privasi', 'legal.privacy')->name('legal.privacy');

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

    // Limiter terpisah dari 'bookings': lookup kode harus tetap mahal bagi
    // yang mengenumerasi, tapi murah bagi pemiliknya yang sekadar memuat
    // ulang halaman pembayarannya.
    Route::get('/booking/{code}', [BookingController::class, 'show'])
        ->middleware('throttle:booking-view')
        ->name('bookings.show');
});

// Booking writes are throttled because pending reservations block calendar
// dates. The named limiter is keyed per user in AppServiceProvider.
Route::post('/booking', [BookingController::class, 'store'])
    ->middleware(['auth', 'throttle:bookings'])
    ->name('bookings.store');

// DELETE, bukan GET: ini mutasi status. Ikut limiter 'bookings' karena
// membatalkan juga melepas tanggal ke kalender.
Route::delete('/booking/{code}/cancel', [BookingController::class, 'cancel'])
    ->middleware(['auth', 'throttle:bookings'])
    ->name('bookings.cancel');

// Midtrans Webhook (Exempt from CSRF in bootstrap/app.php)
Route::post('/payment/midtrans-notification', [PaymentController::class, 'callback'])->name('payments.callback');

// Rekonsiliasi status pembayaran saat tamu kembali dari Snap. POST karena
// menulis status; nilainya sendiri diambil server-ke-server dari Midtrans, bukan
// dari browser. Ikut limiter 'bookings' supaya tombolnya tidak bisa dipakai
// memanggil API gateway berulang-ulang.
Route::post('/booking/{code}/reconcile', [PaymentController::class, 'reconcile'])
    ->middleware(['auth', 'throttle:bookings'])
    ->name('bookings.reconcile');
