<?php

namespace App\Filament\Resources;

use App\Enums\BookingStatus;
use App\Filament\Resources\BookingResource\Pages;
use App\Models\Booking;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('booking_code')
                    ->disabled()
                    ->dehydrated(false)
                    ->hiddenOn('create'),

                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->required(),

                Forms\Components\Select::make('apartment_id')
                    ->relationship('apartment', 'title')
                    ->searchable()
                    ->required(),

                Forms\Components\DatePicker::make('check_in')
                    ->required(),

                Forms\Components\DatePicker::make('check_out')
                    ->required(),

                Forms\Components\TextInput::make('total_nights')
                    ->numeric()
                    ->required(),

                Forms\Components\TextInput::make('total_price')
                    ->numeric()
                    ->prefix('Rp')
                    ->required(),

                Forms\Components\Select::make('status')
                    ->options(fn () => BookingStatus::options())
                    ->required(),

                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('booking_code')
                    ->searchable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->searchable()
                    ->label('Customer'),

                Tables\Columns\TextColumn::make('apartment.title')
                    ->searchable()
                    ->label('Apartment')
                    ->limit(25),

                Tables\Columns\TextColumn::make('check_in')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('check_out')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_price')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): ?string => match (true) {
                        $state instanceof BookingStatus => $state->value,
                        is_string($state) => $state,
                        default => null,
                    })
                    ->color(fn (mixed $state): string => match ($state) {
                        BookingStatus::Confirmed, BookingStatus::Confirmed->value => 'success',
                        BookingStatus::Completed, BookingStatus::Completed->value => 'info',
                        BookingStatus::Pending, BookingStatus::Pending->value => 'warning',
                        BookingStatus::Cancelled, BookingStatus::Cancelled->value => 'danger',
                        default => 'secondary',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(fn () => BookingStatus::options()),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
            'create' => Pages\CreateBooking::route('/create'),
            'edit' => Pages\EditBooking::route('/{record}/edit'),
        ];
    }
}
