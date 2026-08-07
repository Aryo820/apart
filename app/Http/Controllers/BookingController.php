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

class BookingController extends Controller
{
    protected MidtransService $midtransService;

    public function __construct(MidtransService $midtransService)
    {
        $this->midtransService = $midtransService;
    }

    public function store(StoreBookingRequest $request)
    {
        $apartment = Apartment::where('id', $request->apartment_id)
            ->where('status', ApartmentStatus::Available)
            ->firstOrFail();

        $checkIn = Carbon::parse($request->check_in);
        $checkOut = Carbon::parse($request->check_out);
        $nights = max(1, $checkIn->diffInDays($checkOut));
        $totalPrice = $nights * $apartment->price_per_night;

        $bookingCode = Booking::generateBookingCode();

        // Lock existing bookings for this apartment so two concurrent
        // requests can't both pass the availability check (double booking).
        // Booking AND payment are created in the same transaction: if the
        // payment gateway is unreachable the whole booking rolls back.
        try {
            $booking = DB::transaction(function () use ($apartment, $request, $checkIn, $checkOut, $nights, $totalPrice, $bookingCode) {
                $conflict = Booking::conflicting($apartment->id, $request->check_in, $request->check_out)
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
                    $snapToken = $this->midtransService->getSnapToken($booking);
                } catch (\Throwable $e) {
                    throw new PaymentGatewayException(previous: $e);
                }

                Payment::create([
                    'booking_id' => $booking->id,
                    'gross_amount' => $totalPrice,
                    'snap_token' => $snapToken,
                    'status' => PaymentStatus::Pending,
                ]);

                return $booking;
            });
        } catch (BookingConflictException $e) {
            return back()->withErrors(['check_in' => 'Apartemen ini tidak tersedia pada tanggal yang Anda pilih.'])->withInput();
        } catch (PaymentGatewayException $e) {
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

        // Refresh or generate snap token if missing or expired (Midtrans
        // tokens last ~24h). Gateway failures degrade gracefully: the
        // booking detail page still renders, the user can retry later.
        try {
            if (! $booking->payment) {
                $snapToken = $this->midtransService->getSnapToken($booking);
                Payment::create([
                    'booking_id' => $booking->id,
                    'gross_amount' => $booking->total_price,
                    'snap_token' => $snapToken,
                    'status' => PaymentStatus::Pending,
                ]);
                $booking->load('payment');
            } elseif (
                $booking->payment->status === PaymentStatus::Pending
                && $booking->payment->created_at->lt(now()->subDay())
            ) {
                $booking->payment->update([
                    'snap_token' => $this->midtransService->getSnapToken($booking),
                ]);
            }
        } catch (\Throwable $e) {
            // Gateway unreachable — keep whatever token exists.
        }

        return view('bookings.show', compact('booking'));
    }
}
