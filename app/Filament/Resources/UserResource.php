<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Actions\GuardedDeleteAction;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Management';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(User::class, 'email', ignoreRecord: true),

                Forms\Components\TextInput::make('phone')
                    ->tel()
                    ->maxLength(255),

                Forms\Components\Select::make('role')
                    ->options(fn () => UserRole::options())
                    ->required()
                    ->default(UserRole::User->value),

                Forms\Components\TextInput::make('password')
                    ->password()
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('email')
                    ->searchable(),

                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): ?string => match (true) {
                        $state instanceof UserRole => $state->value,
                        is_string($state) => $state,
                        default => null,
                    })
                    ->color(fn (mixed $state): string => match ($state) {
                        UserRole::Admin, UserRole::Admin->value => 'danger',
                        UserRole::User, UserRole::User->value => 'success',
                        default => 'secondary',
                    }),

                Tables\Columns\TextColumn::make('bookings_count')
                    ->counts('bookings')
                    ->label('Bookings'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options(fn () => UserRole::options()),
            ])
            ->actions([
                Actions\EditAction::make(),
                GuardedDeleteAction::make('Pengguna tidak dapat dihapus karena masih memiliki riwayat reservasi.'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    /**
     * Pemeriksaan yang harus dilewati SETIAP mutasi role akun dari panel.
     *
     * Menurunkan role akun sendiri adalah satu-satunya jalur nyata menuju
     * panel tanpa admin: setiap aktor lain yang sampai ke titik ini pasti
     * admin, jadi menurunkan admin kedua selalu menyisakan satu admin.
     *
     * Statik dan murni seperti guardBookingIntegrity: bisa dipanggil dari hook
     * Filament DAN langsung dari test tanpa memuat halaman Livewire.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws Halt mutasi ditolak dan transaksi di-rollback
     */
    public static function guardAccountIntegrity(array $data, User $record, ?User $actor): array
    {
        if ($actor === null || ! $record->is($actor) || ! array_key_exists('role', $data)) {
            return $data;
        }

        $targetRole = UserRole::tryFrom((string) $data['role']);

        if (! $targetRole || $targetRole === $record->role) {
            return $data;
        }

        static::rejectMutation('Anda tidak dapat mengubah role akun Anda sendiri.');

        return $data;
    }

    private static function rejectMutation(string $message): never
    {
        Notification::make()
            ->danger()
            ->title('Perubahan ditolak')
            ->body($message)
            ->persistent()
            ->send();

        throw (new Halt)->rollBackDatabaseTransaction();
    }
}
