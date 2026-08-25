<?php

namespace App\Filament\Resources\ApartmentResource\Pages;

use App\Filament\Actions\GuardedDeleteAction;
use App\Filament\Resources\ApartmentResource;
use Filament\Resources\Pages\EditRecord;

class EditApartment extends EditRecord
{
    protected static string $resource = ApartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            GuardedDeleteAction::make('Apartemen tidak dapat dihapus karena masih memiliki riwayat reservasi.'),
        ];
    }
}
