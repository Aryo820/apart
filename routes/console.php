<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    Booking::where('status', BookingStatus::Pending->value)
        ->where('created_at', '<', now()->subHours(24))
        ->update(['status' => BookingStatus::Cancelled->value]);
})->hourly()->description('Cancel pending bookings older than 24 hours');

Schedule::command('inspire')->hourly();
