<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Filament\Resources\BookingResource\Pages\CreateBooking;
use App\Filament\Resources\BookingResource\Pages\EditBooking;
use App\Models\Apartment;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Panel admin tidak boleh menjadi jalan pintas terhadap invariant yang dijaga
 * jalur tamu dan jalur pembayaran: dua booking tidak boleh memblokir tanggal
 * yang sama pada unit yang sama, dan status tidak boleh berpindah ke arah yang
 * tidak pernah terjadi di domain.
 *
 * Semua skenario di bawah menembak halaman Filament yang sesungguhnya (Livewire),
 * bukan model, karena yang diaudit justru jalur tulis panel itu sendiri.
 */
class AdminBookingIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function apartment(string $title = 'Admin Apartment'): Apartment
    {
        return Apartment::create([
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(6),
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
        int $checkInDays = 10,
        int $checkOutDays = 15,
    ): Booking {
        return Booking::create([
            'booking_code' => 'APT-'.date('Ymd').'-'.strtoupper(Str::random(5)),
            'user_id' => User::factory()->create(['role' => 'user'])->id,
            'apartment_id' => $apartment->id,
            'check_in' => now()->addDays($checkInDays)->toDateString(),
            'check_out' => now()->addDays($checkOutDays)->toDateString(),
            'total_nights' => $checkOutDays - $checkInDays,
            'total_price' => 500000 * ($checkOutDays - $checkInDays),
            'status' => $status,
        ]);
    }

    /** Form edit Filament untuk sebuah booking, sudah terisi dari record-nya. */
    private function edit(Booking $booking): Testable
    {
        return Livewire::test(EditBooking::class, ['record' => $booking->getKey()]);
    }

    /**
     * Test 1 — booking yang bertabrakan tidak bisa dijadikan Confirmed.
     *
     * Ini kasus yang paling mudah terjadi di meja operasional: tamu menelepon
     * karena reservasinya kedaluwarsa, dan admin "menghidupkannya kembali"
     * padahal tanggalnya sudah diambil orang lain.
     */
    public function test_admin_cannot_confirm_a_booking_that_conflicts_with_a_confirmed_one(): void
    {
        $apartment = $this->apartment();
        $rival = $this->booking($apartment, BookingStatus::Confirmed);
        $booking = $this->booking($apartment, BookingStatus::Expired);

        $this->edit($booking)
            ->fillForm(['status' => BookingStatus::Confirmed->value])
            ->call('save')
            ->assertNotified('Perubahan ditolak');

        $this->assertSame(BookingStatus::Expired, $booking->fresh()->status);
        $this->assertSame(
            1,
            Booking::where('apartment_id', $apartment->id)
                ->where('status', BookingStatus::Confirmed->value)
                ->count()
        );
        $this->assertSame(BookingStatus::Confirmed, $rival->fresh()->status);
    }

    /**
     * Test 2 — tanpa tabrakan, admin tetap boleh mengonfirmasi.
     *
     * Guard-nya soal tanggal, bukan soal pembayaran: tidak ada syarat pembayaran
     * baru yang ditambahkan di sini karena domain memang tidak mensyaratkannya
     * untuk booking yang dibuat/diselesaikan di luar Snap.
     */
    public function test_admin_can_confirm_a_booking_that_does_not_conflict(): void
    {
        $apartment = $this->apartment();
        $this->booking($apartment, BookingStatus::Confirmed, 10, 15);
        $booking = $this->booking($apartment, BookingStatus::Expired, 20, 25);

        $this->edit($booking)
            ->fillForm(['status' => BookingStatus::Confirmed->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    /**
     * Test 3 — booking Confirmed tidak bisa DIPINDAHKAN ke rentang yang terisi.
     *
     * Status tidak berubah sama sekali di sini; yang berubah unit dan tanggalnya.
     * Tanpa guard, ini jalur double booking kedua yang tidak lewat dropdown
     * status sama sekali.
     */
    public function test_admin_cannot_move_a_confirmed_booking_into_a_conflicting_range(): void
    {
        $taken = $this->apartment('Unit Terisi');
        $free = $this->apartment('Unit Kosong');
        $this->booking($taken, BookingStatus::Confirmed, 10, 15);
        $booking = $this->booking($free, BookingStatus::Confirmed, 20, 25);

        $this->edit($booking)
            ->fillForm([
                'apartment_id' => $taken->id,
                'check_in' => now()->addDays(10)->toDateString(),
                'check_out' => now()->addDays(15)->toDateString(),
            ])
            ->call('save')
            ->assertNotified('Perubahan ditolak');

        $fresh = $booking->fresh();
        $this->assertSame($free->id, $fresh->apartment_id);
        $this->assertSame(now()->addDays(20)->toDateString(), $fresh->check_in->toDateString());
        $this->assertSame(now()->addDays(25)->toDateString(), $fresh->check_out->toDateString());
    }

    /**
     * Test 4 — transisi yang tidak pernah terjadi di domain ditolak.
     *
     * PaymentStatusApplier hanya menimpa booking yang masih Pending, dan tamu
     * mendapat 403 saat mencoba membatalkan booking Confirmed. Panel sekarang
     * memakai batas yang sama.
     */
    public function test_admin_cannot_reverse_a_confirmed_booking(): void
    {
        $apartment = $this->apartment();

        foreach ([BookingStatus::Pending, BookingStatus::Cancelled, BookingStatus::Expired] as $target) {
            $booking = $this->booking($apartment, BookingStatus::Confirmed, 40, 45);

            $this->edit($booking)
                ->fillForm(['status' => $target->value])
                ->call('save')
                ->assertNotified('Perubahan ditolak');

            $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);

            $booking->delete();
        }
    }

    /**
     * Test 5 — penolakan tidak menyimpan sebagian data.
     *
     * Satu submit yang mengubah status, unit, dan kedua tanggal sekaligus: bila
     * transaksinya tidak utuh, sebagian kolom akan lolos tersimpan.
     */
    public function test_a_rejected_mutation_leaves_every_field_untouched(): void
    {
        $taken = $this->apartment('Unit Terisi');
        $free = $this->apartment('Unit Kosong');
        $this->booking($taken, BookingStatus::Confirmed, 10, 15);
        $booking = $this->booking($free, BookingStatus::Expired, 20, 25);

        $before = $booking->only(['status', 'apartment_id', 'check_in', 'check_out', 'notes']);

        $this->edit($booking)
            ->fillForm([
                'status' => BookingStatus::Confirmed->value,
                'apartment_id' => $taken->id,
                'check_in' => now()->addDays(10)->toDateString(),
                'check_out' => now()->addDays(15)->toDateString(),
                'notes' => 'Dipindahkan lewat panel admin.',
            ])
            ->call('save')
            ->assertNotified('Perubahan ditolak');

        $this->assertEquals($before, $booking->fresh()->only(array_keys($before)));
    }

    /** Create dari panel tidak boleh menabrak booking yang sudah memblokir. */
    public function test_admin_cannot_create_a_booking_on_dates_already_taken(): void
    {
        $apartment = $this->apartment();
        $this->booking($apartment, BookingStatus::Confirmed, 10, 15);

        Livewire::test(CreateBooking::class)
            ->fillForm($this->createPayload($apartment, BookingStatus::Confirmed, 12, 14))
            ->call('create')
            ->assertNotified('Perubahan ditolak');

        $this->assertSame(1, Booking::count());
    }

    /** Tanggal yang bebas tetap bisa dibuat, lengkap dengan booking_code. */
    public function test_admin_can_create_a_booking_on_free_dates(): void
    {
        $apartment = $this->apartment();
        $this->booking($apartment, BookingStatus::Confirmed, 10, 15);

        Livewire::test(CreateBooking::class)
            ->fillForm($this->createPayload($apartment, BookingStatus::Confirmed, 20, 25))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2, Booking::count());
        $this->assertNotNull(Booking::latest('id')->first()->booking_code);
    }

    /**
     * Guard tidak boleh menghalangi pekerjaan admin yang sah: menyimpan catatan
     * pada booking Confirmed bukan transisi dan bukan konflik dengan dirinya
     * sendiri.
     */
    public function test_admin_can_still_edit_a_confirmed_booking_without_changing_its_status(): void
    {
        $booking = $this->booking($this->apartment(), BookingStatus::Confirmed);

        $this->edit($booking)
            ->fillForm(['notes' => 'Tamu meminta late check-in.'])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $booking->fresh();
        $this->assertSame('Tamu meminta late check-in.', $fresh->notes);
        $this->assertSame(BookingStatus::Confirmed, $fresh->status);
    }

    /**
     * Transisi yang memang terjadi di domain tetap terbuka untuk admin: booking
     * Pending boleh ditutup menjadi Cancelled maupun Expired, sama seperti yang
     * dilakukan BookingController::cancel dan bookings:expire-pending.
     */
    public function test_admin_can_close_a_pending_booking(): void
    {
        foreach ([BookingStatus::Cancelled, BookingStatus::Expired] as $target) {
            $booking = $this->booking($this->apartment('Unit '.$target->value), BookingStatus::Pending);

            $this->edit($booking)
                ->fillForm(['status' => $target->value])
                ->call('save')
                ->assertHasNoFormErrors();

            $this->assertSame($target, $booking->fresh()->status);
        }
    }

    /**
     * Payload form create yang lengkap. Angka nights/price mengikuti apa yang
     * diisi admin — validasi turunannya adalah P1 pada batch berikutnya, bukan
     * bagian dari fix integritas ini.
     *
     * @return array<string, mixed>
     */
    private function createPayload(
        Apartment $apartment,
        BookingStatus $status,
        int $checkInDays,
        int $checkOutDays,
    ): array {
        return [
            'user_id' => User::factory()->create(['role' => 'user'])->id,
            'apartment_id' => $apartment->id,
            'check_in' => now()->addDays($checkInDays)->toDateString(),
            'check_out' => now()->addDays($checkOutDays)->toDateString(),
            'total_nights' => $checkOutDays - $checkInDays,
            'total_price' => 500000 * ($checkOutDays - $checkInDays),
            'status' => $status->value,
        ];
    }
}
