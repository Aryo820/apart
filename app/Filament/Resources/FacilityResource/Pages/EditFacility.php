<?php

namespace App\Filament\Resources\FacilityResource\Pages;

use App\Filament\Actions\GuardedDeleteAction;
use App\Filament\Resources\FacilityResource;
use Filament\Resources\Pages\EditRecord;

class EditFacility extends EditRecord
{
    protected static string $resource = FacilityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            GuardedDeleteAction::make('Fasilitas tidak dapat dihapus karena masih digunakan oleh apartemen.'),
        ];
    }
}
