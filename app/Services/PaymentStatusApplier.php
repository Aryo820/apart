<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Apartment;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Satu-satunya tempat status Midtrans diterjemahkan menjadi status payment +
 * booking kita.
 *
 * Dipakai DUA pemanggil:
 *   - PaymentController::callback — webhook Midtrans (payload dipercaya setelah
 *     signature SHA-512 diverifikasi di sana);
 *   - PaymentController::reconcile — user kembali dari Snap dan kita menanyakan
 *     status langsung ke Midtrans (payload dipercaya karena kita yang meminta,
 *     server-ke-server dengan server key sendiri).
 *
 * Logikanya sengaja TIDAK diduplikasi untuk jalur kedua: dua penulis status yang
 * berkembang sendiri-sendiri adalah persis cara double booking pada settlement
 * terlambat dulu lolos. Semua guard — idempotensi, kesesuaian nominal, dan cek
 * konflik sebelum Confirmed — berlaku sama untuk keduanya.
 */
class PaymentStatusApplier
{
    /**
     * Pemetaan transaction_status Midtrans ke enum kita plus status booking yang
     * mengikutinya. 'capture' diselesaikan terpisah (resolveStatus) karena
     * bergantung pada fraud_status.
     */
    private const STATUS_MAP = [
        'settlement' => ['payment' => PaymentStatus::Settlement, 'booking' => BookingStatus::Confirmed],
        'pending' => ['payment' => PaymentStatus::Pending,    'booking' => BookingStatus::Pending],
        'deny' => ['payment' => PaymentStatus::Failed,     'booking' => BookingStatus::Cancelled],
        'failure' => ['payment' => PaymentStatus::Failed,     'booking' => BookingStatus::Cancelled],
        'cancel' => ['payment' => PaymentStatus::Cancel,     'booking' => BookingStatus::Cancelled],
        'expire' => ['payment' => PaymentStatus::Expire,     'booking' => BookingStatus::Expired],
    ];

    /**
     * Satu-satunya status pembayaran yang benar-benar tidak boleh berubah lagi:
     * uangnya sudah masuk. Notifikasi apa pun sesudahnya diabaikan.
     */
    private const TERMINAL_PAYMENT_STATUS = PaymentStatus::Settlement;

    /**
     * Status "sesi pembayaran ditutup tanpa uang masuk". Notifikasi berikutnya
     * diabaikan — KECUALI notifikasi itu melaporkan pembayaran BERHASIL.
     *
     * Snap mengizinkan tamu mencoba kartu atau metode lain pada order_id yang
     * sama setelah percobaan pertama ditolak, jadi settlement sesudah 'failed'
     * itu nyata dan uangnya nyata. Mengabaikannya berarti tamu membayar tanpa
     * mendapat reservasi, dan satu-satunya jejaknya ada di dashboard Midtrans.
     */
    private const CLOSED_PAYMENT_STATUSES = [
        PaymentStatus::Failed,
        PaymentStatus::Cancel,
        PaymentStatus::Expire,
    ];

    /**
     * @param  array<string, mixed>  $payload  payload notifikasi Midtrans, atau
     *                                         respons Transaction::status yang
     *                                         bentuknya sama.
     * @return string salah satu: success | already_processed | not_found |
     *                unhandled | amount_mismatch | settled_but_unavailable
     */
    public function apply(string $bookingCode, array $payload): string
    {
        return DB::transaction(function () use ($bookingCode, $payload) {
            $booking = Booking::with('payment')->where('booking_code', $bookingCode)->first();
            if (! $booking || ! $booking->payment) {
                return 'not_found';
            }

            /*
             * Urutan kunci global project ini: Apartment -> Booking -> Payment
             * -> rentang rival (BookingController::store dan guard panel
             * BookingResource memakai urutan yang sama). Lock baris unit
             * diambil lebih dulu — termasuk untuk notifikasi non-Confirmed
             * yang tidak menilai ketersediaan — agar tidak ada transaksi yang
             * memegang baris booking/payment sambil menunggu lock unit yang
             * sedang dipegang jalur pembuatan booking: pola itulah yang bisa
             * berujung deadlock.
             *
             * Baris payment di-lock supaya webhook ganda (retry Midtrans) dan
             * rekonsiliasi yang berbarengan berbaris, bukan saling balapan;
             * baris booking dibaca ULANG di bawah lock karena statusnya bisa
             * sudah berubah sejak identitasnya dimuat di atas.
             */
            Apartment::whereKey($booking->apartment_id)->lockForUpdate()->first();
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->first();
            $payment = Payment::where('id', $booking->payment->id)->lockForUpdate()->first();

            if (! $locked || ! $payment) {
                return 'not_found';
            }

            // Idempotency: uang sudah masuk, tidak ada notifikasi yang boleh
            // mengubahnya lagi.
            if ($payment->status === self::TERMINAL_PAYMENT_STATUS) {
                return 'already_processed';
            }

            $mapped = $this->resolveStatus($payload);
            if (! $mapped) {
                return 'unhandled';
            }

            // Sesi yang sudah ditutup tanpa pembayaran hanya boleh dibuka
            // kembali oleh notifikasi pembayaran berhasil; sisanya diabaikan
            // supaya statusnya tidak berputar-putar mengikuti notifikasi yang
            // menyusul.
            if (in_array($payment->status, self::CLOSED_PAYMENT_STATUSES, true)
                && $mapped['payment'] !== PaymentStatus::Settlement) {
                return 'already_processed';
            }

            // Defense in depth: pada jalur webhook signature membuktikan asal
            // payload, tapi nominalnya masih harus sama dengan yang kita catat.
            if ((float) ($payload['gross_amount'] ?? 0) !== (float) $payment->gross_amount) {
                return 'amount_mismatch';
            }

            $payment->update([
                'status' => $mapped['payment'],
                'transaction_id' => $payload['transaction_id'] ?? $payment->transaction_id,
                'payment_type' => $payload['payment_type'] ?? $payment->payment_type ?? 'midtrans',
                'raw_response' => $payload,
            ]);

            return $this->applyBookingStatus($locked, $mapped, $payload);
        });
    }

