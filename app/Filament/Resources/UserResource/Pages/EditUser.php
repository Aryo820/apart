<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Actions\GuardedDeleteAction;
use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Guard akun berjalan di dalam transaksi Filament, sebelum $record->
     * update(): penurunan role yang meninggalkan panel tanpa admin ditolak
     * dan transaksinya digulung.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return UserResource::guardAccountIntegrity($data, $this->getRecord(), auth()->user());
    }

    protected function getHeaderActions(): array
    {
        return [
            GuardedDeleteAction::make('Pengguna tidak dapat dihapus karena masih memiliki riwayat reservasi.'),
        ];
    }
}
