<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\BookingResource;
use App\Filament\Resources\BookingResource\Pages\CreateBooking;
use App\Filament\Resources\BookingResource\Pages\EditBooking;
use App\Models\Apartment;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Filament\Support\Exceptions\Halt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tanggal, jumlah malam, total biaya, dan nominal pembayaran harus selalu
 * bercerita hal yang sama. Panel admin dulu bisa membuat keempatnya berbeda
 * pendapat: check_out sebelum check_in, 99 malam untuk menginap lima hari, atau
 * reservasi Rp 100.000 yang pembayarannya Rp 2.500.000.
 */
class AdminBookingPricingTest extends TestCase
{
    use RefreshDatabase;

    private const RATE = 500000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function apartment(int $rate = self::RATE, string $title = 'Pricing Apartment'): Apartment
    {
        return Apartment::create([
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(6),
            'description' => 'Deskripsi test',
            'price_per_night' => $rate,
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

    /**
     * Booking dengan angka yang sudah konsisten, plus baris payment opsional
     * yang nominalnya mengikuti total_price — sama seperti yang dibuat
     * BookingController::store.
     */
    private function booking(
        Apartment $apartment,
        int $checkInDays = 10,
        int $checkOutDays = 15,
        BookingStatus $status = BookingStatus::Pending,
        ?PaymentStatus $paymentStatus = null,
    ): Booking {
        $nights = $checkOutDays - $checkInDays;
        $total = $nights * (int) $apartment->price_per_night;

        $booking = Booking::create([
            'booking_code' => 'APT-'.date('Ymd').'-'.strtoupper(Str::random(5)),
            'user_id' => User::factory()->create(['role' => 'user'])->id,
            'apartment_id' => $apartment->id,
            'check_in' => now()->addDays($checkInDays)->toDateString(),
            'check_out' => now()->addDays($checkOutDays)->toDateString(),
            'total_nights' => $nights,
            'total_price' => $total,
            'status' => $status,
        ]);

        if ($paymentStatus) {
            Payment::create([
                'booking_id' => $booking->id,
                'order_id' => Payment::generateOrderId($booking->booking_code),
                'gross_amount' => $total,
                'snap_token' => 'SNAP-TEST-TOKEN',
                'status' => $paymentStatus,
            ]);
        }

        return $booking->fresh(['payment']);
    }

    private function edit(Booking $booking): Testable
    {
        return Livewire::test(EditBooking::class, ['record' => $booking->getKey()]);
    }

    /** @return array<string, mixed> */
    private function createPayload(Apartment $apartment, int $checkInDays, int $checkOutDays): array
    {
        return [
            'user_id' => User::factory()->create(['role' => 'user'])->id,
            'apartment_id' => $apartment->id,
            'check_in' => now()->addDays($checkInDays)->toDateString(),
            'check_out' => now()->addDays($checkOutDays)->toDateString(),
            'status' => BookingStatus::Pending->value,
        ];
    }

    /** Menginap nol malam bukan reservasi. */
    public function test_admin_cannot_save_a_stay_that_ends_on_its_start_date(): void
    {
        $booking = $this->booking($this->apartment());
        $sameDay = now()->addDays(30)->toDateString();

        $this->edit($booking)
            ->fillForm(['check_in' => $sameDay, 'check_out' => $sameDay])
            ->call('save')
            ->assertHasFormErrors(['check_out']);

        $fresh = $booking->fresh();
        $this->assertSame(now()->addDays(10)->toDateString(), $fresh->check_in->toDateString());
        $this->assertSame(5, $fresh->total_nights);
    }

    /** Check-out sebelum check-in ditolak sebelum apa pun tersimpan. */
    public function test_admin_cannot_save_a_reversed_date_range(): void
    {
        $booking = $this->booking($this->apartment());

        $this->edit($booking)
            ->fillForm([
                'check_in' => now()->addDays(30)->toDateString(),
                'check_out' => now()->addDays(25)->toDateString(),
            ])
            ->call('save')
            ->assertHasFormErrors(['check_out']);

        $fresh = $booking->fresh();
        $this->assertSame(now()->addDays(10)->toDateString(), $fresh->check_in->toDateString());
        $this->assertSame(now()->addDays(15)->toDateString(), $fresh->check_out->toDateString());
    }

    /**
     * Lapisan kedua: payload yang tidak lewat validasi form pun ditolak guard,
     * bukan dibulatkan menjadi satu malam oleh Booking::nightsBetween.
     */
    public function test_the_guard_itself_rejects_an_invalid_range(): void
    {
        $apartment = $this->apartment();
        $sameDay = now()->addDays(30)->toDateString();

        $this->expectException(Halt::class);

        BookingResource::guardBookingIntegrity([
            'apartment_id' => $apartment->id,
            'check_in' => $sameDay,
            'check_out' => $sameDay,
            'status' => BookingStatus::Pending->value,
        ]);
    }

    /**
     * Angka yang dikirim admin untuk malam dan biaya tidak pernah menjadi
     * sumber kebenaran — keduanya diturunkan dari tanggal dan tarif unit.
     */
    public function test_submitted_nights_and_price_are_replaced_by_the_derived_values(): void
    {
        $apartment = $this->apartment();

        Livewire::test(CreateBooking::class)
            ->fillForm($this->createPayload($apartment, 20, 25))
            // Kedua field ini disabled di form, jadi nilainya tidak pernah
            // berangkat dari browser; ditulis langsung ke state Livewire supaya
            // yang diuji adalah payload buatan sendiri, bukan tombol yang hilang.
            ->set('data.total_nights', 99)
            ->set('data.total_price', 1)
            ->call('create')
            ->assertHasNoFormErrors();

        $booking = Booking::latest('id')->first();
        $this->assertSame(5, $booking->total_nights);
        $this->assertSame(5 * self::RATE, (int) $booking->total_price);
    }

    /** Memperpanjang menginap menghitung ulang malam dan biayanya. */
    public function test_changing_the_dates_recalculates_nights_and_price(): void
    {
        $booking = $this->booking($this->apartment());

        $this->edit($booking)
            ->fillForm(['check_out' => now()->addDays(20)->toDateString()])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $booking->fresh();
        $this->assertSame(10, $fresh->total_nights);
        $this->assertSame(10 * self::RATE, (int) $fresh->total_price);
    }

    /** Pindah unit memakai tarif unit yang baru, bukan tarif unit lama. */
    public function test_changing_the_apartment_reprices_with_the_new_rate(): void
    {
        $booking = $this->booking($this->apartment());
        $pricier = $this->apartment(750000, 'Unit Mahal');

        $this->edit($booking)
            ->fillForm(['apartment_id' => $pricier->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $booking->fresh();
        $this->assertSame($pricier->id, $fresh->apartment_id);
        $this->assertSame(5, $fresh->total_nights);
        $this->assertSame(5 * 750000, (int) $fresh->total_price);
    }

    /**
     * Inti dari task ini: nilai reservasi tidak boleh menjauh dari nominal yang
     * sudah menjadi sesi pembayaran. Menagih selisih atau mengembalikan dana
     * bukan alur yang ada di project ini, jadi mutasinya ditolak.
     */
    public function test_a_paid_booking_cannot_be_repriced(): void
    {
        $booking = $this->booking(
            $this->apartment(),
            status: BookingStatus::Confirmed,
            paymentStatus: PaymentStatus::Settlement,
        );

        $this->edit($booking)
            ->fillForm(['check_out' => now()->addDays(20)->toDateString()])
            ->call('save')
            ->assertNotified('Perubahan ditolak');

        $fresh = $booking->fresh();
        $this->assertSame(5, $fresh->total_nights);
        $this->assertSame(now()->addDays(15)->toDateString(), $fresh->check_out->toDateString());
        $this->assertSame(
            (float) $booking->payment->gross_amount,
            (float) $fresh->total_price,
            'total_price harus tetap sama dengan payment.gross_amount',
        );
    }

    /**
     * Yang dijaga nominalnya, bukan tanggalnya: memindahkan menginap dengan
     * jumlah malam dan unit yang sama tidak mengubah uang, jadi tetap boleh —
     * dan rentang barunya tetap melewati pemeriksaan konflik P0.
     */
    public function test_a_paid_booking_can_be_moved_when_the_amount_stays_the_same(): void
    {
        $booking = $this->booking(
            $this->apartment(),
            status: BookingStatus::Confirmed,
            paymentStatus: PaymentStatus::Settlement,
        );

        $this->edit($booking)
            ->fillForm([
                'check_in' => now()->addDays(40)->toDateString(),
                'check_out' => now()->addDays(45)->toDateString(),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $booking->fresh();
        $this->assertSame(now()->addDays(40)->toDateString(), $fresh->check_in->toDateString());
        $this->assertSame(5, $fresh->total_nights);
        $this->assertSame((float) $booking->payment->gross_amount, (float) $fresh->total_price);
    }

    /**
     * total_price adalah snapshot tarif saat booking dibuat. Menyunting catatan
     * setelah tarif unit dinaikkan tidak boleh diam-diam menaikkan nilai
     * reservasi lama — itu akan memutus kesetaraannya dengan payment.
     */
    public function test_an_unrelated_edit_does_not_reprice_an_older_booking(): void
    {
        $apartment = $this->apartment();
        $booking = $this->booking($apartment, paymentStatus: PaymentStatus::Pending);

        $apartment->update(['price_per_night' => 900000]);

        $this->edit($booking)
            ->fillForm(['notes' => 'Tamu minta kamar di lantai atas.'])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $booking->fresh();
        $this->assertSame('Tamu minta kamar di lantai atas.', $fresh->notes);
        $this->assertSame(5 * self::RATE, (int) $fresh->total_price);
        $this->assertSame((float) $booking->payment->gross_amount, (float) $fresh->total_price);
    }
}
