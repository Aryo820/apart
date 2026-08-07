<?php

namespace App\Filament\Resources;

use App\Enums\ApartmentStatus;
use App\Filament\Resources\ApartmentResource\Pages;
use App\Models\Apartment;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ApartmentResource extends Resource
{
    protected static ?string $model = Apartment::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|\UnitEnum|null $navigationGroup = 'Management';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Apartment Details')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(
                                fn (string $operation, $state, Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null
                            ),

                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->unique(Apartment::class, 'slug', ignoreRecord: true)
                            ->maxLength(255),

                        Forms\Components\RichEditor::make('description')
                            ->required()
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Pricing & Location')
                    ->schema([
                        Forms\Components\TextInput::make('price_per_night')
                            ->required()
                            ->numeric()
                            ->prefix('Rp')
                            ->label('Price per Night'),

                        Forms\Components\TextInput::make('city')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('address')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Specifications')
                    ->schema([
                        Forms\Components\TextInput::make('bedrooms')
                            ->required()
                            ->numeric()
                            ->default(1),

                        Forms\Components\TextInput::make('bathrooms')
                            ->required()
                            ->numeric()
                            ->default(1),

                        Forms\Components\TextInput::make('area_sqm')
                            ->required()
                            ->numeric()
                            ->label('Area (m²)')
                            ->default(35),

                        Forms\Components\TextInput::make('capacity')
                            ->required()
                            ->numeric()
                            ->label('Max Guests')
                            ->default(2),
                    ])->columns(4),

                Section::make('Facilities & Status')
                    ->schema([
                        Forms\Components\Select::make('facilities')
                            ->relationship('facilities', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable(),

                        Forms\Components\Select::make('status')
                            ->options(fn () => ApartmentStatus::options())
                            ->default(ApartmentStatus::Available->value)
                            ->required(),

                        Forms\Components\Toggle::make('is_featured')
                            ->label('Featured Apartment')
                            ->default(false),
                    ])->columns(3),

                Section::make('Media Gallery')
                    ->schema([
                        Forms\Components\FileUpload::make('main_image')
                            ->image()
                            ->directory('apartments/main')
                            ->required(),

                        Forms\Components\FileUpload::make('images')
                            ->image()
                            ->multiple()
                            ->directory('apartments/gallery')
                            ->panelLayout('grid'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('main_image')
                    ->square(),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('city')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('price_per_night')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('bedrooms')
                    ->label('Beds')
                    ->badge(),

                Tables\Columns\TextColumn::make('capacity')
                    ->label('Guests')
                    ->badge(),

                Tables\Columns\IconColumn::make('is_featured')
                    ->boolean()
                    ->label('Featured'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): ?string => match (true) {
                        $state instanceof ApartmentStatus => $state->value,
                        is_string($state) => $state,
                        default => null,
                    })
                    ->color(fn (mixed $state): string => match ($state) {
                        ApartmentStatus::Available, ApartmentStatus::Available->value => 'success',
                        ApartmentStatus::Maintenance, ApartmentStatus::Maintenance->value => 'danger',
                        default => 'secondary',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('city')
                    ->options(fn () => Apartment::pluck('city', 'city')->toArray()),
                Tables\Filters\SelectFilter::make('status')
                    ->options(fn () => ApartmentStatus::options()),
                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured Only'),
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
            'index' => Pages\ListApartments::route('/'),
            'create' => Pages\CreateApartment::route('/create'),
            'edit' => Pages\EditApartment::route('/{record}/edit'),
        ];
    }
}
