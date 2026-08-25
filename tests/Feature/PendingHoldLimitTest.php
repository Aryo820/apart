<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\Booking;
use App\Models\User;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Pendaftaran terbuka + hold kalender 24 jam = denial-of-inventory murah
 * bila satu akun boleh menumpuk reservasi Pending tanpa batas. Cap ini
 * membatasi jumlah hold aktif per akun, dihitung dengan definisi yang sama
 * dengan Booking::scopeBlocking (Pending yang belum kedaluwarsa).
 */
class PendingHoldLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['booking.max_pending_per_user' => 3]);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getSnapToken')->andReturn('SNAP-TEST-TOKEN')->byDefault();
        });
    }

    public function test_booking_is_rejected_at_active_pending_cap(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $apartment = $this->makeApartment();

        foreach ([0, 10, 20] as $offset) {
            $this->createPendingBooking($user->id, $apartment->id, $offset);
        }

        $response = $this->actingAs($user)->post('/booking', [
            'apartment_id' => $apartment->id,
            'check_in' => now()->addDays(40)->format('Y-m-d'),
            'check_out' => now()->addDays(42)->format('Y-m-d'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('check_in');

        $this->assertDatabaseCount('bookings', 3);
    }

    public function test_expired_pending_holds_do_not_count_toward_the_cap(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $apartment = $this->makeApartment();

        foreach ([0, 10, 20] as $offset) {
            $this->createPendingBooking($user->id, $apartment->id, $offset);
        }

        // Satu hold sudah lewat batas waktunya: tanggalnya bebas dan cap
        // tidak lagi dihitung untuk baris ini. created_at tidak fillable,
        // jadi ditulis lewat atribut + save(), bukan mass assignment.
        $expired = Booking::query()->where('user_id', $user->id)->orderBy('id')->first();
        $expired->created_at = now()->subMinutes((int) config('booking.payment_expiry_minutes'))->subMinute();
        $expired->save();

        $response = $this->actingAs($user)->post('/booking', [
            'apartment_id' => $apartment->id,
            'check_in' => now()->addDays(40)->format('Y-m-d'),
            'check_out' => now()->addDays(42)->format('Y-m-d'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseCount('bookings', 4);
    }

    private function makeApartment(): Apartment
    {
        return Apartment::create([
            'title' => 'Hold Cap Apartment',
            'slug' => 'hold-cap-'.Str::random(6),
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
    }

    private function createPendingBooking(int $userId, int $apartmentId, int $dayOffset): Booking
    {
        return Booking::create([
            'booking_code' => Booking::generateBookingCode(),
            'user_id' => $userId,
            'apartment_id' => $apartmentId,
            'check_in' => now()->addDays(60 + $dayOffset)->toDateString(),
            'check_out' => now()->addDays(62 + $dayOffset)->toDateString(),
            'total_nights' => 2,
            'total_price' => 1000000,
            'status' => 'pending',
        ]);
    }
}
