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
 * Lifecycle booking: PENDING -> CONFIRMED (dibayar) / EXPIRED (batas waktu
 * lewat) / CANCELLED (dibatalkan tamu sebelum bayar).
 *
 * Fokusnya pada satu aturan yang mudah rusak: status apa yang mengunci tanggal.
 * Semua skenario di bawah memverifikasi ketersediaan lewat Booking::conflicting
 * karena itu jalur yang sama dipakai endpoint availability dan penyimpanan
 * booking.
 */
class BookingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private const CHECK_IN = 10;

    private const CHECK_OUT = 13;

    private string $serverKey = 'test-server-key';

    protected function setUp(): void
    {
        parent::setUp();

        config(['midtrans.server_key' => $this->serverKey]);

        // Gateway di-mock seperti di ApartmentBookingTest: test ini soal
        // lifecycle, bukan soal HTTP ke Midtrans.
        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getSnapToken')->andReturn('SNAP-TEST-TOKEN');
        });
    }

    /** Payload webhook Midtrans yang tanda tangannya valid. */
    private function webhook(Booking $booking, string $status): array
    {
        $payload = [
            'order_id' => $booking->booking_code.'-1700000000',
            'status_code' => '200',
            'gross_amount' => '1500000.00',
            'transaction_status' => $status,
            'transaction_id' => 'TRX-'.strtoupper(Str::random(10)),
        ];

        $payload['signature_key'] = hash(
            'sha512',
            $payload['order_id'].$payload['status_code'].$payload['gross_amount'].$this->serverKey
        );

        return $payload;
    }

    private function apartment(): Apartment
    {
        return Apartment::create([
            'title' => 'Lifecycle Apartment',
            'slug' => 'lifecycle-'.Str::random(6),
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
        ?User $user = null,
        ?int $createdMinutesAgo = null,
        ?PaymentStatus $paymentStatus = PaymentStatus::Pending,
    ): Booking {
        $booking = Booking::create([
            'booking_code' => 'APT-'.date('Ymd').'-'.strtoupper(Str::random(5)),
            'user_id' => ($user ?? User::factory()->create(['role' => 'user']))->id,
            'apartment_id' => $apartment->id,
            'check_in' => now()->addDays(self::CHECK_IN)->toDateString(),
            'check_out' => now()->addDays(self::CHECK_OUT)->toDateString(),
            'total_nights' => 3,
            'total_price' => 1500000,
            'status' => $status,
        ]);

        if ($paymentStatus) {
            Payment::create([
                'booking_id' => $booking->id,
                'gross_amount' => 1500000,
                'snap_token' => 'SNAP-TEST-TOKEN',
                'status' => $paymentStatus,
            ]);
        }

        if ($createdMinutesAgo !== null) {
            // created_at ditulis langsung: timestamps otomatis akan menimpanya.
            Booking::whereKey($booking->id)->update(['created_at' => now()->subMinutes($createdMinutesAgo)]);
        }

        return $booking->fresh(['payment']);
    }

    private function datesAreTaken(Apartment $apartment): bool
    {
        return Booking::conflicting(
            $apartment->id,
            now()->addDays(self::CHECK_IN)->toDateString(),
            now()->addDays(self::CHECK_OUT)->toDateString(),
        )->exists();
    }

    /** Scenario 1 — booking baru berstatus PENDING dan mengunci tanggal. */
    public function test_new_booking_is_pending_and_blocks_its_dates(): void
    {
        $apartment = $this->apartment();
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)->post('/booking', [
            'apartment_id' => $apartment->id,
            'check_in' => now()->addDays(self::CHECK_IN)->toDateString(),
            'check_out' => now()->addDays(self::CHECK_OUT)->toDateString(),
        ]);

        $booking = Booking::firstWhere('user_id', $user->id);

        $response->assertRedirect(route('bookings.show', $booking->booking_code));
        $this->assertSame(BookingStatus::Pending, $booking->status);
        $this->assertTrue($this->datesAreTaken($apartment));
    }

    /** Scenario 2 — dibayar sebelum deadline: webhook membuat booking CONFIRMED. */
    public function test_payment_before_deadline_confirms_the_booking(): void
    {
        $apartment = $this->apartment();
        $booking = $this->booking($apartment, BookingStatus::Pending, createdMinutesAgo: 5);

        $this->postJson('/payment/midtrans-notification', $this->webhook($booking, 'settlement'))
            ->assertOk();

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
        $this->assertSame(PaymentStatus::Settlement, $booking->payment->fresh()->status);
        $this->assertTrue($this->datesAreTaken($apartment));
    }

    /** Scenario 3 — tidak dibayar sampai deadline: command menandai EXPIRED. */
    public function test_command_expires_pending_bookings_past_the_deadline(): void
    {
        $apartment = $this->apartment();
        $expiry = (int) config('booking.payment_expiry_minutes');

        $stale = $this->booking($apartment, BookingStatus::Pending, createdMinutesAgo: $expiry + 1);
        $fresh = $this->booking($this->apartment(), BookingStatus::Pending, createdMinutesAgo: 5);

        $this->artisan('bookings:expire-pending')->assertSuccessful();

        $this->assertSame(BookingStatus::Expired, $stale->fresh()->status);
        $this->assertSame(PaymentStatus::Expire, $stale->payment->fresh()->status);

        // Yang belum lewat deadline tidak boleh tersentuh.
        $this->assertSame(BookingStatus::Pending, $fresh->fresh()->status);
        $this->assertSame(PaymentStatus::Pending, $fresh->payment->fresh()->status);
    }

    /** Scenario 3b — command idempotent: jalan dua kali tidak merusak apa pun. */
    public function test_expire_command_is_idempotent(): void
    {
        $apartment = $this->apartment();
        $expiry = (int) config('booking.payment_expiry_minutes');
        $booking = $this->booking($apartment, BookingStatus::Pending, createdMinutesAgo: $expiry + 1);

        $this->artisan('bookings:expire-pending')->assertSuccessful();
        $firstPass = $booking->fresh();

        $this->artisan('bookings:expire-pending')->expectsOutputToContain('Booking expired: 0');

        $this->assertSame(BookingStatus::Expired, $booking->fresh()->status);
        $this->assertEquals($firstPass->updated_at, $booking->fresh()->updated_at);
    }

    /** Scenario 3c — pembayaran yang sudah settle tidak boleh ikut di-expire. */
    public function test_expire_command_never_overwrites_a_settled_payment(): void
    {
        $apartment = $this->apartment();
        $expiry = (int) config('booking.payment_expiry_minutes');

        // Pending di DB tapi pembayarannya sudah settle (webhook menyusul).
        $booking = $this->booking(
            $apartment,
            BookingStatus::Pending,
            createdMinutesAgo: $expiry + 1,
            paymentStatus: PaymentStatus::Settlement,
        );

        $this->artisan('bookings:expire-pending')->assertSuccessful();

        // Status pembayaran final milik gateway — tidak boleh ditulis ulang.
        $this->assertSame(PaymentStatus::Settlement, $booking->payment->fresh()->status);
    }

    /**
     * Inti perbaikan ini: Pending yang sudah lewat deadline TIDAK mengunci
     * tanggal, bahkan sebelum command perapih status dijalankan. Kalau tidak,
     * ketersediaan bergantung pada cadence scheduler.
     */
    public function test_overdue_pending_stops_blocking_before_the_command_runs(): void
    {
        $apartment = $this->apartment();
        $expiry = (int) config('booking.payment_expiry_minutes');

        $this->booking($apartment, BookingStatus::Pending, createdMinutesAgo: $expiry + 1);

        // Statusnya masih 'pending' di DB, command belum jalan.
        $this->assertDatabaseHas('bookings', ['apartment_id' => $apartment->id, 'status' => 'pending']);
        $this->assertFalse($this->datesAreTaken($apartment));
    }

    /** Pending yang masih dalam masa pembayaran tetap mengunci tanggal. */
    public function test_active_pending_still_blocks_its_dates(): void
    {
        $apartment = $this->apartment();
        $this->booking($apartment, BookingStatus::Pending, createdMinutesAgo: 5);

        $this->assertTrue($this->datesAreTaken($apartment));
    }

    /** Scenario 4 & 5 — EXPIRED dan CANCELLED melepas tanggal; CONFIRMED tidak. */
    public function test_only_confirmed_and_active_pending_block_dates(): void
    {
        foreach ([
            [BookingStatus::Expired, false],
            [BookingStatus::Cancelled, false],
            [BookingStatus::Completed, false],
            [BookingStatus::Confirmed, true],
        ] as [$status, $shouldBlock]) {
            $apartment = $this->apartment();
            $this->booking($apartment, $status, createdMinutesAgo: 5);

            $this->assertSame(
                $shouldBlock,
                $this->datesAreTaken($apartment),
                "Status {$status->value} seharusnya ".($shouldBlock ? 'mengunci' : 'melepas').' tanggal',
            );
        }
    }

    /** Scenario 4 — setelah expired, tanggal yang sama bisa dipesan ulang. */
    public function test_dates_can_be_rebooked_after_expiry(): void
    {
        $apartment = $this->apartment();
        $expiry = (int) config('booking.payment_expiry_minutes');
        $this->booking($apartment, BookingStatus::Pending, createdMinutesAgo: $expiry + 1);

        $this->artisan('bookings:expire-pending')->assertSuccessful();

        $newGuest = User::factory()->create(['role' => 'user']);

        $this->actingAs($newGuest)->post('/booking', [
            'apartment_id' => $apartment->id,
            'check_in' => now()->addDays(self::CHECK_IN)->toDateString(),
            'check_out' => now()->addDays(self::CHECK_OUT)->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            BookingStatus::Pending,
            Booking::firstWhere('user_id', $newGuest->id)->status,
        );
    }

    /** Scenario 6 — server menolak booking kedua pada tanggal yang sama. */
    public function test_server_rejects_a_second_booking_on_the_same_dates(): void
    {
        $apartment = $this->apartment();
        $this->booking($apartment, BookingStatus::Confirmed, createdMinutesAgo: 5);

        $rival = User::factory()->create(['role' => 'user']);

        $this->actingAs($rival)->post('/booking', [
            'apartment_id' => $apartment->id,
            'check_in' => now()->addDays(self::CHECK_IN)->toDateString(),
            'check_out' => now()->addDays(self::CHECK_OUT)->toDateString(),
        ])->assertSessionHasErrors('check_in');

        $this->assertNull(Booking::firstWhere('user_id', $rival->id));
    }

    /** Scenario 7 — webhook yang sama dua kali tidak merusak state. */
    public function test_duplicate_settlement_webhook_is_processed_once(): void
    {
        $apartment = $this->apartment();
        $booking = $this->booking($apartment, BookingStatus::Pending, createdMinutesAgo: 5);
        $payload = $this->webhook($booking, 'settlement');

        $this->postJson('/payment/midtrans-notification', $payload)->assertOk();
        $afterFirst = $booking->payment->fresh();

        $this->postJson('/payment/midtrans-notification', $payload)
            ->assertOk()
            ->assertJson(['status' => 'already_processed']);

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
        $this->assertEquals($afterFirst->updated_at, $booking->payment->fresh()->updated_at);
    }

    /**
     * Webhook terlambat tidak boleh menimpa status final. Pembatalan oleh tamu
     * tetap 'cancelled' walaupun Midtrans menyusul mengirim 'expire'.
     */
    public function test_late_expire_webhook_does_not_overwrite_a_guest_cancellation(): void
    {
        $apartment = $this->apartment();
        $booking = $this->booking($apartment, BookingStatus::Cancelled, createdMinutesAgo: 5);

        $this->postJson('/payment/midtrans-notification', $this->webhook($booking, 'expire'))->assertOk();

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
    }

    /**
     * Pembayaran settle untuk booking yang sudah expired: dihidupkan kembali
     * kalau tanggalnya masih kosong, TAPI tidak kalau sudah diambil tamu lain —
     * itu akan membuat dua reservasi terkonfirmasi pada kamar dan tanggal sama.
     */
    public function test_late_settlement_reinstates_only_when_dates_are_still_free(): void
    {
        $free = $this->apartment();
        $expired = $this->booking($free, BookingStatus::Expired, createdMinutesAgo: 5);

        $this->postJson('/payment/midtrans-notification', $this->webhook($expired, 'settlement'))->assertOk();
        $this->assertSame(BookingStatus::Confirmed, $expired->fresh()->status);

        $taken = $this->apartment();
        $loser = $this->booking($taken, BookingStatus::Expired, createdMinutesAgo: 5);
        $this->booking($taken, BookingStatus::Confirmed, createdMinutesAgo: 5);

        $this->postJson('/payment/midtrans-notification', $this->webhook($loser, 'settlement'))
            ->assertOk()
            ->assertJson(['status' => 'settled_but_unavailable']);

        // Statusnya dibiarkan; pembayarannya tercatat untuk refund manual.
        $this->assertSame(BookingStatus::Expired, $loser->fresh()->status);
        $this->assertSame(PaymentStatus::Settlement, $loser->payment->fresh()->status);
    }

    /**
     * P0-1 — settlement yang datang setelah batas waktu pembayaran, ketika baris
     * booking MASIH 'pending' karena bookings:expire-pending belum berjalan.
     * Tanggalnya sudah dibebaskan scopeBlocking dan sudah diambil tamu lain,
     * jadi booking ini tidak boleh ikut menjadi Confirmed.
     */
    public function test_delayed_settlement_on_a_still_pending_booking_does_not_double_book(): void
    {
        $apartment = $this->apartment();
        $overdueMinutes = (int) config('booking.payment_expiry_minutes') + 30;

        // Lewat deadline, tapi statusnya belum dirapikan command.
        $late = $this->booking($apartment, BookingStatus::Pending, createdMinutesAgo: $overdueMinutes);
        $this->assertTrue($late->isPaymentOverdue());
        $this->assertFalse($this->datesAreTaken($apartment), 'tanggal harus sudah bebas sebelum tamu lain masuk');

        $rival = $this->booking($apartment, BookingStatus::Confirmed, createdMinutesAgo: 5);

        $this->postJson('/payment/midtrans-notification', $this->webhook($late, 'settlement'))
            ->assertOk()
            ->assertJson(['status' => 'settled_but_unavailable']);

        $this->assertSame(BookingStatus::Pending, $late->fresh()->status);
        // Pembayarannya tetap tercatat — uangnya nyata, tindak lanjutnya manual.
        $this->assertSame(PaymentStatus::Settlement, $late->payment->fresh()->status);
        $this->assertSame(BookingStatus::Confirmed, $rival->fresh()->status);

        // Inti aturannya: tidak boleh ada DUA Confirmed pada unit + rentang sama.
        $this->assertSame(1, Booking::where('apartment_id', $apartment->id)
            ->where('status', BookingStatus::Confirmed->value)
            ->count());
    }

    /** Kebalikannya: kalau tanggalnya masih bebas, settlement terlambat tetap dihormati. */
    public function test_delayed_settlement_still_confirms_when_nobody_took_the_dates(): void
    {
        $apartment = $this->apartment();
        $late = $this->booking(
            $apartment,
            BookingStatus::Pending,
            createdMinutesAgo: (int) config('booking.payment_expiry_minutes') + 30,
        );

        $this->postJson('/payment/midtrans-notification', $this->webhook($late, 'settlement'))
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertSame(BookingStatus::Confirmed, $late->fresh()->status);
        $this->assertSame(PaymentStatus::Settlement, $late->payment->fresh()->status);
    }

    /**
     * P0-2 — kartu ditolak (payment 'failed' + booking 'cancelled'), lalu tamu
     * mencoba metode lain pada order_id yang sama dan berhasil. Settlement itu
     * harus diproses: uangnya nyata.
     */
    public function test_settlement_after_a_failed_attempt_confirms_the_booking(): void
    {
        $apartment = $this->apartment();
        $booking = $this->booking(
            $apartment,
            BookingStatus::Cancelled,
            createdMinutesAgo: 5,
            paymentStatus: PaymentStatus::Failed,
        );

        $this->postJson('/payment/midtrans-notification', $this->webhook($booking, 'settlement'))
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertSame(PaymentStatus::Settlement, $booking->payment->fresh()->status);
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    /** P0-2 tidak boleh mengalahkan P0-1: pemulihannya tetap lewat cek konflik. */
    public function test_settlement_after_a_failed_attempt_does_not_steal_taken_dates(): void
    {
        $apartment = $this->apartment();
        $booking = $this->booking(
            $apartment,
            BookingStatus::Cancelled,
            createdMinutesAgo: 5,
            paymentStatus: PaymentStatus::Failed,
        );
        $this->booking($apartment, BookingStatus::Confirmed, createdMinutesAgo: 5);

        $this->postJson('/payment/midtrans-notification', $this->webhook($booking, 'settlement'))
            ->assertOk()
            ->assertJson(['status' => 'settled_but_unavailable']);

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertSame(PaymentStatus::Settlement, $booking->payment->fresh()->status);
    }

    /**
     * Hanya pembayaran BERHASIL yang boleh membuka sesi yang sudah ditutup:
     * notifikasi 'expire' yang menyusul setelah 'failed' tetap diabaikan.
     */
    public function test_non_settlement_webhook_on_a_closed_payment_is_ignored(): void
    {
        $apartment = $this->apartment();
        $booking = $this->booking(
            $apartment,
            BookingStatus::Cancelled,
            createdMinutesAgo: 5,
            paymentStatus: PaymentStatus::Failed,
        );

        $this->postJson('/payment/midtrans-notification', $this->webhook($booking, 'expire'))
            ->assertOk()
            ->assertJson(['status' => 'already_processed']);

        $this->assertSame(PaymentStatus::Failed, $booking->payment->fresh()->status);
        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
    }

    /** Scenario 5 — tamu membatalkan booking yang belum dibayar. */
    public function test_owner_can_cancel_an_unpaid_pending_booking(): void
    {
        $apartment = $this->apartment();
        $owner = User::factory()->create(['role' => 'user']);
        $booking = $this->booking($apartment, BookingStatus::Pending, user: $owner, createdMinutesAgo: 5);

        $this->actingAs($owner)
            ->delete(route('bookings.cancel', $booking->booking_code))
            ->assertRedirect(route('bookings.show', $booking->booking_code))
            ->assertSessionHas('success');

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        // Sesi pembayaran ikut ditutup supaya tidak bisa dibayar setelah dibatalkan.
        $this->assertSame(PaymentStatus::Cancel, $booking->payment->fresh()->status);
        $this->assertFalse($this->datesAreTaken($apartment));
    }

    /** Booking milik orang lain tidak bisa dibatalkan. */
    public function test_cancellation_is_blocked_for_non_owners(): void
    {
        $booking = $this->booking($this->apartment(), BookingStatus::Pending, createdMinutesAgo: 5);
        $intruder = User::factory()->create(['role' => 'user']);

        $this->actingAs($intruder)
            ->delete(route('bookings.cancel', $booking->booking_code))
            ->assertForbidden();

        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
    }

    /**
     * Booking yang sudah dibayar TIDAK bisa dibatalkan sendiri: itu memunculkan
     * pertanyaan refund, dan aturannya belum ditetapkan di project ini.
     */
    public function test_paid_booking_cannot_be_cancelled_by_the_guest(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $booking = $this->booking(
            $this->apartment(),
            BookingStatus::Confirmed,
            user: $owner,
            createdMinutesAgo: 5,
            paymentStatus: PaymentStatus::Settlement,
        );

        $this->actingAs($owner)
            ->delete(route('bookings.cancel', $booking->booking_code))
            ->assertForbidden();

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    /** Pending yang sudah lewat deadline: jalurnya expired, bukan cancel. */
    public function test_overdue_pending_cannot_be_cancelled(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $expiry = (int) config('booking.payment_expiry_minutes');
        $booking = $this->booking(
            $this->apartment(),
            BookingStatus::Pending,
            user: $owner,
            createdMinutesAgo: $expiry + 1,
        );

        $this->actingAs($owner)
            ->delete(route('bookings.cancel', $booking->booking_code))
            ->assertForbidden();
    }

    /** Mutasi status tidak boleh bisa dipicu lewat GET. */
    public function test_cancellation_is_not_reachable_by_get(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $booking = $this->booking($this->apartment(), BookingStatus::Pending, user: $owner, createdMinutesAgo: 5);

        $this->actingAs($owner)
            ->get('/booking/'.$booking->booking_code.'/cancel')
            ->assertStatus(405);

        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
    }

    public function test_cancellation_requires_authentication(): void
    {
        $booking = $this->booking($this->apartment(), BookingStatus::Pending, createdMinutesAgo: 5);

        $this->delete(route('bookings.cancel', $booking->booking_code))
            ->assertRedirect(route('login'));

        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
    }

    /** STEP 6 — tiap status menampilkan blok yang benar di halaman reservasi. */
    public function test_detail_page_shows_the_deadline_and_cancel_option_while_pending(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $booking = $this->booking($this->apartment(), BookingStatus::Pending, user: $owner, createdMinutesAgo: 5);

        $this->actingAs($owner)->get('/booking/'.$booking->booking_code)
            ->assertOk()
            ->assertSee('Menunggu pembayaran')
            ->assertSee('Batas waktu pembayaran')
            ->assertSee($booking->paymentDeadlineAt()->translatedFormat('l, d F Y'))
            ->assertSee('otomatis')
            ->assertSee('Bayar Sekarang')
            ->assertSee('Batalkan reservasi')
            ->assertSee('Butuh bantuan?');
    }

    public function test_detail_page_shows_the_expired_block_for_an_overdue_pending_booking(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $expiry = (int) config('booking.payment_expiry_minutes');
        $booking = $this->booking(
            $this->apartment(),
            BookingStatus::Pending,
            user: $owner,
            createdMinutesAgo: $expiry + 1,
        );

        $this->actingAs($owner)->get('/booking/'.$booking->booking_code)
            ->assertOk()
            ->assertSee('Reservasi kedaluwarsa')
            ->assertSee('Tidak ada tagihan')
            ->assertSee('Pesan ulang unit ini')
            // tidak boleh menawarkan bayar/cancel untuk tanggal yang sudah bebas
            ->assertDontSee('Bayar Sekarang')
            ->assertDontSee('Batalkan reservasi');
    }

    /**
     * Keadaan yang lahir dari guard P0-1: pembayaran tercatat tapi reservasi
     * tidak bisa dikonfirmasi. Halaman TIDAK boleh mengklaim "tidak ada
     * tagihan" di sini — uangnya sudah masuk.
     */
    public function test_detail_page_does_not_claim_no_charge_when_payment_settled_without_confirmation(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $apartment = $this->apartment();
        $booking = $this->booking(
            $apartment,
            BookingStatus::Expired,
            user: $owner,
            createdMinutesAgo: 5,
            paymentStatus: PaymentStatus::Settlement,
        );

        $this->actingAs($owner)->get('/booking/'.$booking->booking_code)
            ->assertOk()
            ->assertSee('Pembayaran diterima, reservasi perlu ditinjau')
            ->assertSee('Hubungi bantuan')
            ->assertDontSee('Tidak ada tagihan')
            ->assertDontSee('Bayar Sekarang');
    }

    public function test_detail_page_distinguishes_expired_from_cancelled(): void
    {
        $owner = User::factory()->create(['role' => 'user']);

        $expired = $this->booking($this->apartment(), BookingStatus::Expired, user: $owner, createdMinutesAgo: 5);
        $this->actingAs($owner)->get('/booking/'.$expired->booking_code)
            ->assertOk()
            ->assertSee('Reservasi kedaluwarsa')
            ->assertDontSee('Reservasi dibatalkan');

        $cancelled = $this->booking($this->apartment(), BookingStatus::Cancelled, user: $owner, createdMinutesAgo: 5);
        $this->actingAs($owner)->get('/booking/'.$cancelled->booking_code)
            ->assertOk()
            ->assertSee('Reservasi dibatalkan')
            ->assertDontSee('Reservasi kedaluwarsa');
    }

    public function test_confirmed_booking_offers_no_self_service_cancellation(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $booking = $this->booking(
            $this->apartment(),
            BookingStatus::Confirmed,
            user: $owner,
            createdMinutesAgo: 5,
            paymentStatus: PaymentStatus::Settlement,
        );

        $this->actingAs($owner)->get('/booking/'.$booking->booking_code)
            ->assertOk()
            ->assertSee('Reservasi terkonfirmasi')
            ->assertDontSee('Batalkan reservasi')
            ->assertDontSee('Batas waktu pembayaran');
    }
}
