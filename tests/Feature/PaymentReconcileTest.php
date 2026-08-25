<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Apartment;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Rekonsiliasi status saat tamu kembali dari Snap.
 *
 * Skenario yang dijaga: tamu membayar, webhook Midtrans belum sampai (tertunda,
 * hilang, atau — di localhost — tidak bisa menjangkau server kita), lalu tamu
 * kembali ke halaman reservasi. Tanpa jalur ini halaman masih berbunyi
 * "menunggu pembayaran" lengkap dengan tombol bayar yang mengundang bayar dua
 * kali.
 */
class PaymentReconcileTest extends TestCase
{
    use RefreshDatabase;

    /** Penanda script rekonsiliasi otomatis di bookings/show.blade.php. */
    private const AUTO_SUBMIT = "document.getElementById('reconcileForm')?.requestSubmit();";

    private function booking(BookingStatus $status = BookingStatus::Pending, PaymentStatus $paymentStatus = PaymentStatus::Pending): Booking
    {
        $apartment = Apartment::create([
            'title' => 'Reconcile Test Apartment',
            'slug' => 'reconcile-test-'.Str::random(6),
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
            'user_id' => User::factory()->create(['role' => 'user'])->id,
            'apartment_id' => $apartment->id,
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(12)->toDateString(),
            'total_nights' => 2,
            'total_price' => 1000000,
            'status' => $status,
        ]);

        Payment::create([
            'booking_id' => $booking->id,
            'order_id' => $booking->booking_code.'-1700000000',
            'gross_amount' => 1000000,
            'snap_token' => 'SNAP-TEST',
            'status' => $paymentStatus,
        ]);

        return $booking->load('payment');
    }

    /** @param  array<string, mixed>|\Throwable  $answer */
    private function fakeGateway(array|\Throwable $answer): void
    {
        $this->mock(MidtransService::class, function (MockInterface $mock) use ($answer) {
            $expectation = $mock->shouldReceive('getTransactionStatus')->once();

            $answer instanceof \Throwable
                ? $expectation->andThrow($answer)
                : $expectation->andReturn($answer);

            $mock->shouldReceive('getSnapToken')->andReturn('SNAP-TEST');
        });
    }

    /** @return array<string, mixed> Bentuknya sama dengan Transaction::status. */
    private function gatewaySays(string $status, string $amount = '1000000.00'): array
    {
        return [
            'order_id' => 'ignored-by-controller',
            'transaction_status' => $status,
            'transaction_id' => 'TRX-'.strtoupper(Str::random(10)),
            'payment_type' => 'bank_transfer',
            'gross_amount' => $amount,
        ];
    }

    public function test_reconcile_confirms_booking_when_gateway_reports_settlement(): void
    {
        $booking = $this->booking();
        $this->fakeGateway($this->gatewaySays('settlement'));

        $this->actingAs($booking->user)
            ->post("/booking/{$booking->booking_code}/reconcile")
            ->assertRedirect(route('bookings.show', $booking->booking_code))
            ->assertSessionHas('success');

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
        $this->assertSame(PaymentStatus::Settlement, $booking->payment->fresh()->status);
    }

    /**
     * Nominal tetap diperiksa walau payload-nya kita minta sendiri: satu-satunya
     * penulis status adalah PaymentStatusApplier, guard-nya berlaku sama.
     */
    public function test_reconcile_rejects_amount_mismatch(): void
    {
        $booking = $this->booking();
        $this->fakeGateway($this->gatewaySays('settlement', '500.00'));

        $this->actingAs($booking->user)->post("/booking/{$booking->booking_code}/reconcile");

        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
        $this->assertSame(PaymentStatus::Pending, $booking->payment->fresh()->status);
    }

    /**
     * Gateway tidak terjangkau: status dibiarkan apa adanya dan tamu diarahkan
     * ke route-nya, BUKAN back() — halaman ini memicu rekonsiliasi otomatis, dan
     * back() ke URL ?from=midtrans akan berputar terus.
     */
    public function test_reconcile_survives_gateway_failure_without_redirect_loop(): void
    {
        $booking = $this->booking();
        $this->fakeGateway(new \RuntimeException('gateway down'));

        $this->actingAs($booking->user)
            ->post("/booking/{$booking->booking_code}/reconcile", [], ['referer' => route('bookings.show', [$booking->booking_code, 'from' => 'midtrans'])])
            ->assertRedirect(route('bookings.show', $booking->booking_code))
            ->assertSessionHas('status');

        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
    }

    public function test_reconcile_is_denied_for_other_users(): void
    {
        $booking = $this->booking();
        $intruder = User::factory()->create(['role' => 'user']);

        $this->actingAs($intruder)
            ->post("/booking/{$booking->booking_code}/reconcile")
            ->assertForbidden();

        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
    }

    /**
     * Tombolnya native (form="reconcileForm"), jadi form-nya harus benar-benar
     * ada di halaman — pernah hilang dan membuat tombolnya tidak melakukan apa pun.
     */
    public function test_booking_page_renders_the_reconcile_form(): void
    {
        $booking = $this->booking();

        $this->actingAs($booking->user)
            ->get("/booking/{$booking->booking_code}")
            ->assertOk()
            ->assertSee('id="reconcileForm"', false)
            ->assertSee(route('bookings.reconcile', $booking->booking_code), false)
            ->assertDontSee(self::AUTO_SUBMIT, false);
    }

    public function test_returning_from_midtrans_triggers_reconcile_automatically(): void
    {
        $booking = $this->booking();

        $this->actingAs($booking->user)
            ->get("/booking/{$booking->booking_code}?from=midtrans")
            ->assertOk()
            ->assertSee(self::AUTO_SUBMIT, false);
    }

    /** Sudah lunas: tidak ada yang bisa basi, jadi API Midtrans tidak dipanggil. */
    public function test_settled_booking_does_not_re_check_the_gateway(): void
    {
        $booking = $this->booking(BookingStatus::Confirmed, PaymentStatus::Settlement);

        $this->actingAs($booking->user)
            ->get("/booking/{$booking->booking_code}?from=midtrans")
            ->assertOk()
            ->assertDontSee(self::AUTO_SUBMIT, false);
    }
}
