<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Str;
use Midtrans\Config;
use Midtrans\Snap;

class MidtransService
{
    public function __construct()
    {
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized = config('midtrans.is_sanitized');
        Config::$is3ds = config('midtrans.is_3ds');
    }

    public function getSnapToken(Booking $booking): string
    {
        $params = [
            'transaction_details' => [
                'order_id' => $booking->booking_code.'-'.time(),
                'gross_amount' => (int) $booking->total_price,
            ],
            'customer_details' => [
                'first_name' => $booking->user->name,
                'email' => $booking->user->email,
                'phone' => $booking->user->phone ?? '081234567890',
            ],
            'item_details' => [
                [
                    'id' => 'APT-'.$booking->apartment->id,
                    'price' => (int) ($booking->total_price / $booking->total_nights),
                    'quantity' => $booking->total_nights,
                    'name' => mb_substr($booking->apartment->title, 0, 45),
                ],
            ],
            'callbacks' => [
                'finish' => route('bookings.show', $booking->booking_code),
            ],
        ];

        try {
            return Snap::getSnapToken($params);
        } catch (\Exception $e) {
            if (Str::contains(config('midtrans.server_key'), 'Demo')) {
                return 'SNAP-DEMO-TOKEN-'.strtoupper(Str::random(12));
            }
            throw $e;
        }
    }
}
