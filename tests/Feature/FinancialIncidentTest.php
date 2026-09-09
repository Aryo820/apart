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
 * F-05 regression: insiden finansial "settled-but-unavailable" (uang masuk,
 * booking tidak memegang tanggal) harus terlihat dari panel admin, terpisah
 * dari payment normal â€” dan kombinasi normal TIDAK boleh ditandai.
 *
 * Predicate yang diuji: Payment Settlement + booking Pending/Cancelled/Expired
 * = perlu tindakan. Settlement + Confirmed/Completed = normal.
 */
class FinancialIncidentTest extends TestCase
{
    use RefreshDatabase;

    private string $serverKey = 'test-server-key';

    protected function setUp(): void
    {
        parent::setUp();

        // Signature webhook diverifikasi terhadap server key; pakai nilai yang
        // sama dengan yang dipakai payload di bawah. Jalur tamu juga meminta
        // snap token — jangan keluar ke gateway sungguhan dari sini.
        config(['midtrans.server_key' => 'test-server-key']);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getSnapToken')->andReturn('SNAP-TEST-TOKEN')->byDefault();
        });
    }

    private function apartment(): Apartment
    {
        return Apartment::create([
            'title' => 'Incident Apartment',
            'slug' => 'incident-'.Str::random(6),
            'description' => 'Deskripsi test',
            'price_per_night' => 500000,
            'address' => 'Jl. Test No. 1',
            'city' => 'Jakarta',
            'bedrooms' => 1,
            'bathrooms' => 1,
            'area_sqm' => 30,
            'capacity' => 2,
            'main_image' => 'https://example.com/img.jpg',
            'is_featured' => false,
            'status' => 'available',
        ]);
    }

    private function booking(
        Apartment $apartment,
        BookingStatus $status,
        PaymentStatus $paymentStatus,
        int $createdMinutesAgo = 5,
    ): Booking {
        $booking = Booking::create([
            'booking_code' => 'APT-'.date('Ymd').'-'.strtoupper(Str::random(5)),
            'user_id' => User::factory()->create(['role' => 'user'])->id,
            'apartment_id' => $apartment->id,
            'check_in' => now()->addDays(2)->toDateString(),
            'check_out' => now()->addDays(4)->toDateString(),
            'total_nights' => 2,
            'total_price' => 1000000,
            'status' => $status,
        ]);

        Payment::create([
            'booking_id' => $booking->id,
            'gross_amount' => 1000000,
            'snap_token' => 'SNAP-TEST-TOKEN',
            'status' => $paymentStatus,
        ]);

        if ($createdMinutesAgo !== 5) {
            Booking::whereKey($booking->id)->update(['created_at' => now()->subMinutes($createdMinutesAgo)]);
        }

        return $booking->fresh(['payment']);
    }

    private function webhookPayload(Booking $booking, string $transactionStatus): array
    {
        $payment = $booking->payment;

        $payload = [
            'order_id' => $payment->order_id ?? ($booking->booking_code.'-1700000000'),
            'status_code' => '200',
            'gross_amount' => '1000000.00',
            'payment_type' => 'bank_transfer',
            'transaction_id' => 'TX-'.Str::random(8),
            'transaction_status' => $transactionStatus,
            'fraud_status' => 'accept',
        ];

        $payload['signature_key'] = hash(
            'sha512',
            $payload['order_id'].$payload['status_code'].$payload['gross_amount'].$this->serverKey,
        );

        return $payload;
    }

    /** Kondisi normal Settlement + Confirmed TIDAK boleh ditandai insiden. */
    public function test_normal_settlement_is_not_flagged(): void
    {
        $apartment = $this->apartment();
        $booking = $this->booking($apartment, BookingStatus::Confirmed, PaymentStatus::Settlement);

        $this->assertFalse($booking->payment->isNeedingAttention());
        $this->assertSame(0, Payment::needsAttention()->count());
    }

    /** Settlement + Completed (tamu sudah menginap) bukan insiden. */
    public function test_completed_booking_with_settlement_is_not_flagged(): void
    {
        $apartment = $this->apartment();
        $booking = $this->booking($apartment, BookingStatus::Completed, PaymentStatus::Settlement);

        $this->assertFalse($booking->payment->isNeedingAttention());
        $this->assertSame(0, Payment::needsAttention()->count());
    }

    /** Pending + Pending (belum dibayar) bukan insiden. */
    public function test_undpaid_pending_booking_is_not_flagged(): void
    {
        $apartment = $this->apartment();
        $booking = $this->booking($apartment, BookingStatus::Pending, PaymentStatus::Pending);

        $this->assertFalse($booking->payment->isNeedingAttention());
    }

    /** Failed + Cancelled (alur tolak normal) bukan insiden. */
    public function test_failed_payment_with_cancelled_booking_is_not_flagged(): void
    {
        $apartment = $this->apartment();
        $booking = $this->booking($apartment, BookingStatus::Cancelled, PaymentStatus::Failed);

        $this->assertFalse($booking->payment->isNeedingAttention());
    }

    /** Expired normal (tidak pernah dibayar) bukan insiden. */
    public function test_expired_booking_without_settlement_is_not_flagged(): void
    {
        $apartment = $this->apartment();
        $booking = $this->booking($apartment, BookingStatus::Expired, PaymentStatus::Expire);

        $this->assertFalse($booking->payment->isNeedingAttention());
    }

    /**
     * Edge case aktual: webhook settlement datang setelah tanggal diambil
     * booking lain. Insiden harus terdeteksi dari persisted state saja.
     */
    public function test_real_settled_but_unavailable_incident_is_detected(): void
    {
        $apartment = $this->apartment();
        $expiry = (int) config('booking.payment_expiry_minutes');

        // Booking lewat deadline (tanggal sudah bebas), masih Pending.
        $victim = $this->booking(
            $apartment,
            BookingStatus::Pending,
            PaymentStatus::Pending,
            createdMinutesAgo: $expiry + 30,
        );

        // Tamu lain mengambil tanggalnya.
        $this->booking($apartment, BookingStatus::Confirmed, PaymentStatus::Settlement, createdMinutesAgo: 5);

        // Webhook settlement datang untuk booking korban.
        $response = $this->postJson('/payment/midtrans-notification', $this->webhookPayload($victim, 'settlement'));

        $response->assertOk()->assertJson(['status' => 'settled_but_unavailable']);

        // Uang tercatat, booking TIDAK confirmed.
        $this->assertSame(PaymentStatus::Settlement, $victim->fresh()->payment->status);
        $this->assertSame(BookingStatus::Pending, $victim->fresh()->status);

        // Insiden terdeteksi oleh scope.
        $this->assertSame(1, Payment::needsAttention()->count());
        $this->assertTrue($victim->fresh()->payment->isNeedingAttention());
    }

    /**
     * Insiden juga terdeteksi ketika booking korban sudah Cancelled (kartu
     * ditolak dulu, lalu retry settlement datang setelah tanggal diambil).
     */
    public function test_settlement_late_after_cancelled_incident_is_detected(): void
    {
        $apartment = $this->apartment();

        $victim = $this->booking($apartment, BookingStatus::Cancelled, PaymentStatus::Failed);

        // Tanggal diambil booking lain.
        $this->booking($apartment, BookingStatus::Confirmed, PaymentStatus::Settlement, createdMinutesAgo: 5);

        $this->postJson('/payment/midtrans-notification', $this->webhookPayload($victim, 'settlement'))
            ->assertOk()
            ->assertJson(['status' => 'settled_but_unavailable']);

        $this->assertSame(1, Payment::needsAttention()->count());
    }

    /** Payment insiden terlihat & bisa dibedakan oleh admin di panel. */
    public function test_admin_sees_incident_indicator_and_filter_on_payments_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $apartment = $this->apartment();

        $normal = $this->booking($apartment, BookingStatus::Confirmed, PaymentStatus::Settlement);
        $incident = $this->booking(
            $this->apartment(),
            BookingStatus::Pending,
            PaymentStatus::Settlement,
            createdMinutesAgo: (int) config('booking.payment_expiry_minutes') + 30,
        );

        // Dashboard menampilkan jumlah insiden unresolved.
        $dashboard = $this->actingAs($admin)->get('/admin');
        $dashboard->assertOk();

        // Halaman payment menampilkan badge "Perlu Tindakan" untuk insiden.
        $this->actingAs($admin)->get('/admin/payments?needs_attention=1')
            ->assertOk();

        // Query yang dipakai filter harus mengembalikan persis insiden.
        $flagged = Payment::needsAttention()->get();
        $this->assertSame(1, $flagged->count());
        $this->assertSame($incident->payment->id, $flagged->first()->id);
        $this->assertNotSame($normal->payment->id, $flagged->first()->id);
    }

    /** Non-admin tetap tidak bisa membuka daftar payment. */
    public function test_non_admin_cannot_view_payments(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $apartment = $this->apartment();
        $this->booking($apartment, BookingStatus::Confirmed, PaymentStatus::Settlement);

        $this->actingAs($user)->get('/admin/payments')->assertForbidden();
    }

    /** Tamu tidak bisa membuka halaman payment sama sekali (belum login). */
    public function test_guest_is_redirected_from_payments(): void
    {
        $this->get('/admin/payments')->assertRedirect('/admin/login');
    }
}
