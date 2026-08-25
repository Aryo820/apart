<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    /**
     * Batas waktu pembayaran lewat tanpa pembayaran masuk. Dipisahkan dari
     * Cancelled supaya "tamu membatalkan" dan "tamu tidak pernah membayar"
     * tidak lagi terlihat sama — keduanya butuh copy dan penanganan berbeda.
     */
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
        };
    }

    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
