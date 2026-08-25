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

/**
 * Halaman reservasi adalah satu-satunya rute terautentikasi yang menerima
 * string pencarian bebas. Tanpa limiter, kode booking bisa dienumerasi
 * dengan laju penuh; otorisasi 403 baru berjalan SETELAH barisnya ketemu.
 */
class BookingShowThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_code_lookup_is_rate_limited_after_60_requests(): void
    {
        $attacker = User::factory()->create(['role' => 'user']);

        // Kode yang sengaja tidak ada: tiap hit tetap menggerus bucket
        // sebelum firstOrFail() melempar 404 — persis seperti enumerasi.
        for ($i = 0; $i < 60; $i++) {
            $this->actingAs($attacker)
                ->get('/booking/APT-19990101-AAAA'.strtoupper(Str::random(2)))
                ->assertNotFound();
        }

        $this->actingAs($attacker)
            ->get('/booking/APT-19990101-BBBBBBBB')
            ->assertStatus(429);
    }

    public function test_limit_is_per_user_and_owner_still_sees_their_page(): void
    {
        $attacker = User::factory()->create(['role' => 'user']);
        $victimBooking = $this->makePendingBooking();

        for ($i = 0; $i < 61; $i++) {
            $this->actingAs($attacker)->get('/booking/APT-19990101-ZZZZZZZZ');
        }

        // Bucket si penyerang sudah penuh: bahkan kode yang benar pun ditolak
        // 429 — limiter membatasi enumerasi sebelum otorisasi dicoba.
        $this->actingAs($attacker)
            ->get('/booking/'.$victimBooking->booking_code)
            ->assertStatus(429);

        $owner = User::find($victimBooking->user_id);

        $this->actingAs($owner)
            ->get('/booking/'.$victimBooking->booking_code)
            ->assertOk();
    }

    private function makePendingBooking(): Booking
    {
        $user = User::factory()->create(['role' => 'user']);
        $apartment = Apartment::create([
            'title' => 'Show Throttle Apartment',
            'slug' => 'show-throttle-'.Str::random(6),
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
            'booking_code' => Booking::generateBookingCode(),
            'user_id' => $user->id,
            'apartment_id' => $apartment->id,
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(12)->toDateString(),
            'total_nights' => 2,
            'total_price' => 1000000,
            'status' => BookingStatus::Pending,
        ]);

        // snap_token sudah ada: show() tidak akan memanggil gateway.
        Payment::create([
            'booking_id' => $booking->id,
            'gross_amount' => 1000000,
            'snap_token' => 'SNAP-TEST',
            'status' => PaymentStatus::Pending,
        ]);

        return $booking;
    }
}
