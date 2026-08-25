<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use App\Models\Booking;
use Filament\Resources\Pages\CreateRecord;

class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;

    /**
     * Hook ini berjalan di dalam transaksi Filament, sebelum baris booking
     * ditulis — jadi guard-nya memakai lock yang masih dipegang saat INSERT
     * terjadi. Booking baru tidak punya status sebelumnya, jadi yang berlaku
     * hanya pemeriksaan konflik; total_nights dan total_price diisi guard dari
     * aturan domain, bukan dari form.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = BookingResource::guardBookingIntegrity($data);

        $data['booking_code'] = Booking::generateBookingCode();

        return $data;
    }
}
