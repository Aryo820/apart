<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'order_id',
        'transaction_id',
        'snap_token',
        'payment_type',
        'gross_amount',
        'status',
        'raw_response',
        'attention_resolved_at',
        'attention_resolution_note',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'raw_response' => 'array',
        'status' => PaymentStatus::class,
        'attention_resolved_at' => 'datetime',
    ];

    /**
     * order_id yang dikirim ke Midtrans. Suffix timestamp menjaga keunikannya
     * ketika token diterbitkan ulang untuk booking yang sama — Midtrans menolak
     * order_id yang sudah pernah dipakai transaksi yang belum gagal.
     *
     * PaymentController membaca booking_code kembali dengan Str::beforeLast,
     * jadi formatnya tidak boleh berubah tanpa mengubah parsing di sana.
     */
    public static function generateOrderId(string $bookingCode): string
    {
        return $bookingCode.'-'.time();
    }

    /**
     * Insiden finansial "settlement tanpa inventory": uang sudah masuk
     * (payment Settlement) tapi booking TIDAK memegang tanggalnya.
     *
     * Predicate ini deterministik dari persisted state yang sudah ada:
     * PaymentStatusApplier adalah satu-satunya penulis status Settlement,
     * dan ia selalu mencoba mengonfirmasi booking. Satu-satunya jalan
     * kombinasi "Settlement + booking Pending/Cancelled/Expired" adalah
     * branch settled_but_unavailable (tanggal dipegang rival, uang tetap
     * dicatat untuk refund manual). Settlement + Confirmed adalah alur
     * normal; Settlement + Completed adalah tamu yang sudah selesai
     * menginap — bukan insiden.
     *
     * Ini predicate DASAR — mencakup insiden yang follow-up-nya sudah
     * selesai (resolved) maupun belum, sehingga bukti historis tidak pernah
     * hilang. Untuk daftar kerja admin, pakai scopeNeedsAttention.
     */
    public function scopeFinancialIncident(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::Settlement->value)
            ->whereHas('booking', fn (Builder $booking) => $booking->whereIn(
                'status',
                collect([
                    BookingStatus::Pending,
                    BookingStatus::Cancelled,
                    BookingStatus::Expired,
                ])->map(fn (BookingStatus $status) => $status->value)->all(),
            ));
    }

    /**
     * Insiden finansial yang follow-up-nya BELUM selesai: daftar kerja
     * admin ("Perlu Tindakan"). Setelah operator menandai selesai
     * (markAttentionResolved), payment keluar dari sini — tapi tetap
     * teridentifikasi lewat scopeFinancialIncident sebagai evidence.
     */
    public function scopeNeedsAttention(Builder $query): Builder
    {
        return $query->financialIncident()
            ->whereNull('attention_resolved_at');
    }

    /** Payment yang membutuhkan tindak lanjut manual (lihat scopeNeedsAttention). */
    public function isNeedingAttention(): bool
    {
        return $this->status === PaymentStatus::Settlement
            && $this->booking !== null
            && in_array($this->booking->status, [
                BookingStatus::Pending,
                BookingStatus::Cancelled,
                BookingStatus::Expired,
            ], true)
            && $this->attention_resolved_at === null;
    }

    /**
     * Menandai follow-up eksternal/manual selesai — HANYA mencatat waktu
     * dan catatan operator. Tidak menyentuh Payment.status (catatan
     * gateway), tidak menyentuh Booking.status (fakta reservasi), dan
     * tidak memicu refund otomatis apa pun.
     *
     * Idempotent untuk kondisi yang sudah selesai: memanggilnya dua kali
     * tidak menggeser timestamp resolusi pertama.
     */
    public function markAttentionResolved(string $note): bool
    {
        if (! $this->isNeedingAttention()) {
            return false;
        }

        return (bool) $this->forceFill([
            'attention_resolved_at' => $this->attention_resolved_at ?? now(),
            'attention_resolution_note' => $note,
        ])->save();
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
