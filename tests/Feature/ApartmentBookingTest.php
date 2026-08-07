<?php

namespace Tests\Feature;

use App\Enums\ApartmentStatus;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Apartment;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class ApartmentBookingTest extends TestCase
{
    use RefreshDatabase;

    private const SNAP_TOKEN = 'SNAP-TEST-TOKEN';

    protected function setUp(): void
    {
        parent::setUp();

        // Never hit the real Midtrans API from tests.
        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getSnapToken')
                ->andReturn(self::SNAP_TOKEN)
                ->byDefault();
        });
    }

    private function makeApartment(array $overrides = []): Apartment
    {
        return Apartment::create(array_merge([
            'title' => 'Test Apartment',
            'slug' => 'test-apartment-'.Str::random(6),
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
        ], $overrides));
    }

    private function makeBooking(Apartment $apartment, User $user, array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'booking_code' => 'APT-'.date('Ymd').'-'.strtoupper(Str::random(5)),
            'user_id' => $user->id,
            'apartment_id' => $apartment->id,
            'check_in' => now()->addDays(2)->toDateString(),
            'check_out' => now()->addDays(5)->toDateString(),
            'total_nights' => 3,
            'total_price' => 1500000,
            'status' => BookingStatus::Confirmed,
            'notes' => null,
        ], $overrides));
    }

    public function test_user_can_view_apartments_and_detail_page(): void
    {
        $this->seed();

        $response = $this->get('/apartments');
        $response->assertStatus(200);

        $apartment = Apartment::first();
        $detailResponse = $this->get('/apartments/'.$apartment->slug);
        $detailResponse->assertStatus(200);
        $detailResponse->assertSee($apartment->title);
    }

    public function test_authenticated_user_can_create_booking_and_simulate_payment(): void
    {
        $this->seed();

        $user = User::where('role', 'user')->first();
        $apartment = Apartment::whereDoesntHave('bookings')->first();

        $response = $this->actingAs($user)->post('/booking', [
            'apartment_id' => $apartment->id,
            'check_in' => now()->addDays(10)->format('Y-m-d'),
            'check_out' => now()->addDays(12)->format('Y-m-d'),
            'notes' => 'Unit bersih dan ekstra tatanan.',
        ]);
        $response->assertRedirect();

        $booking = Booking::where('user_id', $user->id)->first();

        $payResponse = $this->actingAs($user)->post('/booking/'.$booking->booking_code.'/simulate-payment', [
            'status' => 'settlement',
        ]);
        $payResponse->assertRedirect();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_booking_creates_a_payment_row_with_snap_token(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $apartment = $this->makeApartment();

        $this->actingAs($user)->post('/booking', [
            'apartment_id' => $apartment->id,
            'check_in' => now()->addDays(10)->format('Y-m-d'),
            'check_out' => now()->addDays(12)->format('Y-m-d'),
        ])->assertRedirect();

        $this->assertDatabaseHas('payments', [
            'booking_id' => Booking::first()->id,
            'snap_token' => self::SNAP_TOKEN,
            'status' => 'pending',
        ]);
    }

    public function test_cannot_book_apartment_in_maintenance(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $apartment = $this->makeApartment(['status' => 'maintenance']);

        $this->actingAs($user)
            ->post('/booking', [
                'apartment_id' => $apartment->id,
                'check_in' => now()->addDays(10)->format('Y-m-d'),
                'check_out' => now()->addDays(12)->format('Y-m-d'),
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_maintenance_apartment_detail_page_returns_404(): void
    {
        $apartment = $this->makeApartment(['status' => 'maintenance']);

        $this->get('/apartments/'.$apartment->slug)->assertNotFound();
    }

    public function test_availability_check_rejects_maintenance_apartment(): void
    {
        $apartment = $this->makeApartment(['status' => 'maintenance']);

        $this->post('/apartments/'.$apartment->id.'/availability', [
            'check_in' => now()->addDays(10)->format('Y-m-d'),
            'check_out' => now()->addDays(12)->format('Y-m-d'),
        ])->assertJson(['available' => false]);
    }

    public function test_cannot_book_overlapping_dates(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $apartment = $this->makeApartment();
        $this->makeBooking($apartment, $user); // confirmed, +2..+5

        $response = $this->actingAs($user)->post('/booking', [
            'apartment_id' => $apartment->id,
            'check_in' => now()->addDays(4)->format('Y-m-d'),
            'check_out' => now()->addDays(6)->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('check_in');
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_adjacent_checkout_and_checkin_are_allowed(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $apartment = $this->makeApartment();
        $this->makeBooking($apartment, $user); // check-out at +5

        $this->actingAs($user)
            ->post('/booking', [
                'apartment_id' => $apartment->id,
                'check_in' => now()->addDays(5)->format('Y-m-d'),
                'check_out' => now()->addDays(7)->format('Y-m-d'),
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_booking_rolls_back_when_payment_gateway_fails(): void
    {
        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getSnapToken')
                ->andThrow(new \RuntimeException('gateway down'));
        });

        $user = User::factory()->create(['role' => 'user']);
        $apartment = $this->makeApartment();

        $response = $this->actingAs($user)->post('/booking', [
            'apartment_id' => $apartment->id,
            'check_in' => now()->addDays(10)->format('Y-m-d'),
            'check_out' => now()->addDays(12)->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('payment');
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_user_cannot_view_someone_elses_booking(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $intruder = User::factory()->create(['role' => 'user']);
        $apartment = $this->makeApartment();
        $booking = $this->makeBooking($apartment, $owner, ['status' => BookingStatus::Pending]);

        $this->actingAs($intruder)
            ->get('/booking/'.$booking->booking_code)
            ->assertForbidden();
    }

    public function test_past_check_in_dates_are_rejected(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $apartment = $this->makeApartment();

        $response = $this->actingAs($user)->post('/booking', [
            'apartment_id' => $apartment->id,
            'check_in' => now()->subDays(1)->format('Y-m-d'),
            'check_out' => now()->addDays(2)->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('check_in');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_pending_booking_blocks_availability_check(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $apartment = $this->makeApartment();
        $this->makeBooking($apartment, $user, ['status' => BookingStatus::Pending]);

        $this->post('/apartments/'.$apartment->id.'/availability', [
            'check_in' => now()->addDays(3)->format('Y-m-d'),
            'check_out' => now()->addDays(4)->format('Y-m-d'),
        ])->assertJson(['available' => false]);
    }

    public function test_cancelled_booking_detail_page_renders(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $apartment = $this->makeApartment();
        $booking = $this->makeBooking($apartment, $user, ['status' => BookingStatus::Cancelled]);

        $this->actingAs($user)
            ->get('/booking/'.$booking->booking_code)
            ->assertOk()
            ->assertSee($booking->booking_code);
    }

    public function test_apartment_status_and_user_role_enum_cast_roundtrip(): void
    {
        $apartment = $this->makeApartment();
        $user = User::factory()->create(['role' => 'user']);

        $this->assertSame(ApartmentStatus::Available, $apartment->fresh()->status);
        $this->assertSame(UserRole::User, $user->fresh()->role);
        $this->assertFalse($user->fresh()->isAdmin());
        $this->assertTrue(User::factory()->create(['role' => 'admin'])->fresh()->isAdmin());
    }

    public function test_payment_status_enum_cast_roundtrip(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $apartment = $this->makeApartment();
        $booking = $this->makeBooking($apartment, $user, ['status' => BookingStatus::Pending]);
        $payment = Payment::create([
            'booking_id' => $booking->id,
            'gross_amount' => 1500000,
            'snap_token' => self::SNAP_TOKEN,
            'status' => PaymentStatus::Pending,
        ]);

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
    }
}
