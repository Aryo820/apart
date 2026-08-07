<?php

namespace App\Filament\Resources;

use App\Enums\PaymentStatus;
use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('booking_id')
                    ->relationship('booking', 'booking_code')
                    ->required(),

                Forms\Components\TextInput::make('transaction_id'),

                Forms\Components\TextInput::make('payment_type'),

                Forms\Components\TextInput::make('gross_amount')
                    ->numeric()
                    ->prefix('Rp')
                    ->required(),

                Forms\Components\Select::make('status')
                    ->options(fn () => PaymentStatus::options())
                    ->required(),
            ]);
    }

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
            ])
            ->actions([
                Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
        ];
    }
}
