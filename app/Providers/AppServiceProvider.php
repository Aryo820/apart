<?php

namespace App\Providers;

use App\Enums\ApartmentStatus;
use App\Models\Apartment;
use App\Models\Booking;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\User;
use App\Policies\ApartmentPolicy;
use App\Policies\BookingPolicy;
use App\Policies\FacilityPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Shared bucket for booking write actions, keyed per user so the
        // limit can't be bypassed by using different route paths.
        RateLimiter::for('bookings', function ($request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // Panel resources are admin-only at the Gate level (defense-in-depth
        // on top of FilamentUser::canAccessPanel).
        Gate::policy(Apartment::class, ApartmentPolicy::class);
        Gate::policy(Booking::class, BookingPolicy::class);
        Gate::policy(Facility::class, FacilityPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        // Bagikan daftar kota populer (dari DB) ke layout utama agar footer
        // tidak lagi meng-hardcode nama kota.
        View::composer('layouts.app', function ($view) {
            $view->with('popularCities', Apartment::query()
                ->where('status', ApartmentStatus::Available)
                ->select('city')
                ->groupBy('city')
                ->orderByRaw('COUNT(*) DESC')
                ->limit(4)
                ->pluck('city'));
        });
    }
}
