<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Apartment;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentCallbackTest extends TestCase
{
    use RefreshDatabase;

    private string $serverKey = 'test-server-key';

    protected function setUp(): void
    {
        parent::setUp();

        config(['midtrans.server_key' => $this->serverKey]);
    }

    private function makePendingBooking(): Booking
    {
        $user = User::factory()->create(['role' => 'user']);
        $apartment = Apartment::create([
            'title' => 'Callback Test Apartment',
            'slug' => 'callback-test-'.Str::random(6),
            'description' => 'Desc',
            'price_per_night' => 500000,
            'address' => 'Jl. Test',
            'city' => 'Jakarta',
            'bedrooms' => 1,
            'bathrooms' => 1,
            'area_sqm' => 30,
            'capacity' => 2,
            'main_image' => 'https://example.com/img.jpg',
            'status' => 'available',
        ]);

        $booking = Booking::create([
            'booking_code' => 'APT-'.date('Ymd').'-'.strtoupper(Str::random(5)),
            'user_id' => $user->id,
            'apartment_id' => $apartment->id,
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(12)->toDateString(),
            'total_nights' => 2,
            'total_price' => 1000000,
            'status' => BookingStatus::Pending,
        ]);

        Payment::create([
            'booking_id' => $booking->id,
            'gross_amount' => 1000000,
            'snap_token' => 'SNAP-TEST',
            'status' => PaymentStatus::Pending,
        ]);

        return $booking;
    }

    private function payload(Booking $booking, string $status, array $overrides = []): array
    {
        $base = array_merge([
            'order_id' => $booking->booking_code.'-1700000000',
            'status_code' => '200',
            'gross_amount' => '1000000.00',
            'transaction_status' => $status,
            'transaction_id' => 'TRX-'.strtoupper(Str::random(10)),
        ], $overrides);

        $base['signature_key'] = hash(
            'sha512',
            $base['order_id'].$base['status_code'].$base['gross_amount'].$this->serverKey
        );

        return $base;
    }

    public function test_callback_rejects_invalid_signature(): void
    {
        $booking = $this->makePendingBooking();
        $data = $this->payload($booking, 'settlement');
        $data['signature_key'] = str_repeat('0', 128);

        $this->postJson('/payment/midtrans-notification', $data)
            ->assertStatus(403);

        $this->assertSame(PaymentStatus::Pending, $booking->payment->fresh()->status);
    }

    public function test_callback_settlement_confirms_booking(): void
    {
        $booking = $this->makePendingBooking();

        $this->postJson('/payment/midtrans-notification', $this->payload($booking, 'settlement'))
            ->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertSame(PaymentStatus::Settlement, $booking->payment->fresh()->status);
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
        $this->assertNotNull($booking->payment->fresh()->transaction_id);
    }

    public function test_callback_is_idempotent_on_final_status(): void
    {
        $booking = $this->makePendingBooking();

        $this->postJson('/payment/midtrans-notification', $this->payload($booking, 'settlement'))->assertStatus(200);
        $this->postJson('/payment/midtrans-notification', $this->payload($booking, 'cancel'))->assertStatus(200);

        $this->assertSame(PaymentStatus::Settlement, $booking->payment->fresh()->status);
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_callback_capture_with_fraud_accept_confirms(): void
    {
        $booking = $this->makePendingBooking();

        $this->postJson('/payment/midtrans-notification', $this->payload($booking, 'capture', [
            'fraud_status' => 'accept',
        ]))->assertStatus(200);

        $this->assertSame(PaymentStatus::Settlement, $booking->payment->fresh()->status);
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_callback_capture_with_fraud_challenge_holds_booking(): void
    {
        $booking = $this->makePendingBooking();

        $this->postJson('/payment/midtrans-notification', $this->payload($booking, 'capture', [
            'fraud_status' => 'challenge',
        ]))->assertStatus(200);

        $this->assertSame(PaymentStatus::Pending, $booking->payment->fresh()->status);
        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
    }

    public function test_callback_capture_with_fraud_deny_fails_booking(): void
    {
        $booking = $this->makePendingBooking();

        $this->postJson('/payment/midtrans-notification', $this->payload($booking, 'capture', [
            'fraud_status' => 'deny',
        ]))->assertStatus(200);

        $this->assertSame(PaymentStatus::Failed, $booking->payment->fresh()->status);
        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
    }

    public function test_callback_rejects_gross_amount_mismatch(): void
    {
        $booking = $this->makePendingBooking();

        $this->postJson('/payment/midtrans-notification', $this->payload($booking, 'settlement', [
            'gross_amount' => '500.00',
        ]))->assertStatus(422);

        $this->assertSame(PaymentStatus::Pending, $booking->payment->fresh()->status);
        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
    }

    public function test_callback_returns_404_for_unknown_booking(): void
    {
        $booking = $this->makePendingBooking();
        $data = $this->payload($booking, 'settlement');
        $data['order_id'] = 'APT-19990101-XXXXX-1700000000';
        $data['signature_key'] = hash(
            'sha512',
            $data['order_id'].$data['status_code'].$data['gross_amount'].$this->serverKey
        );

        $this->postJson('/payment/midtrans-notification', $data)->assertStatus(404);
    }

    public function test_callback_returns_422_for_unhandled_status(): void
    {
        $booking = $this->makePendingBooking();

        $this->postJson('/payment/midtrans-notification', $this->payload($booking, 'unknown_status'))
            ->assertStatus(422);

        $this->assertSame(PaymentStatus::Pending, $booking->payment->fresh()->status);
    }

    public function test_callback_expire_cancels_booking(): void
    {
        $booking = $this->makePendingBooking();

        $this->postJson('/payment/midtrans-notification', $this->payload($booking, 'expire'))
            ->assertStatus(200);

        $this->assertSame(PaymentStatus::Expire, $booking->payment->fresh()->status);
        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
    }
}
