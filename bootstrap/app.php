<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Aplikasi berjalan di belakang reverse proxy/LB/CDN, jadi REMOTE_ADDR
         * adalah IP proxy — bukan tamu. Tanpa ini semua bucket throttle
         * berbasis IP (login 10/menit, register 5/menit, availability 30/menit)
         * berbagi satu identitas dan bisa dijenuhkan satu aktor untuk
         * mengunci autentikasi semua pengguna. Setel TRUSTED_PROXIES ke CIDR
         * proxy yang sebenarnya di produksi; '*' hanya untuk proxy yang selalu
         * menimpa X-Forwarded-For dengan nilai yang dia hitung sendiri.
         */
        $proxies = trim((string) env('TRUSTED_PROXIES', '*'));

        $middleware->trustProxies(at: match ($proxies) {
            '' => [],
            '*' => '*',
            default => array_values(array_filter(array_map('trim', explode(',', $proxies)))),
        });

        $middleware->validateCsrfTokens(except: [
            'payment/midtrans-notification',
        ]);

        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