    /**
     * @param  array{payment: PaymentStatus, booking: BookingStatus}  $mapped
     * @param  array<string, mixed>  $payload
     */
    private function applyBookingStatus(Booking $booking, array $mapped, array $payload): string
    {
        /*
         * SETIAP transisi menuju Confirmed melewati pemeriksaan konflik terbaru
         * — termasuk ketika booking-nya masih Pending.
         *
         * Alasannya: Booking::scopeBlocking membebaskan tanggal tepat saat batas
         * waktu pembayaran lewat, sementara baris booking-nya baru ditandai
         * Expired oleh bookings:expire-pending beberapa menit kemudian (atau
         * tidak pernah, kalau scheduler tidak dipasang). Jadi "statusnya masih
         * Pending" BUKAN bukti bahwa tanggalnya masih milik booking ini.
         *
         * Untuk pembayaran yang tepat waktu pemeriksaan ini no-op: booking
         * Pending yang belum melewati deadline masih memblokir tanggalnya
         * sendiri, jadi tidak mungkin ada booking lain yang memblokir rentang
         * yang sama.
         *
         * lockForUpdate: rentang yang diperiksa dikunci sampai transaksi selesai,
         * jadi booking baru tidak bisa menyelip di antara pemeriksaan dan
         * penulisan status.
         */
        if ($mapped['booking'] === BookingStatus::Confirmed) {
            $rivals = Booking::conflicting(
                $booking->apartment_id,
                $booking->check_in->toDateString(),
                $booking->check_out->toDateString(),
            )->whereKeyNot($booking->id)
                ->lockForUpdate()
                ->get(['id', 'booking_code', 'status']);

            /*
             * Uangnya nyata, jadi tidak diabaikan diam-diam: statusnya dibiarkan
             * apa adanya dan kasusnya dicatat untuk refund manual. Memaksa
             * Confirmed berarti dua tamu memegang kamar yang sama — kerugian
             * yang lebih besar daripada satu refund.
             */
            if ($rivals->isNotEmpty()) {
                Log::warning('Pembayaran settle tapi tanggalnya sudah dipegang booking lain. Perlu refund manual.', [
                    'booking_code' => $booking->booking_code,
                    'booking_status' => $booking->status->value,
                    'apartment_id' => $booking->apartment_id,
                    'check_in' => $booking->check_in->toDateString(),
                    'check_out' => $booking->check_out->toDateString(),
                    'conflicting_bookings' => $rivals->map(fn ($rival) => [
                        'booking_code' => $rival->booking_code,
                        'status' => $rival->status->value,
                    ])->all(),
                    'transaction_id' => $payload['transaction_id'] ?? null,
                    'gross_amount' => $payload['gross_amount'] ?? null,
                ]);

                return 'settled_but_unavailable';
            }

            $booking->update(['status' => BookingStatus::Confirmed]);

            return 'success';
        }

        /*
         * Pemetaan non-Confirmed (pending / cancelled / expired) hanya boleh
         * menimpa booking yang masih Pending. Booking yang sudah final tidak
         * boleh diubah notifikasi yang terlambat — kalau tidak, pembatalan
         * sungguhan bisa berubah menjadi 'expired' hanya karena Midtrans
         * menyusul mengirim notifikasi kedaluwarsa beberapa menit kemudian.
         */
        if ($booking->status === BookingStatus::Pending) {
            $booking->update(['status' => $mapped['booking']]);
        }

        return 'success';
    }

    /**
     * Terjemahkan transaction_status, dengan 'capture' (kartu kredit) ditangani
     * terpisah karena membawa fraud_status.
     *
     * @param  array<string, mixed>  $payload
     * @return array{payment: PaymentStatus, booking: BookingStatus}|null
     */
    private function resolveStatus(array $payload): ?array
    {
        $transactionStatus = $payload['transaction_status'] ?? null;

        if ($transactionStatus === 'capture') {
            return match ($payload['fraud_status'] ?? 'accept') {
                'accept' => ['payment' => PaymentStatus::Settlement, 'booking' => BookingStatus::Confirmed],
                'challenge' => ['payment' => PaymentStatus::Pending, 'booking' => BookingStatus::Pending],
                'deny' => ['payment' => PaymentStatus::Failed, 'booking' => BookingStatus::Cancelled],
                default => null,
            };
        }

        return self::STATUS_MAP[$transactionStatus] ?? null;
    }
}
