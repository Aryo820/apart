<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
            ->whereIn('status', [
                BookingStatus::Confirmed->value,
                BookingStatus::Pending->value,
            ])
            ->whereDate('check_in', '<', $checkOut)
            ->whereDate('check_out', '>', $checkIn);
    }

    /**
     * Generate a unique booking code (APT-YYYYMMDD-XXXXX).
     *
     * Shared by the web booking flow and the Filament admin create form
     * so the uniqueness retry logic lives in exactly one place.
     */
    public static function generateBookingCode(): string
    {
        $code = 'APT-'.date('Ymd').'-'.strtoupper(Str::random(5));
        $maxAttempts = 3;
        $attempts = 0;

        while (static::where('booking_code', $code)->exists()) {
            if (++$attempts >= $maxAttempts) {
                throw new \RuntimeException('Gagal membuat kode booking unik. Silakan coba lagi.');
            }

            $code = 'APT-'.date('Ymd').'-'.strtoupper(Str::random(5));
        }

        return $code;
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
