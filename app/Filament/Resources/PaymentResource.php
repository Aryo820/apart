<?php

namespace App\Filament\Resources;

use App\Enums\PaymentStatus;
use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Payment adalah CATATAN dari Midtrans, bukan data yang di-maintain admin.
 *
 * Resource ini sengaja read-only. Satu-satunya penulis status payment adalah
 * PaymentStatusApplier, yang dipanggil webhook dan rekonsiliasi setelah
 * nilainya dibuktikan berasal dari gateway (signature SHA-512 / permintaan
 * server-ke-server). Nominalnya juga bukan angka bebas: ia adalah pembanding
 * yang dipakai applier untuk menolak notifikasi yang nominalnya tidak cocok.
 *
 * Karena itu tidak ada form, tidak ada create/edit page, dan tidak ada action
 * yang menulis. Batas sesungguhnya bukan di sini melainkan di PaymentPolicy,
 * yang menolak create/update/delete untuk semua orang — UI yang tidak
 * menampilkan tombol bukan authorization.
 */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('booking.booking_code')
                    ->searchable()
                    ->weight('bold')
                    ->label('Booking Code'),

                Tables\Columns\TextColumn::make('booking.user.name')
                    ->searchable()
                    ->label('Customer'),

                Tables\Columns\TextColumn::make('transaction_id')
                    ->searchable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('payment_type')
                    ->badge()
                    ->color('info')
                    ->placeholder('Snap'),

                Tables\Columns\TextColumn::make('gross_amount')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): ?string => match (true) {
                        $state instanceof PaymentStatus => $state->value,
                        is_string($state) => $state,
                        default => null,
                    })
                    ->color(fn (mixed $state): string => match ($state) {
                        PaymentStatus::Settlement, PaymentStatus::Settlement->value => 'success',
                        PaymentStatus::Pending, PaymentStatus::Pending->value => 'warning',
                        PaymentStatus::Expire, PaymentStatus::Cancel, PaymentStatus::Failed,
                        PaymentStatus::Expire->value, PaymentStatus::Cancel->value, PaymentStatus::Failed->value => 'danger',
                        default => 'secondary',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(fn () => PaymentStatus::options()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
        ];
    }
}
