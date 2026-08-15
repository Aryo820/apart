<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Apartment;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Kontrak UI customer-facing yang mudah hilang saat styling: halaman error
 * pakai layout sendiri, empty state harus membedakan "kosong" vs "tidak cocok
 * filter", dan tidak boleh ada label fitur yang belum ada implementasinya.
 */
class UiPolishTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_http_errors_render_the_branded_indonesian_page(): void
    {
        $response = $this->get('/halaman-yang-tidak-ada')->assertNotFound();

        $response->assertSee('Halaman tidak ditemukan')
            ->assertSee('Error 404')
            ->assertSee('Kembali ke beranda')
            // layout situs ikut terpakai (navbar + footer), bukan halaman putih Laravel
            ->assertSee('Lewati ke konten utama')
            ->assertDontSee('Not Found');
    }

    public function test_forbidden_page_reuses_the_same_layout_with_its_own_copy(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $intruder = User::factory()->create(['role' => 'user']);
        $apartment = $this->makeApartment();
        $booking = Booking::create([
            'booking_code' => 'APT-'.date('Ymd').'-'.strtoupper(Str::random(5)),
            'user_id' => $owner->id,
            'apartment_id' => $apartment->id,
            'check_in' => now()->addDays(2)->toDateString(),
            'check_out' => now()->addDays(4)->toDateString(),
            'total_nights' => 2,
            'total_price' => 1000000,
            'status' => BookingStatus::Pending,
        ]);

        $this->actingAs($intruder)
            ->get('/booking/'.$booking->booking_code)
            ->assertForbidden()
            ->assertSee('Akses ditolak')
            ->assertSee('Error 403');
    }

    public function test_catalogue_empty_state_separates_no_units_from_no_matches(): void
    {
        // Belum ada unit sama sekali — jangan menyalahkan filter.
        $this->get('/apartments')
            ->assertOk()
            ->assertSee('Belum ada unit tersedia')
            ->assertDontSee('cocok dengan filter Anda');

        $this->makeApartment(['city' => 'Jakarta']);

        // Ada unit, tapi filternya tidak menemukan apa pun.
        $this->get('/apartments?city=Surabaya')
            ->assertOk()
            ->assertSee('Unit tidak ditemukan')
            ->assertSee('cocok dengan filter Anda');
    }

    public function test_booking_list_does_not_advertise_an_invoice_that_does_not_exist(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $apartment = $this->makeApartment();
        Booking::create([
            'booking_code' => 'APT-'.date('Ymd').'-'.strtoupper(Str::random(5)),
            'user_id' => $user->id,
            'apartment_id' => $apartment->id,
            'check_in' => now()->addDays(2)->toDateString(),
            'check_out' => now()->addDays(4)->toDateString(),
            'total_nights' => 2,
            'total_price' => 1000000,
            'status' => BookingStatus::Confirmed,
        ]);

        $this->actingAs($user)->get('/my-bookings')
            ->assertOk()
            ->assertSee('Lihat detail')
            ->assertDontSee('Invoice');
    }
}
