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
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
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

        /*
         * Halaman reservasi menerima kode booking bebas, dan otorisasi barunya
         * berjalan SETELAH pencarian. Tanpa limit, pengguna terautentikasi bisa
         * mengenumerasi kode dengan laju penuh tanpa jejak. Bucket dipisah dari
         * limiter tulis 'bookings' supaya membuka halaman sendiri tidak pernah
         * menggerus jatah membuat/membatalkan reservasi.
         */
        RateLimiter::for('booking-view', function ($request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Panel resources are admin-only at the Gate level (defense-in-depth
        // on top of FilamentUser::canAccessPanel).
        Gate::policy(Apartment::class, ApartmentPolicy::class);
        Gate::policy(Booking::class, BookingPolicy::class);
        Gate::policy(Facility::class, FacilityPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        /*
         * Scheduler yang gagal tidak boleh hilang diam-diam: tanpa ini, satu-
         * satunya jejak kegagalan bookings:expire-pending adalah exit code
         * schedule:run yang tidak dilihat siapa pun. Listener ini menulis
         * sinyal ERROR terstruktur ke log aplikasi — kanal yang sudah
         * dibawa oleh deployment apa pun (file/journald/stdout) dan bisa
         * di-forward ke error tracker eksternal tanpa asumsi provider.
         */
        Event::listen(ScheduledTaskFailed::class, function (ScheduledTaskFailed $event): void {
            Log::error('scheduler_task_failed', [
                'task' => $event->task->command ?? $event->task->name ?? 'unknown',
                'description' => $event->task->description,
                'exception' => $event->exception::class,
                'message' => $event->exception->getMessage(),
            ]);
        });

        // Bagikan daftar kota populer (dari DB) ke layout utama agar footer
        // tidak lagi meng-hardcode nama kota. Dibungkus rescue() karena layout
        // ini juga dipakai halaman error: kalau DB-nya yang tumbang, halaman
        // 500/503 tidak boleh ikut gagal render.
        View::composer('layouts.app', function ($view) {
            $view->with('popularCities', rescue(fn () => Apartment::query()
                ->where('status', ApartmentStatus::Available)
                ->select('city')
                ->groupBy('city')
                ->orderByRaw('COUNT(*) DESC')
                ->limit(4)
                ->pluck('city'), collect(), report: false));
        });
    }
}
