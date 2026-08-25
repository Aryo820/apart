<?php

namespace App\Filament\Resources;

use App\Enums\BookingStatus;
use App\Filament\Resources\BookingResource\Pages;
use App\Models\Apartment;
use App\Models\Booking;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

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
                    ->required()
                    // Validasi sisi server, sama seperti aturan 'after:check_in'
                    // pada StoreBookingRequest di jalur tamu. Guard di
                    // guardBookingIntegrity memeriksanya sekali lagi karena
                    // validasi form bukan batas terakhir.
                    ->after('check_in'),

                /*
                 * Jumlah malam dan total biaya TIDAK diisi admin: keduanya
                 * turunan dari tanggal dan tarif unit (Booking::nightsBetween /
                 * Booking::priceFor), dihitung ulang di
                 * guardBookingIntegrity saat disimpan. dehydrated(false)
                 * membuat nilainya tidak pernah ikut terkirim, jadi payload
                 * buatan sendiri pun tidak punya kolom untuk diisi.
                 */
                Forms\Components\TextInput::make('total_nights')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Dihitung dari check-in dan check-out.'),

                Forms\Components\TextInput::make('total_price')
                    ->numeric()
                    ->prefix('Rp')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Dihitung dari tarif unit x jumlah malam.'),

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
            ])
            ->bulkActions([]);
    }

    /**
     * Pemeriksaan yang harus dilewati SETIAP mutasi booking dari panel admin.
     *
     * Sebelumnya panel menulis status, tanggal, dan unit lewat
     * $record->update() mentah — sehingga admin adalah satu-satunya aktor di
     * project ini yang bisa membuat dua booking memblokir tanggal yang sama,
     * tepat pada invariant yang dijaga ketat di jalur tamu (BookingController::
     * store) dan jalur pembayaran (PaymentStatusApplier). Guard ini tidak
     * membawa aturan baru: konfliknya dari Booking::blockingRivalFor (yaitu
     * scopeConflicting + scopeBlocking), transisinya dari
     * Booking::canTransitionTo.
     *
     * Dipanggil dari mutateFormDataBeforeCreate/mutateFormDataBeforeSave, yang
     * keduanya berjalan DI DALAM transaksi Filament — lihat
     * AdminPanelProvider::databaseTransactions(). Itu prasyarat, bukan hiasan:
     * tanpa transaksi, lock yang dipasang di sini dilepas sebelum barisnya
     * ditulis dan pemeriksaannya kembali menjadi race.
     *
     * Selain menolak, guard ini juga MENGISI: total_nights dan total_price
     * dikembalikan sebagai hasil hitungan domain, bukan sebagai angka yang
     * dikirim admin.
     *
     * @param  array<string, mixed>  $data  data form yang sudah lolos validasi
     * @param  Booking|null  $record  booking yang diedit; null saat create
     * @return array<string, mixed> data yang benar-benar disimpan
     *
     * @throws Halt mutasi ditolak dan transaksi di-rollback: DB tidak berubah
     */
    public static function guardBookingIntegrity(array $data, ?Booking $record = null): array
    {
        /*
         * Setiap field dibaca dari form dengan record sebagai cadangan: Filament
         * punya jalur simpan sebagian (saveFormComponentOnly) yang hanya
         * mengirim komponen yang disimpan. Payload sebagian tidak boleh berarti
         * "lewati pemeriksaan", dan juga tidak boleh berarti error 500.
         */
        $status = $data['status'] ?? $record?->status;
        $target = $status instanceof BookingStatus ? $status : BookingStatus::tryFrom((string) $status);

        if (! $target) {
            static::rejectMutation('Status reservasi tidak dikenali.');
        }

        /*
         * Lock baris UNIT dipasang paling awal, sebelum baris booking-nya:
         * urutan kunci global project ini adalah Apartment -> Booking ->
         * rentang rival -> Payment. Semua penilai ketersediaan (jalur tamu di
         * BookingController::store, jalur pembayaran di PaymentStatusApplier,
         * dan guard ini) mengambil lock yang sama lebih dulu, sehingga panel
         * tidak bisa menyelip di antara pemeriksaan dan penulisan dua aktor
         * lain — termasuk saat hasil query konflik kosong dan tidak ada
         * baris booking yang bisa dikunci.
         */
        $targetApartmentId = (int) ($data['apartment_id'] ?? $record?->apartment_id ?? 0);

        if (! Apartment::whereKey($targetApartmentId)->lockForUpdate()->first()) {
            static::rejectMutation('Unit yang dipilih tidak ditemukan.');
        }

        /*
         * Status dibaca ULANG dari DB di bawah lock, bukan dipercaya dari
         * record yang dimuat saat form dibuka: webhook Midtrans atau
         * bookings:expire-pending bisa mengubahnya di antara admin membuka
         * halaman dan menekan Simpan. Pola yang sama dipakai
         * BookingController::cancel.
         */
        $fresh = $record
            ? Booking::with('payment')->whereKey($record->getKey())->lockForUpdate()->first()
            : null;

        if ($record) {
            if (! $fresh) {
                static::rejectMutation('Reservasi ini sudah tidak ada.');
            }

            if (! $fresh->canTransitionTo($target)) {
                static::rejectMutation(
                    "Status {$fresh->status->label()} tidak bisa diubah menjadi {$target->label()}."
                );
            }
        }

        $data = static::applyStayAndPricing($data, $fresh);

        /*
         * Hanya status yang mengunci kalender yang perlu diperiksa. Confirmed
         * selalu memblokir; Pending memblokir selama batas waktu pembayarannya
         * belum lewat — keduanya lewat scopeBlocking, jadi memeriksa Pending
         * juga membuat panel setara dengan BookingController::store, yang
         * menolak booking Pending yang bertabrakan. Cancelled, Expired, dan
         * Completed tidak memblokir apa pun sehingga tidak ada yang bisa
         * ditabrak.
         */
        if (! in_array($target, [BookingStatus::Pending, BookingStatus::Confirmed], true)) {
            return $data;
        }

        $rival = Booking::blockingRivalFor(
            (int) ($data['apartment_id'] ?? $fresh?->apartment_id),
            Carbon::parse($data['check_in'] ?? $fresh?->check_in)->toDateString(),
            Carbon::parse($data['check_out'] ?? $fresh?->check_out)->toDateString(),
            $fresh?->getKey(),
        );

        if ($rival) {
            static::rejectMutation(
                "Tanggal tersebut pada unit ini sudah dipegang reservasi {$rival->booking_code} ({$rival->status->label()})."
            );
        }

        return $data;
    }

    /**
     * Isi lama menginap dan biayanya dari aturan domain, bukan dari input admin.
     *
     * total_nights selalu diturunkan dari rentang tanggal (Booking::
     * nightsBetween). total_price hanya dihitung ulang ketika yang
     * menentukannya berubah — unit atau tanggal — karena nilainya adalah
     * SNAPSHOT tarif saat booking dibuat: menghitungnya ulang pada penyuntingan
     * catatan atau status akan mengubah nilai reservasi lama begitu tarif unit
     * pernah dinaikkan, tanpa ada yang meminta.
     *
     * @param  array<string, mixed>  $data
     * @param  Booking|null  $fresh  baris booking yang sedang dikunci; null saat create
     * @return array<string, mixed>
     */
    private static function applyStayAndPricing(array $data, ?Booking $fresh): array
    {
        $apartmentId = (int) ($data['apartment_id'] ?? $fresh?->apartment_id);
        $checkIn = Carbon::parse($data['check_in'] ?? $fresh?->check_in)->startOfDay();
        $checkOut = Carbon::parse($data['check_out'] ?? $fresh?->check_out)->startOfDay();

        // Aturan yang sama dengan 'after:check_in' pada StoreBookingRequest:
        // menginap nol malam bukan reservasi. Form sudah memvalidasinya; ini
        // lapisan kedua untuk payload yang tidak lewat form.
        if ($checkOut->lessThanOrEqualTo($checkIn)) {
            static::rejectMutation('Check-out harus setelah check-in, minimal satu malam.');
        }

        $data['total_nights'] = Booking::nightsBetween($checkIn, $checkOut);

        $repricing = $fresh === null
            || $apartmentId !== $fresh->apartment_id
            || ! $checkIn->isSameDay($fresh->check_in)
            || ! $checkOut->isSameDay($fresh->check_out);

        if (! $repricing) {
            return $data;
        }

        $apartment = Apartment::find($apartmentId);

        if (! $apartment) {
            static::rejectMutation('Unit yang dipilih tidak ditemukan.');
        }

        $data['total_price'] = Booking::priceFor($apartment, $data['total_nights']);

        /*
         * Nominal yang sudah menjadi sesi pembayaran tidak bisa dikejar dari
         * sini. Payment::gross_amount dibuat sama dengan total_price
         * (BookingController::store) dan itu pula angka yang dikirim ke Midtrans
         * serta yang dipakai PaymentStatusApplier untuk menolak notifikasi yang
         * nominalnya tidak cocok. Membiarkan keduanya berbeda berarti tamu
         * membayar satu angka sementara reservasinya menyebut angka lain.
         *
         * Perbandingannya memakai idiom yang sama dengan applier: cast ke float
         * di kedua sisi. Yang ditawarkan di sini hanya penolakan — menagih
         * selisih atau mengembalikan dana adalah alur pembayaran yang belum ada
         * di project ini, dan tidak dikarang di sini.
         */
        $gross = $fresh?->payment ? (float) $fresh->payment->gross_amount : null;

        if ($gross !== null && $gross !== (float) $data['total_price']) {
            static::rejectMutation(sprintf(
                'Perubahan ini membuat nilai reservasi menjadi Rp %s, sementara sesi pembayarannya tercatat Rp %s. Nominal yang sudah dikirim ke Midtrans tidak bisa diubah dari panel.',
                number_format((float) $data['total_price'], 0, ',', '.'),
                number_format($gross, 0, ',', '.'),
            ));
        }

        return $data;
    }

    /**
     * Hentikan penyimpanan dengan pesan yang bisa ditindaklanjuti admin.
     *
     * Halt adalah cara Filament membatalkan proses save-nya sendiri;
     * rollBackDatabaseTransaction() memastikan tidak ada satu kolom pun yang
     * tertinggal tersimpan.
     */
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
            'create' => Pages\CreateBooking::route('/create'),
            'edit' => Pages\EditBooking::route('/{record}/edit'),
        ];
    }
}
