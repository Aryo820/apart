<?php

namespace App\Filament\Resources;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Payment adalah CATATAN dari Midtrans, bukan data yang di-maintain admin.
 *
 * Resource ini sengaja read-only terhadap data gateway: satu-satunya penulis
 * status payment adalah PaymentStatusApplier, yang dipanggil webhook dan
 * rekonsiliasi setelah nilainya dibuktikan berasal dari gateway (signature
 * SHA-512 / permintaan server-ke-server). Nominalnya juga bukan angka bebas:
 * ia adalah pembanding yang dipakai applier untuk menolak notifikasi yang
 * nominalnya tidak cocok. Karena itu tidak ada form create/edit, dan batas
 * sesungguhnya ada di PaymentPolicy (create/update/delete ditolak semua).
 *
 * Satu-satunya pengecualian: action "Tandai Selesai" untuk insiden finansial
 * "settled-but-unavailable" — dan pun tidak menyentuh data gateway. Ia hanya
 * mencatat attention_resolved_at + catatan operator (kolom operasional
 * Batch 3.1), karena refund manual terjadi di dashboard Midtrans, bukan di
 * sini. Status payment tetap merepresentasikan fakta gateway.
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

                /*
                 * Kolom "Tindakan" memakai predicate needsAttention, bukan
                 * flag manual: nilai ini selalu merupakan kebenaran terkini
                 * dari kombinasi payment+booking+resolusi. Insiden = uang
                 * masuk (Settlement) tapi booking tidak memegang tanggal
                 * (Pending/Cancelled/Expired) DAN belum ditandai selesai.
                 */
                Tables\Columns\TextColumn::make('needs_attention')
                    ->label('Tindakan')
                    ->badge()
                    ->state(function (Payment $record): string {
                        if ($record->isNeedingAttention()) {
                            return 'Perlu Tindakan';
                        }

                        return $record->attention_resolved_at !== null
                            ? 'Selesai Ditindak'
                            : 'Normal';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Perlu Tindakan' => 'danger',
                        'Selesai Ditindak' => 'success',
                        default => 'gray',
                    })
                    ->tooltip(function (Payment $record): ?string {
                        if ($record->isNeedingAttention()) {
                            return 'Pembayaran settled tapi tanggal dipegang booking lain — refund manual diperlukan.';
                        }

                        return $record->attention_resolved_at !== null
                            ? 'Follow-up manual selesai: '.$record->attention_resolved_at->timezone('Asia/Jakarta')->format('d M Y, H:i').' WIB'
                            : null;
                    }),

                Tables\Columns\TextColumn::make('attention_resolved_at')
                    ->label('Ditindak Pada')
                    ->dateTime('d M Y, H:i')
                    ->timezone('Asia/Jakarta')
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('attention_resolution_note')
                    ->label('Catatan Tindakan')
                    ->limit(80)
                    ->tooltip(fn (Payment $record): ?string => $record->attention_resolution_note)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

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

                /*
                 * Filter insiden: hanya memunculkan payment yang membutuhkan
                 * tindakan manual — query yang sama dengan kolom Tindakan dan
                 * stat dashboard, jadi ketiganya tidak bisa berbeda pendapat.
                 */
                Tables\Filters\TernaryFilter::make('needs_attention')
                    ->label('Perlu Tindakan')
                    ->queries(
                        true: fn ($query) => $query->needsAttention(),
                        false: fn ($query) => $query->where(function ($q) {
                            $q->where('status', '!=', PaymentStatus::Settlement->value)
                                ->orWhereHas('booking', fn ($booking) => $booking->whereNotIn('status', [
                                    BookingStatus::Pending->value,
                                    BookingStatus::Cancelled->value,
                                    BookingStatus::Expired->value,
                                ]))
                                ->orWhereNotNull('attention_resolved_at');
                        }),
                    ),
            ])
            ->actions([
                self::markIncidentResolvedAction(),
            ]);
    }

    /**
     * Action "Tandai Selesai": hanya untuk insiden finansial yang belum
     * ditindak. Mencatat resolusi operasional (timestamp + catatan) —
     * TIDAK menyentuh Payment.status/Booking.status dan tidak memicu
     * refund otomatis; refund manual terjadi di dashboard Midtrans.
     *
     * Authorization ganda: Gate ability resolveAttention (PaymentPolicy,
     * admin-only) + visible() yang tidak menampilkan action kecuali
     * payment benar-benar sedang membutuhkan tindakan.
     */
    private static function markIncidentResolvedAction(): Actions\Action
    {
        return Actions\Action::make('markAttentionResolved')
            ->label('Tandai Selesai')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->authorize('resolveAttention')
            ->visible(fn (Payment $record): bool => $record->isNeedingAttention())
            ->schema([
                Forms\Components\Textarea::make('note')
                    ->label('Catatan tindakan')
                    ->required()
                    ->minLength(3)
                    ->maxLength(500)
                    ->rows(3)
                    ->helperText('Contoh: "Refund manual Midtrans selesai. Reference: ..."')
                    ->validationMessages([
                        'required' => 'Catatan tindakan wajib diisi — kasus ini menyangkut uang.',
                        'max' => 'Catatan maksimal 500 karakter.',
                    ]),
            ])
            ->requiresConfirmation()
            ->modalHeading('Tandai insiden selesai?')
            ->modalDescription(fn (Payment $record): string => 'Menandai bahwa follow-up manual (mis. refund di Midtrans) sudah dilakukan. Status payment gateway dan status booking TIDAK berubah.')
            ->modalSubmitActionLabel('Tandai Selesai')
            ->action(function (Payment $record, array $data): void {
                $resolved = $record->markAttentionResolved($data['note']);

                if (! $resolved) {
                    Notification::make()
                        ->title('Insiden sudah ditandai selesai sebelumnya')
                        ->info()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Insiden ditandai selesai')
                    ->success()
                    ->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
        ];
    }
}
