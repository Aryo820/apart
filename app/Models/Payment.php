<?php

namespace App\Models;

use App\Enums\PaymentStatus;
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
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'raw_response' => 'array',
        'status' => PaymentStatus::class,
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

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
