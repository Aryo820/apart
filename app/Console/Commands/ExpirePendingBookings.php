<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Menutup booking Pending yang batas waktu pembayarannya sudah lewat.
 *
 * Menggantikan closure lama di routes/console.php yang menandai booking
 * seperti itu sebagai 'cancelled' — status yang sama dengan pembatalan
 * sungguhan, sehingga admin maupun tamu tidak bisa membedakan "tidak pernah
 * dibayar" dari "dibatalkan". Sekarang statusnya 'expired'.
 *
 * Tanggal sebenarnya sudah bebas lebih dulu lewat Booking::scopeBlocking —
 * command ini yang membereskan status agar tampilan dan laporan jujur, bukan
 * yang menentukan ketersediaan. Jadi keterlambatan scheduler tidak pernah
 * membuat tanggal terkunci lebih lama.
 */
class ExpirePendingBookings extends Command
{
    protected $signature = 'bookings:expire-pending';

    protected $description = 'Tandai booking pending yang melewati batas waktu pembayaran sebagai expired';

    public function handle(): int
    {
        $cutoff = Booking::paymentExpiryCutoff();
        $expired = 0;

        /*
         * Idempotent: filternya adalah status Pending + created_at di bawah
         * cutoff. Setelah baris diubah menjadi Expired ia tidak cocok lagi,
         * jadi menjalankan command dua kali tidak berefek pada baris yang sama.
         *
         * lockForUpdate di dalam transaksi per booking: webhook Midtrans bisa
         * datang pada detik yang sama, dan status dibaca ulang setelah lock
         * supaya pembayaran yang baru saja settle tidak tertimpa.
         */
        Booking::query()
            ->where('status', BookingStatus::Pending->value)
            ->where('created_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($bookings) use ($cutoff, &$expired) {
                foreach ($bookings as $booking) {
                    $expired += DB::transaction(function () use ($booking, $cutoff) {
                        $fresh = Booking::whereKey($booking->id)->lockForUpdate()->first();

                        if (! $fresh || $fresh->status !== BookingStatus::Pending) {
                            return 0;
                        }

                        if ($fresh->created_at->gt($cutoff)) {
                            return 0;
                        }

                        $fresh->update(['status' => BookingStatus::Expired]);

                        /*
                         * Payment ikut ditutup kalau masih Pending, supaya
                         * halaman reservasi tidak selamanya menampilkan
                         * "Menunggu pembayaran" untuk booking yang sudah mati.
                         * Status final (settlement/failed/cancel/expire) tidak
                         * disentuh — itu catatan dari gateway, bukan milik kita.
                         */
                        $payment = $fresh->payment;

                        if ($payment && $payment->status === PaymentStatus::Pending) {
                            $payment->update(['status' => PaymentStatus::Expire]);
                        }

                        return 1;
                    });
                }
            });

        $this->info("Booking expired: {$expired}");

        return self::SUCCESS;
    }
}
