<?php

namespace App\Services;

use App\Exceptions\PaymentGatewayException;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Str;
use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Transaction;

class MidtransService
{
    public function __construct()
    {
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized = config('midtrans.is_sanitized');
        Config::$is3ds = config('midtrans.is_3ds');

        /*
         * SDK tidak memasang timeout bawaan. Karena getSnapToken dipanggil DI
         * DALAM transaksi booking (BookingController::store) yang kini memegang
         * lock baris apartment, gateway yang menggantung tanpa batas berarti
         * antrean reservasi satu unit ikut menggantung. Batasi di sini agar
         * hold-lock selalu terbatas.
         */
        Config::$curlOptions += [
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
        ];
    }

    /**
     * Nominal yang benar-benar ditagihkan ke tamu untuk sebuah reservasi.
     *
     * SATU-SATUNYA sumber nominal pembayaran. Alasannya ada di SDK:
     * Midtrans\Snap::createTransaction MENGHITUNG ULANG gross_amount dari
     * item_details (quantity × price) dan MENIMPA nilai yang kita kirim, jadi
     * jumlah yang ditagih selalu floor(total / malam) × malam. Untuk tarif
     * rupiah bulat hasilnya identik dengan total_price; untuk tarif pecahan
     * tamu tertagih kurang sementara payments.gross_amount mencatat total —
     * PaymentStatusApplier kemudian menolak settlement-nya selamanya padahal
     * uang sudah pindah.
     *
     * Karena itu invariant-nya divalidasi di sini, SEBELUM token diterbitkan:
     * harga harus positif dan habis dibagi jumlah malam. Pelanggaran gagal
     * keras sebagai PaymentGatewayException — reservasi tidak jadi dibuat —
     * alih-alih menyimpan booking yang pembayarannya mustahil terkonfirmasi.
     *
     * Sengaja statis dan murni (tanpa state service): dipakai controller untuk
     * mengisi payments.gross_amount dan oleh getSnapToken untuk item_details,
     * sehingga keduanya mustahil berbeda angka.
     */
    public static function chargeAmount(int|float $totalPrice, int $nights): int
    {
        $total = (int) $totalPrice;
        $nights = max(1, $nights);

        if ($total <= 0 || $total % $nights !== 0) {
            throw new PaymentGatewayException(sprintf(
                'Total reservasi Rp %s untuk %d malam bukan kelipatan rupiah bulat per malam. Perbaiki tarif unit sebelum reservasi dibuat.',
                number_format((float) $totalPrice, 2, ',', '.'),
                $nights,
            ));
        }

        return $total;
    }

    /**
     * @param  string|null  $orderId  order_id yang dipakai transaksi ini. Pemanggil
     *                                menyediakannya (dan menyimpannya di baris
     *                                payment) supaya statusnya bisa ditanyakan
     *                                kembali ke Midtrans saat user kembali dari
     *                                Snap; dibuat di sini hanya sebagai fallback.
     */
    public function getSnapToken(Booking $booking, ?string $orderId = null): string
    {
        $chargeAmount = self::chargeAmount((float) $booking->total_price, (int) $booking->total_nights);
        $perNight = intdiv($chargeAmount, max(1, (int) $booking->total_nights));

        $params = [
            'transaction_details' => [
                'order_id' => $orderId ?? Payment::generateOrderId($booking->booking_code),
                'gross_amount' => $chargeAmount,
            ],
            'customer_details' => [
                'first_name' => $booking->user->name,
                'email' => $booking->user->email,
                'phone' => $booking->user->phone ?? '081234567890',
            ],
            'item_details' => [
                [
                    'id' => 'APT-'.$booking->apartment->id,
                    'price' => $perNight,
                    'quantity' => $booking->total_nights,
                    'name' => mb_substr($booking->apartment->title, 0, 45),
                ],
            ],
            'callbacks' => [
                'finish' => route('bookings.show', [$booking->booking_code, 'from' => 'midtrans']),
            ],

            /*
             * Masa berlaku transaksi di sisi gateway disamakan dengan deadline
             * kita (Booking::paymentDeadlineAt). Tanpa ini keduanya bisa
             * berbeda: tamu masih bisa membayar lewat Snap setelah booking kita
             * tandai expired dan tanggalnya sudah diambil orang lain — uang
             * masuk untuk kamar yang tidak lagi tersedia.
             *
             * Duration = menit yang TERSISA, bukan durasi penuh, supaya token
             * yang diterbitkan ulang di tengah jalan tetap berakhir pada
             * deadline yang sama, bukan memperpanjangnya.
             */
            'expiry' => [
                'start_time' => now()->format('Y-m-d H:i:s O'),
                'unit' => 'minute',
                'duration' => $this->remainingPaymentMinutes($booking),
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

    /**
     * Status transaksi menurut Midtrans, dibentuk seperti payload notifikasi
     * (transaction_status, fraud_status, gross_amount, transaction_id,
     * payment_type) sehingga bisa diproses oleh penerap status yang sama dengan
     * webhook — bukan jalur penulisan status kedua.
     *
     * Dipanggil server-ke-server dengan server key kita sendiri lewat HTTPS,
     * jadi tidak ada signature yang perlu diverifikasi: asal datanya sudah
     * dipercaya karena kita yang meminta.
     *
     * @return array<string, mixed>
     *
     * @throws \Exception saat transaksinya tidak dikenal Midtrans atau API-nya
     *                    tidak terjangkau — pemanggil yang memutuskan apa yang
     *                    ditampilkan ke user.
     */
    public function getTransactionStatus(string $orderId): array
    {
        $status = Transaction::status($orderId);

        return json_decode(json_encode($status), true) ?: [];
    }

    /**
     * Sisa menit sampai batas waktu pembayaran. Minimal 1 karena Midtrans
     * menolak duration <= 0; pemanggil yang bertanggung jawab tidak meminta
     * token untuk booking yang sudah kedaluwarsa (BookingController::show).
     */
    private function remainingPaymentMinutes(Booking $booking): int
    {
        $deadline = $booking->paymentDeadlineAt();

        if (! $deadline) {
            return (int) config('booking.payment_expiry_minutes');
        }

        return max(1, (int) ceil(now()->diffInMinutes($deadline, false)));
    }
}
