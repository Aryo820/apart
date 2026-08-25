<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            /*
             * Setiap create/save halaman resource dibungkus satu transaksi.
             *
             * Ini prasyarat guard integritas booking (BookingResource::
             * guardBookingIntegrity): pemeriksaan konflik di sana memasang
             * lockForUpdate, dan lock hanya bertahan sampai akhir transaksi.
             * Tanpa baris ini setiap penyimpanan berjalan autocommit — lock
             * dilepas sebelum baris booking ditulis, dan booking lain masih bisa
             * menyelip di antara pemeriksaan dan penulisan.
             *
             * Efek sampingnya diinginkan: penyimpanan yang gagal di tengah jalan
             * tidak lagi meninggalkan sebagian kolom tersimpan.
             */
            ->databaseTransactions()
            /*
             * Ability yang tidak punya method di policy TIDAK boleh diam-diam
             * menjadi ALLOW.
             *
             * Di luar strict mode, Filament::get_authorization_response()
             * hanya bertanya ke Gate bila method-nya ada di policy; kalau tidak
             * ada, ia jatuh ke Response::allow(). Itu kebalikan dari Laravel
             * Gate biasa (yang menolak) — dan artinya setiap ability yang belum
             * pernah diputuskan siapa pun (deleteAny dan kawan-kawan) terbuka.
             *
             * Dengan strict mode, ability yang tidak terdefinisi melempar
             * LogicException alih-alih mengizinkan: gagal keras di halaman
             * admin, bukan gagal diam-diam pada data. Setiap ability yang
             * memang dipakai panel ini didefinisikan eksplisit di AdminPolicy
             * dan PaymentPolicy.
             */
            ->strictAuthorization()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
