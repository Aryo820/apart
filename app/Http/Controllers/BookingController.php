<?php

namespace App\Http\Controllers;

use App\Enums\ApartmentStatus;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\BookingConflictException;
use App\Exceptions\PaymentGatewayException;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Apartment;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\MidtransService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BookingController extends Controller
{
    protected MidtransService $midtransService;

    public function __construct(MidtransService $midtransService)
    {
        $this->midtransService = $midtransService;
    }

    public function store(StoreBookingRequest $request)
    {
        $bookingCode = Booking::generateBookingCode();

        // Booking DAN payment dibuat dalam satu transaksi: kalau gateway tak
        // terjangkau, seluruh booking ikut tergulung.
        //
        // Urutan kunci di project ini adalah Apartment -> Booking -> rentang
        // rival -> Payment. Lock baris unit dipasang SEBELUM pemeriksaan
        // konflik dan berlaku sampai commit, sehingga dua request yang
        // bersaing pada unit yang sama pasti berbaris pada lock ini — bukan
        // bergantung pada kebetulan gap-lock InnoDB saat hasil konflik kosong
        // (kalender kosong = tidak ada baris yang bisa dikunci oleh query
        // konflik). Unit lain tidak terpengaruh; serialisasinya per-unit.
        try {
            $booking = DB::transaction(function () use ($request, $bookingCode) {
                $apartment = Apartment::where('id', $request->integer('apartment_id'))
                    ->lockForUpdate()
                    ->first();

                if (! $apartment || $apartment->status !== ApartmentStatus::Available) {
                    abort(404);
                }

                $checkIn = Carbon::parse($request->check_in);
                $checkOut = Carbon::parse($request->check_out);
                $nights = Booking::nightsBetween($checkIn, $checkOut);
                $totalPrice = Booking::priceFor($apartment, $nights);

                // Gagal keras sebelum token diterbitkan bila tarif unit tidak
                // memenuhi invariant Midtrans (rupiah bulat per malam) —
                // alih-alih menagih tamu selisih lalu menolak settlement-nya
                // selamanya. Lihat MidtransService::chargeAmount.
                $chargeAmount = MidtransService::chargeAmount((float) $totalPrice, $nights);

                $conflict = Booking::conflicting($apartment->id, $checkIn->toDateString(), $checkOut->toDateString())
                    ->lockForUpdate()
                    ->exists();

                if ($conflict) {
                    throw new BookingConflictException;
                }

                $booking = Booking::create([
                    'booking_code' => $bookingCode,
                    'user_id' => Auth::id(),
                    'apartment_id' => $apartment->id,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'total_nights' => $nights,
                    'total_price' => $totalPrice,
                    'status' => BookingStatus::Pending,
                    'notes' => $request->notes,
                ]);

                try {
                    // order_id dibuat di sini, bukan di dalam service, supaya
                    // nilai yang dikirim ke Midtrans ikut tersimpan: itu satu-
                    // satunya cara menanyakan status transaksi ini kembali saat
                    // tamu balik dari Snap (PaymentController::reconcile).
                    $orderId = Payment::generateOrderId($booking->booking_code);
                    $snapToken = $this->midtransService->getSnapToken($booking, $orderId);
                } catch (\Throwable $e) {
                    throw new PaymentGatewayException(previous: $e);
                }

                Payment::create([
                    'booking_id' => $booking->id,
                    'order_id' => $orderId,
                    // Nominal yang DISIMPAN harus sama dengan yang DITAGIHKAN:
                    // angka inilah pembanding PaymentStatusApplier untuk
                    // notifikasi dari gateway.
                    'gross_amount' => $chargeAmount,
                    'snap_token' => $snapToken,
                    'status' => PaymentStatus::Pending,
                ]);

                return $booking;
            });
        } catch (BookingConflictException $e) {
            return back()->withErrors(['check_in' => 'Apartemen ini tidak tersedia pada tanggal yang Anda pilih.'])->withInput();
        } catch (PaymentGatewayException $e) {
            report($e);

            return back()->withErrors(['payment' => 'Gagal terhubung ke gerbang pembayaran. Silakan coba lagi.'])->withInput();
        }

        return redirect()->route('bookings.show', $booking->booking_code)
            ->with('success', 'Booking berhasil dibuat! Silakan lanjutkan pembayaran.');
    }

    public function index()
    {
        $bookings = Booking::with(['apartment', 'payment'])
            ->where('user_id', Auth::id())
            ->latest()
            ->paginate(10);

        return view('bookings.index', compact('bookings'));
    }

    public function show($code)
    {
        $booking = Booking::with(['apartment', 'user', 'payment'])
            ->where('booking_code', $code)
            ->firstOrFail();

        // Ensure authorization
        if (Auth::id() !== $booking->user_id && ! Auth::user()?->isAdmin()) {
            abort(403);
        }

        // Refresh or generate snap token if missing. Sejak MidtransService
        // mengirim `expiry` yang sama dengan deadline kita, token tidak bisa
        // lagi mati lebih dulu dari booking-nya — jadi pemeriksaan umur token
        // 24 jam yang lama tidak diperlukan. Yang tersisa hanya kasus gateway
        // sempat tidak terjangkau sehingga token belum pernah terbit.
        // Gateway failures degrade gracefully: halaman tetap ter-render.
        if ($booking->status === BookingStatus::Pending && ! $booking->isPaymentOverdue()) {
            try {
                if (! $booking->payment) {
                    $orderId = Payment::generateOrderId($booking->booking_code);

                    Payment::create([
                        'booking_id' => $booking->id,
                        'order_id' => $orderId,
                        // Sumber nominal yang sama dengan store(): angka yang
                        // disimpan harus sama dengan yang akan ditagihkan.
                        'gross_amount' => MidtransService::chargeAmount((float) $booking->total_price, (int) $booking->total_nights),
                        'snap_token' => $this->midtransService->getSnapToken($booking, $orderId),
                        'status' => PaymentStatus::Pending,
                    ]);
                    $booking->load('payment');
                } elseif (! $booking->payment->snap_token && $booking->payment->status === PaymentStatus::Pending) {
                    // order_id ikut diperbarui: transaksi yang bisa ditanyakan
                    // statusnya adalah yang tokennya baru diterbitkan ini.
                    $orderId = Payment::generateOrderId($booking->booking_code);

                    $booking->payment->update([
                        'order_id' => $orderId,
                        'snap_token' => $this->midtransService->getSnapToken($booking, $orderId),
                    ]);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return view('bookings.show', compact('booking'));
    }

    /**
     * Pembatalan oleh tamu. Hanya untuk booking yang belum dibayar — aturannya
     * ada di Booking::isCancellableByGuest, dan diperiksa ULANG di dalam
     * transaksi setelah lockForUpdate karena webhook Midtrans atau command
     * bookings:expire-pending bisa mengubah status di antara pemuatan halaman
     * dan penekanan tombol.
     */
    public function cancel(string $code)
    {
        $booking = Booking::with('payment')->where('booking_code', $code)->firstOrFail();

        Gate::authorize('cancel', $booking);

        $cancelled = DB::transaction(function () use ($booking) {
            $fresh = Booking::with('payment')->whereKey($booking->id)->lockForUpdate()->first();

            if (! $fresh || ! $fresh->isCancellableByGuest()) {
                return false;
            }

            $fresh->update(['status' => BookingStatus::Cancelled]);

            // Sesi pembayaran yang masih menganggur ikut ditutup supaya tidak
            // ada jalan membayar reservasi yang sudah dibatalkan sendiri.
            if ($fresh->payment && $fresh->payment->status === PaymentStatus::Pending) {
                $fresh->payment->update(['status' => PaymentStatus::Cancel]);
            }

            return true;
        });

        if (! $cancelled) {
            return back()->withErrors([
                'cancel' => 'Reservasi ini sudah tidak bisa dibatalkan sendiri. Status-nya berubah sebelum permintaan Anda diproses.',
            ]);
        }

        return redirect()->route('bookings.show', $booking->booking_code)
            ->with('success', 'Reservasi dibatalkan. Tanggalnya sudah tersedia kembali untuk dipesan.');
    }
}
