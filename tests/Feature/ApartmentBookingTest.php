<?php

namespace Tests\Feature;

use App\Enums\ApartmentStatus;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Apartment;
use App\Models\Booking;
use App\Models\Facility;
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

    public function test_authenticated_user_can_create_booking_and_open_midtrans_payment_page(): void
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

        $booking = Booking::where('user_id', $user->id)->latest('id')->firstOrFail();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'snap_token' => self::SNAP_TOKEN,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->get('/booking/'.$booking->booking_code)
            ->assertOk()
            ->assertSee('Bayar')
            ->assertDontSee('Bayar Lunas');
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

    public function test_relative_image_paths_render_as_storage_urls(): void
    {
        // Filament stores a relative disk path; printing the raw column made
        // the browser resolve it against the page URL (404 on every page).
        $user = User::factory()->create(['role' => 'user']);
        $apartment = $this->makeApartment([
            'main_image' => 'apartments/main/unit.jpg',
            'images' => ['apartments/gallery/one.jpg'],
        ]);
        $booking = $this->makeBooking($apartment, $user);

        foreach (['/apartments', '/apartments/'.$apartment->slug] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('/storage/apartments/main/unit.jpg', false)
                ->assertDontSee('src="apartments/main/unit.jpg"', false);
        }

        $this->get('/apartments/'.$apartment->slug)
            ->assertSee('/storage/apartments/gallery/one.jpg', false);

        foreach (['/my-bookings', '/booking/'.$booking->booking_code] as $url) {
            $this->actingAs($user)->get($url)
                ->assertOk()
                ->assertSee('/storage/apartments/main/unit.jpg', false)
                ->assertDontSee('src="apartments/main/unit.jpg"', false);
        }
    }

    public function test_absolute_image_urls_are_left_untouched(): void
    {
        $apartment = $this->makeApartment(['main_image' => 'https://example.com/img.jpg']);

        $this->get('/apartments/'.$apartment->slug)
            ->assertOk()
            ->assertSee('https://example.com/img.jpg', false);
    }

    public function test_conflicting_booking_keeps_submitted_dates_on_the_form(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $apartment = $this->makeApartment();
        $this->makeBooking($apartment, $user); // confirmed, +2..+5

        $checkIn = now()->addDays(4)->format('Y-m-d');
        $checkOut = now()->addDays(6)->format('Y-m-d');

        $this->actingAs($user)
            ->from('/apartments/'.$apartment->slug)
            ->post('/booking', [
                'apartment_id' => $apartment->id,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'notes' => 'Tiba malam',
            ])
            ->assertRedirect('/apartments/'.$apartment->slug);

        // The form must come back populated, with the error next to the field.
        $this->actingAs($user)->get('/apartments/'.$apartment->slug)
            ->assertOk()
            ->assertSee('value="'.$checkIn.'"', false)
            ->assertSee('value="'.$checkOut.'"', false)
            ->assertSee('Tiba malam', false)
            ->assertSee('id="check_in_error"', false)
            ->assertSee('Apartemen ini tidak tersedia pada tanggal yang Anda pilih');
    }

    public function test_listing_paginates_with_the_published_ink_pagination_view(): void
    {
        // paginate(9) — 10 units forces a second page.
        for ($i = 0; $i < 10; $i++) {
            $this->makeApartment(['title' => 'Unit '.$i]);
        }

        $response = $this->get('/apartments')->assertOk();

        $response->assertSee('Pagination Navigation', false)
            ->assertSee('page=2', false)
            // the framework default (light theme) must not be in use anymore
            ->assertDontSee('dark:bg-gray-800', false);

        $this->get('/apartments?page=2')
            ->assertOk()
            ->assertSee('Menampilkan 10–10 dari 10 unit', false);
    }

    public function test_listing_filters_come_back_populated_in_the_sidebar(): void
    {
        $this->makeApartment(['title' => 'Murah', 'price_per_night' => 200000, 'city' => 'Bandung', 'capacity' => 2]);
        $this->makeApartment(['title' => 'Mahal', 'price_per_night' => 900000, 'city' => 'Bandung', 'capacity' => 6]);

        $response = $this->get('/apartments?'.http_build_query([
            'city' => 'Bandung',
            'min_price' => 100000,
            'max_price' => 500000,
            'capacity' => 2,
        ]))->assertOk();

        $response->assertSee('Murah')
            ->assertDontSee('>Mahal<', false)
            ->assertSee('value="100000"', false)
            ->assertSee('value="500000"', false)
            ->assertSee('4 filter aktif');
    }

    public function test_detail_page_still_shows_every_field_after_the_restyle(): void
    {
        $apartment = $this->makeApartment([
            'title' => 'Unit Uji Detail',
            'description' => "Baris pertama.\nBaris kedua.",
            'bedrooms' => 3,
            'bathrooms' => 2,
            'area_sqm' => 96,
            'capacity' => 5,
            'price_per_night' => 1250000,
        ]);
        $apartment->facilities()->attach(Facility::create(['name' => 'Kolam Renang', 'icon' => 'pool']));

        $this->get('/apartments/'.$apartment->slug)
            ->assertOk()
            ->assertSee('Unit Uji Detail')
            ->assertSee($apartment->address)
            ->assertSee($apartment->city)
            ->assertSee('IDR 1.250.000', false)
            ->assertSee('3 kamar')      // bedrooms
            ->assertSee('2 kamar')      // bathrooms
            ->assertSee('96 m²', false)
            ->assertSee('5 orang')
            ->assertSee('Baris pertama.<br />', false) // nl2br masih aktif
            ->assertSee('Kolam Renang')
            // kontrak form yang dipakai kalkulator harga tidak boleh berubah
            ->assertSee('id="priceCalculationCard"', false)
            ->assertSee('id="calcTotalPrice"', false)
            ->assertSee('name="apartment_id"', false);
    }

    public function test_every_booking_status_gets_its_own_label_on_both_pages(): void
    {
        // Sebelumnya Completed ikut jatuh ke cabang merah "dibatalkan".
        $user = User::factory()->create(['role' => 'user']);
        $apartment = $this->makeApartment();

        $expected = [
            BookingStatus::Pending->value => 'Menunggu pembayaran',
            BookingStatus::Confirmed->value => 'Terkonfirmasi',
            BookingStatus::Cancelled->value => 'Dibatalkan',
            BookingStatus::Completed->value => 'Selesai',
        ];

        $codes = [];
        $offset = 0;
        foreach (array_keys($expected) as $status) {
            $booking = $this->makeBooking($apartment, $user, [
                'status' => $status,
                'check_in' => now()->addDays($offset += 10)->toDateString(),
                'check_out' => now()->addDays($offset += 2)->toDateString(),
            ]);
            $codes[$status] = $booking->booking_code;
        }

        $list = $this->actingAs($user)->get('/my-bookings')->assertOk();
        foreach ($expected as $label) {
            $list->assertSee($label);
        }

        foreach ($expected as $status => $label) {
            $this->actingAs($user)->get('/booking/'.$codes[$status])
                ->assertOk()
                ->assertSee($label);
        }

        // Booking yang selesai tidak boleh dibaca sebagai gagal.
        $this->actingAs($user)->get('/booking/'.$codes[BookingStatus::Completed->value])
            ->assertDontSee('Dibatalkan');
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

    public function test_detail_page_exposes_booked_ranges_so_the_picker_can_block_them(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $apartment = $this->makeApartment();
        $blocked = $this->makeBooking($apartment, $user); // confirmed, +2..+5
        $free = $this->makeBooking($apartment, $user, [   // cancelled — tidak memblokir
            'status' => BookingStatus::Cancelled,
            'check_in' => now()->addDays(20)->toDateString(),
            'check_out' => now()->addDays(22)->toDateString(),
        ]);

        $this->get('/apartments/'.$apartment->slug)
            ->assertOk()
            ->assertSee('Tanggal tidak tersedia (1 periode)')
            ->assertSee($blocked->check_in->format('Y-m-d'), false)
            ->assertDontSee($free->check_in->format('Y-m-d'), false)
            // endpoint availability harus terpasang di form (URL di-escape @json)
            ->assertSee('apartments\/'.$apartment->id.'\/availability', false)
            ->assertSee('id="availabilityMessage"', false);
    }

    public function test_detail_page_shows_the_full_price_breakdown_fields(): void
    {
        $apartment = $this->makeApartment(['price_per_night' => 750000]);

        $this->get('/apartments/'.$apartment->slug)
            ->assertOk()
            ->assertSee('IDR 750.000', false)
            ->assertSee('Subtotal')
            ->assertSee('id="calcSubtotal"', false)
            ->assertSee('id="calcNights"', false)
            ->assertSee('id="calcTotalPrice"', false);
    }

    public function test_review_page_shows_every_field_the_guest_must_check_before_paying(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $apartment = $this->makeApartment(['price_per_night' => 500000]);
        $booking = $this->makeBooking($apartment, $user, ['status' => BookingStatus::Pending]);
        Payment::create([
            'booking_id' => $booking->id,
            'gross_amount' => $booking->total_price,
            'snap_token' => self::SNAP_TOKEN,
            'status' => PaymentStatus::Pending,
        ]);

        $this->actingAs($user)->get('/booking/'.$booking->booking_code)
            ->assertOk()
            ->assertSee($booking->booking_code)
            ->assertSee($apartment->title)
            ->assertSee($booking->check_in->format('d F Y'))
            ->assertSee($booking->check_out->format('d F Y'))
            ->assertSee('3 malam')
            ->assertSee('IDR 500.000', false)          // tarif per malam
            ->assertSee('IDR 1.500.000', false)        // subtotal + total
            ->assertSee('Status pembayaran')
            ->assertSee('Menunggu pembayaran')
            ->assertSee('Bayar Sekarang');
    }

    public function test_paid_booking_reports_settlement_as_the_payment_status(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $apartment = $this->makeApartment();
        $booking = $this->makeBooking($apartment, $user, ['status' => BookingStatus::Confirmed]);
        Payment::create([
            'booking_id' => $booking->id,
            'gross_amount' => $booking->total_price,
            'snap_token' => self::SNAP_TOKEN,
            'status' => PaymentStatus::Settlement,
        ]);

        $this->actingAs($user)->get('/booking/'.$booking->booking_code)
            ->assertOk()
            ->assertSee('Lunas')
            ->assertSee('Pembayaran')
            ->assertDontSee('Bayar Sekarang');
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
