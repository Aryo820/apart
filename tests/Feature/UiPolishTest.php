<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Apartment;
use App\Models\Booking;
use App\Models\Facility;
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

    /**
     * Grid fasilitas harus benar untuk jumlah apa pun. Layout lamanya memakai
     * col-span yang dihitung dari indeks item (2/2/2 lalu 3/3), jadi hanya
     * benar untuk tepat 5 fasilitas.
     */
    public function test_facility_grid_survives_any_number_of_facilities(): void
    {
        foreach ([1, 2, 3, 4, 5, 7] as $count) {
            Facility::query()->delete();

            for ($i = 1; $i <= $count; $i++) {
                Facility::create(['name' => "Fasilitas {$i}", 'icon' => 'wifi', 'description' => 'Deskripsi']);
            }

            $response = $this->get('/')->assertOk();

            // Semua yang diambil controller tampil (dibatasi 6 di grid).
            foreach (range(1, min($count, 6)) as $i) {
                $response->assertSee("Fasilitas {$i}");
            }

            // Tidak ada lagi kelas span yang bergantung pada jumlah item.
            $response->assertDontSee('md:col-span-2', false)
                ->assertDontSee('md:grid-cols-6', false);
        }
    }

    /** CTA mobile memakai form booking yang sama, bukan form kedua. */
    public function test_mobile_cta_reuses_the_existing_booking_form(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $apartment = $this->makeApartment();

        $html = $this->actingAs($user)->get('/apartments/'.$apartment->slug)
            ->assertOk()
            ->assertSee('mobile-cta-bar', false)
            // asosiasi form native, bukan form kedua
            ->assertSee('<button type="submit" form="bookingForm"', false)
            // hook label yang ditukar script saat tanggal tidak tersedia
            ->assertSee('data-mobile-cta-label', false)
            ->getContent();

        // Hanya SATU form yang menembak bookings.store — tidak ada logika ganda.
        $this->assertSame(1, substr_count($html, 'action="'.route('bookings.store').'"'));
    }

    /** Tamu belum login tidak boleh melihat CTA "Booking" yang menyesatkan. */
    public function test_mobile_cta_sends_guests_to_login_instead(): void
    {
        $apartment = $this->makeApartment();

        $this->get('/apartments/'.$apartment->slug)
            ->assertOk()
            ->assertSee('mobile-cta-bar', false)
            ->assertSee('Masuk untuk booking')
            ->assertDontSee('<button type="submit" form="bookingForm"', false);
    }

    public function test_long_description_is_clamped_but_kept_whole_in_the_dom(): void
    {
        $long = str_repeat('Deskripsi unit yang sangat panjang sekali. ', 30);
        $apartment = $this->makeApartment(['description' => $long]);

        $this->get('/apartments/'.$apartment->slug)
            ->assertOk()
            ->assertSee('prose-clamp', false)
            ->assertSee('Baca selengkapnya')
            // teks lengkap tetap ada — dipotong tingginya, bukan isinya
            ->assertSee('Deskripsi unit yang sangat panjang sekali.');

        $short = $this->makeApartment(['description' => 'Deskripsi singkat.']);

        $this->get('/apartments/'.$short->slug)
            ->assertOk()
            ->assertDontSee('prose-clamp', false)
            ->assertDontSee('Baca selengkapnya');
    }

    public function test_listing_cards_surface_a_few_facilities_without_extra_queries(): void
    {
        $apartment = $this->makeApartment();
        $facilities = collect(['WiFi', 'Kolam Renang', 'Gym', 'Parkir', 'Dapur'])
            ->map(fn ($name) => Facility::create(['name' => $name, 'icon' => 'wifi']));
        $apartment->facilities()->attach($facilities->pluck('id'));

        $this->get('/apartments')
            ->assertOk()
            ->assertSee('WiFi')
            ->assertSee('Kolam Renang')
            ->assertSee('Gym')
            // dibatasi 3 supaya kartu tetap ringkas
            ->assertDontSee('Parkir')
            ->assertSee('+2 lainnya');
    }

    /**
     * Lantai ukuran teks pendukung adalah 0.75rem (token text-xs). Sebelumnya
     * label form 0.58rem ≈ 9.3px dan metadata kartu 10–11px — huruf kapital
     * dengan tracking lebar terbaca lebih kecil lagi daripada angkanya, dan
     * justru itu yang memandu form pencarian.
     */
    public function test_supporting_text_never_drops_below_the_12px_floor(): void
    {
        $apartment = $this->makeApartment(['is_featured' => true]);
        Facility::create(['name' => 'WiFi', 'icon' => 'wifi', 'description' => 'Internet cepat']);
        $apartment->facilities()->attach(Facility::first()->id);

        foreach (['/', '/apartments', '/apartments/'.$apartment->slug] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertDoesNotMatchRegularExpression(
                '/text-\[(?:[0-9]|1[01])px\]|text-\[0\.[0-6]\d*rem\]/',
                $html,
                "Ada kelas teks di bawah 12px pada {$url}."
            );
        }
    }
}
