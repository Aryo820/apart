<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    /**
     * Maps a Midtrans transaction_status onto our payment enum plus the
     * resulting booking status. Keeps both in sync and guarantees we never
     * write a value outside the payments.status enum.
     *
     * 'capture' is resolved separately (resolveStatus) because it depends
     * on Midtrans' fraud_status: accept -> paid, challenge -> hold for
     * manual review, deny -> failed.
     */
    private const STATUS_MAP = [
        'settlement' => ['payment' => PaymentStatus::Settlement, 'booking' => BookingStatus::Confirmed],
        'pending' => ['payment' => PaymentStatus::Pending,    'booking' => BookingStatus::Pending],
        'deny' => ['payment' => PaymentStatus::Failed,     'booking' => BookingStatus::Cancelled],
        'failure' => ['payment' => PaymentStatus::Failed,     'booking' => BookingStatus::Cancelled],
        'cancel' => ['payment' => PaymentStatus::Cancel,     'booking' => BookingStatus::Cancelled],
        'expire' => ['payment' => PaymentStatus::Expire,     'booking' => BookingStatus::Cancelled],
    ];

    /** Payment statuses that must never transition again. */
    private const FINAL_PAYMENT_STATUSES = [
        PaymentStatus::Settlement,
        PaymentStatus::Failed,
        PaymentStatus::Cancel,
        PaymentStatus::Expire,
    ];

    public function callback(Request $request)
    {
        if (! $this->signatureIsValid($request)) {
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        // order_id = "{booking_code}-{timestamp}"; booking_code itself
        // contains dashes, so strip only the trailing timestamp segment.
        $bookingCode = Str::beforeLast((string) $request->order_id, '-');

        $result = DB::transaction(function () use ($request, $bookingCode) {
            $booking = Booking::with('payment')->where('booking_code', $bookingCode)->first();
            if (! $booking || ! $booking->payment) {
                return ['error' => 'not_found'];
            }

            // Lock the payment row so concurrent webhooks (Midtrans retries)
            // serialize instead of racing each other (last-write-wins bug).
            $payment = Payment::where('id', $booking->payment->id)->lockForUpdate()->first();
            if (! $payment) {
                return ['error' => 'not_found'];
            }

            // Idempotency: if the payment already reached a final state,
            // don't process again. Prevents double-charging on webhook retries.
            if (in_array($payment->status, self::FINAL_PAYMENT_STATUSES, true)) {
                return ['error' => 'already_processed'];
            }

            $mapped = $this->resolveStatus($request);
            if (! $mapped) {
                return ['error' => 'unhandled'];
            }

            // Defense in depth: the signature proves the payload comes from
            // Midtrans, but the nominal must still match what we expected.
            if ((float) $request->gross_amount !== (float) $payment->gross_amount) {
                return ['error' => 'amount_mismatch'];
            }

            $payment->update([
                'status' => $mapped['payment'],
                'transaction_id' => $request->transaction_id ?? $payment->transaction_id,
                'payment_type' => $request->payment_type ?? $payment->payment_type ?? 'midtrans',
                'raw_response' => $request->all(),
            ]);
            $booking->update(['status' => $mapped['booking']]);

            return ['status' => 'success'];
        });

        return match ($result['error'] ?? null) {
            'not_found' => response()->json(['message' => 'Booking not found'], 404),
            'already_processed' => response()->json(['status' => 'already_processed']),
            'unhandled' => response()->json(['message' => 'Unhandled status'], 422),
            'amount_mismatch' => response()->json(['message' => 'Amount mismatch'], 422),
            default => response()->json(['status' => 'success']),
        };
    }

    /**
     * Resolve the transaction status onto our status pair, handling the
     * credit-card 'capture' status (which carries fraud_status) separately.
     */
    private function resolveStatus(Request $request): ?array
    {
        if ($request->transaction_status === 'capture') {
            $fraudStatus = $request->fraud_status ?? 'accept';

            return match ($fraudStatus) {
                'accept' => ['payment' => PaymentStatus::Settlement, 'booking' => BookingStatus::Confirmed],
                'challenge' => ['payment' => PaymentStatus::Pending, 'booking' => BookingStatus::Pending],
                'deny' => ['payment' => PaymentStatus::Failed, 'booking' => BookingStatus::Cancelled],
                default => null,
            };
        }

        return self::STATUS_MAP[$request->transaction_status] ?? null;
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
