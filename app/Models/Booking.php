<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_code',
        'user_id',
        'apartment_id',
        'check_in',
        'check_out',
        'total_nights',
        'total_price',
        'status',
        'notes',
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
        'total_price' => 'decimal:2',
        'status' => BookingStatus::class,
    ];

    /**
     * Bookings yang masih memegang tanggal pada kalender sebuah unit.
     *
     * Confirmed selalu memblokir. Pending memblokir HANYA selama batas waktu
     * pembayarannya belum lewat: begitu lewat, tanggalnya bebas kembali pada
     * detik itu juga — tanpa menunggu scheduler berjalan. Itu penting, karena
     * scheduler jalan tiap 5 menit sementara tamu berikutnya bisa datang
     * kapan saja; kalau kebebasan tanggal bergantung pada scheduler, ada
     * jendela di mana tanggal terlihat terkunci padahal seharusnya tidak.
     *
     * Cancelled, Expired, dan Completed tidak memblokir.
     *
     * Satu-satunya sumber kebenaran soal "status apa yang mengunci tanggal":
     * scopeConflicting dan daftar tanggal terpesan di ApartmentController
     * keduanya lewat sini, jadi keduanya tidak bisa lagi berbeda pendapat.
     */
    public function scopeBlocking(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->where('status', BookingStatus::Confirmed->value)
                ->orWhere(function (Builder $query) {
                    $query->where('status', BookingStatus::Pending->value)
                        ->where('created_at', '>', self::paymentExpiryCutoff());
                });
        });
    }

    /**
     * Booking Pending yang dibuat sebelum waktu ini sudah kedaluwarsa.
     * Dihitung dari config supaya command, scope, accessor, dan gateway
     * memakai angka yang sama persis.
     */
    public static function paymentExpiryCutoff(): Carbon
    {
        return now()->subMinutes((int) config('booking.payment_expiry_minutes'));
    }

    /**
     * Bookings that block a given date range for an apartment.
     * Two ranges overlap when existing.check_in < new.check_out
     * AND existing.check_out > new.check_in — so an adjacent stay
     * (check-out on the same day as another's check-in) is allowed.
     *
     * Columns are stored as DATETIME ("2026-08-11 00:00:00"), so string
     * comparison against a bare date ("2026-08-11") is wrong; whereDate
     * compares the DATE part on both sides.
     */
    public function scopeConflicting(Builder $query, int $apartmentId, string $checkIn, string $checkOut): Builder
    {
        return $query->where('apartment_id', $apartmentId)
            ->blocking()
            ->whereDate('check_in', '<', $checkOut)
            ->whereDate('check_out', '>', $checkIn);
    }

    /**
     * Jumlah malam untuk sebuah rentang menginap.
     *
     * Diangkat dari BookingController::store (yang sekarang memanggil ini) agar
     * jalur tamu dan jalur panel tidak bisa lagi menghitung malam dengan cara
     * yang berbeda. max(1, ...) dipertahankan apa adanya: jalur tamu sudah
     * memvalidasi check_out > check_in lewat StoreBookingRequest, jadi ia tidak
     * pernah aktif di sana — dan pemanggil yang tidak punya validasi itu
     * (panel admin) menolak rentang tidak valid lebih dulu, bukan membiarkannya
     * dibulatkan menjadi 1 malam di sini.
     */
    public static function nightsBetween(CarbonInterface $checkIn, CarbonInterface $checkOut): int
    {
        return (int) max(1, $checkIn->diffInDays($checkOut));
    }

    /**
     * Total biaya menginap: tarif unit dikalikan jumlah malam.
     *
     * Satu-satunya rumus harga di project ini. Tidak ada pajak, biaya layanan,
     * diskon, atau tarif musiman — kalau suatu saat ada, tempatnya di sini,
     * bukan di form admin.
     *
     * Nilainya adalah snapshot: begitu tersimpan di bookings.total_price ia
     * tidak mengikuti perubahan harga unit, karena Payment::gross_amount dan
     * nominal yang sudah dikirim ke Midtrans mengacu ke angka itu.
     */
    public static function priceFor(Apartment $apartment, int $nights): float
    {
        return (float) $apartment->price_per_night * $nights;
    }

    /**
     * Booking LAIN yang memblokir rentang ini, dikunci sampai transaksi selesai.
     *
     * Bukan query konflik baru: ia memakai scopeConflicting (yang memakai
     * scopeBlocking) persis seperti BookingController::store dan
     * PaymentStatusApplier, jadi tidak ada aktor yang bisa punya pendapat
     * berbeda soal "tanggal ini masih bebas atau tidak".
     *
     * lockForUpdate hanya berarti di dalam transaksi: pemanggil bertanggung
     * jawab membukanya, kalau tidak lock-nya dilepas seketika dan pemeriksaan
     * ini kembali menjadi race.
     */
    public static function blockingRivalFor(int $apartmentId, string $checkIn, string $checkOut, ?int $ignoreId = null): ?self
    {
        return static::conflicting($apartmentId, $checkIn, $checkOut)
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->lockForUpdate()
            ->first();
    }

    /**
     * Transisi status yang boleh dilakukan secara manual.
     *
     * Isinya BUKAN aturan baru — setiap baris di bawah adalah transisi yang
     * sudah benar-benar terjadi di jalur domain yang ada:
     *
     *   Pending   -> Confirmed  PaymentStatusApplier saat settlement
     *   Pending   -> Cancelled  BookingController::cancel, dan webhook deny/failure/cancel
     *   Pending   -> Expired    webhook expire dan bookings:expire-pending
     *   Cancelled -> Confirmed  settlement setelah percobaan gagal (applier)
     *   Expired   -> Confirmed  settlement terlambat (applier)
     *   Confirmed -> Completed  satu-satunya jalan keluar booking yang sudah menginap;
     *                           tidak ada penulis lain untuk status ini
     *
     * Yang TIDAK ada di daftar itu memang tidak pernah terjadi di domain:
     * booking Confirmed tidak bisa dikembalikan menjadi Pending/Cancelled/
     * Expired (applier hanya menimpa booking yang masih Pending, dan tamu
     * mendapat 403), dan Completed bersifat final.
     *
     * Status yang tidak berubah selalu lolos: menyimpan perubahan catatan pada
     * booking Confirmed bukan sebuah transisi.
     */
    public function canTransitionTo(BookingStatus $target): bool
    {
        if ($this->status === $target) {
            return true;
        }

        return in_array($target, match ($this->status) {
            BookingStatus::Pending => [BookingStatus::Confirmed, BookingStatus::Cancelled, BookingStatus::Expired],
            BookingStatus::Cancelled, BookingStatus::Expired => [BookingStatus::Confirmed],
            BookingStatus::Confirmed => [BookingStatus::Completed],
            BookingStatus::Completed => [],
        }, true);
    }

    /**
     * Batas waktu pembayaran untuk booking ini. Diturunkan dari created_at,
     * bukan disimpan sebagai kolom: created_at tidak pernah berubah, jadi
     * nilainya stabil tanpa menambah kolom yang harus dijaga konsisten.
     */
    public function paymentDeadlineAt(): ?Carbon
    {
        return $this->created_at?->copy()
            ->addMinutes((int) config('booking.payment_expiry_minutes'));
    }

    /** Pending yang batas waktunya sudah lewat, tapi belum ditutup command. */
    public function isPaymentOverdue(): bool
    {
        return $this->status === BookingStatus::Pending
            && $this->created_at !== null
            && $this->created_at->lte(self::paymentExpiryCutoff());
    }

    /**
     * Pembatalan oleh tamu hanya untuk booking yang BELUM dibayar. Begitu ada
     * pembayaran yang settle, pembatalan menimbulkan pertanyaan pengembalian
     * dana — dan aturan refund belum ditetapkan di project ini, jadi jalur itu
     * sengaja tidak dibuka dan diarahkan ke kontak bantuan.
     */
    public function isCancellableByGuest(): bool
    {
        return $this->status === BookingStatus::Pending
            && ! $this->isPaymentOverdue()
            && $this->payment?->status !== PaymentStatus::Settlement;
    }

    /**
     * Generate a unique booking code (APT-YYYYMMDD-XXXXXXXX).
     *
     * Shared by the web booking flow and the Filament admin create form
     * so the uniqueness retry logic lives in exactly one place.
     */
    public static function generateBookingCode(): string
    {
        $code = self::newBookingCode();
        $maxAttempts = 3;
        $attempts = 0;

        while (static::where('booking_code', $code)->exists()) {
            if (++$attempts >= $maxAttempts) {
                throw new \RuntimeException('Gagal membuat kode booking unik. Silakan coba lagi.');
            }

            $code = self::newBookingCode();
        }

        return $code;
    }

    private static function newBookingCode(): string
    {
        return 'APT-'.date('Ymd').'-'.strtoupper(Str::random(8));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }
}
