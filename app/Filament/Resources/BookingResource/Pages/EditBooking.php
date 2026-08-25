<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use Filament\Resources\Pages\EditRecord;

class EditBooking extends EditRecord
{
    protected static string $resource = BookingResource::class;

    /**
     * Titik paling kritis di panel: di sini status, tanggal, dan unit sebuah
     * booking yang sudah ada bisa berubah. Hook ini berjalan di dalam transaksi
     * Filament dan sebelum $record->update(), jadi lock yang dipasang guard
     * masih dipegang ketika barisnya ditulis.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return BookingResource::guardBookingIntegrity($data, $this->getRecord());
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
