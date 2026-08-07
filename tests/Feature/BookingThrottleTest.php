<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\User;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class BookingThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Never hit the real Midtrans API from tests.
        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getSnapToken')->andReturn('SNAP-TEST-TOKEN')->byDefault();
        });
    }

    private function makeApartment(): Apartment
    {
        return Apartment::create([
            'title' => 'Throttle Test Apartment',
            'slug' => 'throttle-test-'.Str::random(6),
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

    public function test_booking_is_rate_limited_after_10_requests(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $apartment = $this->makeApartment();

        // 10 valid bookings with non-overlapping dates (3-day gap each).
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->post('/booking', [
                'apartment_id' => $apartment->id,
                'check_in' => now()->addDays(20 + ($i * 3))->format('Y-m-d'),
                'check_out' => now()->addDays(22 + ($i * 3))->format('Y-m-d'),
            ])->assertRedirect();
        }

        $this->actingAs($user)->post('/booking', [
            'apartment_id' => $apartment->id,
            'check_in' => now()->addDays(40)->format('Y-m-d'),
            'check_out' => now()->addDays(42)->format('Y-m-d'),
        ])->assertStatus(429);

        $this->assertDatabaseCount('bookings', 10);
    }

    public function test_availability_check_is_rate_limited_after_30_requests(): void
    {
        $apartment = $this->makeApartment();

        for ($i = 0; $i < 30; $i++) {
            $this->post('/apartments/'.$apartment->id.'/availability', [
                'check_in' => now()->addDays(10)->format('Y-m-d'),
                'check_out' => now()->addDays(12)->format('Y-m-d'),
            ])->assertOk();
        }

        $this->post('/apartments/'.$apartment->id.'/availability', [
            'check_in' => now()->addDays(10)->format('Y-m-d'),
            'check_out' => now()->addDays(12)->format('Y-m-d'),
        ])->assertStatus(429);
    }
}
