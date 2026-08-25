<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Actions\GuardedDeleteAction;
use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            GuardedDeleteAction::make('Pengguna tidak dapat dihapus karena masih memiliki riwayat reservasi.'),
        ];
    }
}
