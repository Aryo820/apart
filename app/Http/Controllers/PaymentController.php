<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\MidtransService;
use App\Services\PaymentStatusApplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentStatusApplier $applier,
        private MidtransService $midtransService,
    ) {}

    /**
     * Webhook Midtrans. Tugasnya hanya membuktikan payload benar-benar dari
     * Midtrans, lalu menyerahkan penerapan statusnya ke PaymentStatusApplier —
     * penulis status yang sama dengan jalur rekonsiliasi di bawah.
     */
    public function callback(Request $request)
    {
        if (! $this->signatureIsValid($request)) {
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        // order_id = "{booking_code}-{timestamp}"; booking_code itself
        // contains dashes, so strip only the trailing timestamp segment.
        $bookingCode = Str::beforeLast((string) $request->order_id, '-');

        $result = $this->applier->apply($bookingCode, $request->all());

        return match ($result) {
            'not_found' => response()->json(['message' => 'Booking not found'], 404),
            'already_processed' => response()->json(['status' => 'already_processed']),
            'unhandled' => response()->json(['message' => 'Unhandled status'], 422),
            'amount_mismatch' => response()->json(['message' => 'Amount mismatch'], 422),
            // 200: pembayarannya sudah tercatat, jadi Midtrans tidak perlu
            // mengulang kirim. Tindak lanjutnya manual, bukan teknis.
            'settled_but_unavailable' => response()->json(['status' => 'settled_but_unavailable']),
            default => response()->json(['status' => 'success']),
        };
    }

    /**
     * Rekonsiliasi saat tamu kembali dari Snap.
     *
     * Masalah yang diselesaikan: status hanya pernah ditulis webhook, sementara
     * webhook bisa tertunda, gagal terkirim, atau — di development — tidak bisa
     * menjangkau localhost sama sekali. Tanpa jalur ini tamu yang baru saja
     * membayar kembali ke halaman yang masih berbunyi "menunggu pembayaran",
     * lengkap dengan tombol bayar yang mengundangnya membayar dua kali.
     *
     * Statusnya TIDAK dipercaya dari browser: yang dikirim tamu hanya "tolong
     * periksa". Kebenarannya diambil server-ke-server dari Midtrans, lalu
     * diterapkan oleh penerap status yang sama dengan webhook — dengan seluruh
     * guard-nya (idempotensi, nominal, cek konflik sebelum Confirmed).
     */
    public function reconcile(string $code)
    {
        $booking = Booking::with('payment')->where('booking_code', $code)->firstOrFail();

        if (Auth::id() !== $booking->user_id && ! Auth::user()?->isAdmin()) {
            abort(403);
        }

        $orderId = $booking->payment?->order_id;

        // Redirect ke route-nya, bukan back(): halaman reservasi bisa memicu
        // rekonsiliasi ini otomatis saat tamu kembali dari Snap (?from=midtrans),
        // dan back() akan mengembalikannya ke URL yang sama — berputar terus.

        // Booking yang dibuat admin lewat Filament, atau payment dari sebelum
        // kolom order_id ada: tidak ada transaksi yang bisa ditanyakan.
        if (! $orderId) {
            return redirect()->route('bookings.show', $booking->booking_code)
                ->with('status', 'Belum ada sesi pembayaran yang bisa diperiksa untuk reservasi ini.');
        }

        try {
            $payload = $this->midtransService->getTransactionStatus($orderId);
        } catch (\Throwable $e) {
            // Gateway tidak terjangkau atau transaksinya belum terdaftar di
            // Midtrans (tamu menutup Snap sebelum memilih metode pembayaran).
            // Webhook tetap menjadi jaring pengaman, jadi ini bukan kegagalan
            // yang perlu ditampilkan sebagai error.
            report($e);

            return redirect()->route('bookings.show', $booking->booking_code)
                ->with('status', 'Status pembayaran belum bisa diperiksa sekarang. Coba lagi beberapa saat lagi.');
        }

        $result = $this->applier->apply($booking->booking_code, $payload);
        $fresh = $booking->fresh();

        Log::info('Rekonsiliasi status pembayaran dari halaman reservasi.', [
            'booking_code' => $booking->booking_code,
            'order_id' => $orderId,
            'midtrans_status' => $payload['transaction_status'] ?? null,
            'result' => $result,
            'booking_status' => $fresh?->status->value,
        ]);

        return redirect()
            ->route('bookings.show', $booking->booking_code)
            ->with($this->reconcileFlash($result, $fresh));
    }

    /**
     * Pesan hasil rekonsiliasi. 'success' dibedakan menurut status booking
     * SETELAH penerapan: hasil yang sama bisa berarti terkonfirmasi, masih
     * menunggu (mis. VA belum dibayar), atau ditutup.
     *
     * @return array<string, string>
     */
    private function reconcileFlash(string $result, ?Booking $booking): array
    {
        if ($result === 'settled_but_unavailable') {
            return ['status' => 'Pembayaran Anda tercatat, tetapi tanggalnya sudah dipesan tamu lain sebelum pembayaran ini sampai. Tim kami akan menindaklanjuti pengembalian dana.'];
        }

        return match ($booking?->status) {
            BookingStatus::Confirmed => ['success' => 'Pembayaran Anda sudah diterima. Reservasi ini terkonfirmasi.'],
            BookingStatus::Cancelled => ['status' => 'Menurut Midtrans pembayaran ini tidak berhasil, jadi reservasinya dibatalkan.'],
            BookingStatus::Expired => ['status' => 'Batas waktu pembayaran reservasi ini sudah lewat.'],
            default => ['status' => 'Belum ada pembayaran yang diterima Midtrans untuk reservasi ini. Selesaikan pembayaran sebelum batas waktunya.'],
        };
    }

    /**
     * Verify Midtrans' SHA-512 signature. Only bypassed in local dev when
     * the configured server key is still the demo placeholder — a real key
     * (including in local) is always verified.
     */
    private function signatureIsValid(Request $request): bool
    {
        $serverKey = (string) config('midtrans.server_key');
        $expected = hash('sha512', $request->order_id.$request->status_code.$request->gross_amount.$serverKey);

        if (hash_equals($expected, (string) $request->signature_key)) {
            return true;
        }

        return app()->isLocal() && Str::contains($serverKey, 'Demo');
    }
}
