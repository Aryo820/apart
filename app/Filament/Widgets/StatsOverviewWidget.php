<?php

namespace App\Filament\Widgets;

use App\Enums\ApartmentStatus;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Apartment;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalRevenue = Payment::where('status', PaymentStatus::Settlement->value)->sum('gross_amount');
        $activeBookings = Booking::whereIn('status', [
            BookingStatus::Confirmed->value,
            BookingStatus::Pending->value,
        ])->count();
        $totalApartments = Apartment::where('status', ApartmentStatus::Available)->count();
        $totalUsers = User::where('role', UserRole::User)->count();

        return [
            Stat::make('Total Revenue', 'Rp '.number_format($totalRevenue, 0, ',', '.'))
                ->description('Settled payments')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Active Bookings', $activeBookings)
                ->description('Pending & Confirmed')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary'),

            Stat::make('Available Apartments', $totalApartments)
                ->description('Ready for rent')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('info'),

            Stat::make('Total Customers', $totalUsers)
                ->description('Registered users')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('warning'),
        ];
    }
}
