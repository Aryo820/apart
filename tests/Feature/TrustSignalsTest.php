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
 * Trust signal yang mudah hilang tanpa disadari: jalur bantuan, halaman legal,
 * dan konfirmasi setelah bayar. Semuanya bersumber dari config/support.php,
 * jadi test ini juga menjaga agar kontak tidak kembali di-hardcode di Blade.
 */
class TrustSignalsTest extends TestCase
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

    private function makeBooking(User $user, BookingStatus $status): Booking
    {
        return Booking::create([
            'booking_code' => 'APT-'.date('Ymd').'-'.strtoupper(Str::random(5)),
            'user_id' => $user->id,
            'apartment_id' => $this->makeApartment()->id,
            'check_in' => now()->addDays(2)->toDateString(),
            'check_out' => now()->addDays(4)->toDateString(),
            'total_nights' => 2,
            'total_price' => 1000000,
            'status' => $status,
        ]);
    }

    public function test_footer_exposes_support_contact_and_legal_links_on_every_page(): void
    {
        config([
            'support.email' => 'bantuan@contoh.test',
            'support.phone' => '+62 21 9999 8888',
            'support.phone_tel' => '+622199998888',
        ]);

        foreach (['/', '/apartments', '/login'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('mailto:bantuan@contoh.test', false)
                ->assertSee('tel:+622199998888', false)
                ->assertSee(route('legal.terms'), false)
                ->assertSee(route('legal.privacy'), false)
                ->assertSee('Midtrans');
        }
    }

    public function test_legal_pages_are_reachable_and_use_the_site_layout(): void
    {
        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertSee('Syarat &amp; Ketentuan', false)
            ->assertSee('Ketersediaan unit')
            ->assertSee('Kegagalan pembayaran')
            ->assertSee('Kontak bantuan')
            // layout situs ikut terpakai, bukan halaman polos
            ->assertSee('Lewati ke konten utama');

        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('Kebijakan Privasi')
            ->assertSee('Data yang kami kumpulkan')
            ->assertSee('Lewati ke konten utama');
    }

    /**
     * Dokumen legal harus menggambarkan aplikasi yang benar-benar dikirim.
     * Sebelumnya §5 menyatakan pembatalan mandiri tidak tersedia padahal
     * tombolnya ada di halaman reservasi, §4 menjanjikan sesi pembayaran baru
     * yang tidak pernah dibuat kode, dan halaman ini memuat catatan "perlu
     * dikonfigurasi" yang ditujukan ke pengelola, bukan ke pembaca.
     */
    public function test_terms_match_the_shipped_cancellation_and_expiry_behaviour(): void
    {
        $deadlineHours = (int) round(config('booking.payment_expiry_minutes') / 60);

        $this->get(route('legal.terms'))
            ->assertOk()
            // fitur yang memang ada (BookingController::cancel + tombolnya)
            ->assertSee('Batalkan reservasi')
            // batas waktu ikut config, bukan angka yang diketik di teks
            ->assertSee($deadlineHours.' jam')
            // 'expire' dan 'cancel' dibedakan, sama seperti BookingStatus
            ->assertSee('kedaluwarsa')
            // tidak ada catatan internal / TODO yang terpublikasi
            ->assertDontSee('Perlu dikonfigurasi')
            ->assertDontSee('belum ditetapkan')
            ->assertDontSee('harus diisi oleh pengelola')
            // klaim lama yang bertentangan dengan kode
            ->assertDontSee('pembatalan tidak dapat dilakukan sendiri')
            ->assertDontSee('sesi baru akan dibuat');
    }

    /** Klaim keamanan yang tidak punya dasar di kode tidak boleh muncul. */
    public function test_pages_make_no_unfounded_security_claims(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $booking = $this->makeBooking($user, BookingStatus::Pending);

        foreach (['/', route('legal.terms'), route('legal.privacy')] as $url) {
            $response = $this->get($url)->assertOk();
            foreach (['100% aman', 'bank-level', 'PCI', 'terenkripsi 256'] as $claim) {
                $response->assertDontSee($claim, false);
            }
        }

        $this->actingAs($user)->get('/booking/'.$booking->booking_code)
            ->assertOk()
            ->assertDontSee('100% aman')
            ->assertDontSee('PCI', false)
            // klaim yang boleh: apa yang benar-benar terjadi di kode
            ->assertSee('Midtrans');
    }

    public function test_confirmed_booking_shows_a_self_contained_confirmation(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $booking = $this->makeBooking($user, BookingStatus::Confirmed);

        $this->actingAs($user)->get('/booking/'.$booking->booking_code)
            ->assertOk()
            ->assertSee('Reservasi terkonfirmasi')
            ->assertSee($booking->booking_code)
            ->assertSee('Langkah selanjutnya')
            ->assertSee('Cetak / simpan PDF')
            ->assertSee('Butuh bantuan?')
            ->assertSee('IDR 1.000.000');
    }

    public function test_support_contact_is_present_in_every_booking_state(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        config(['support.email' => 'bantuan@contoh.test']);

        foreach (BookingStatus::cases() as $status) {
            $booking = $this->makeBooking($user, $status);

            $this->actingAs($user)->get('/booking/'.$booking->booking_code)
                ->assertOk()
                ->assertSee('Butuh bantuan?')
                ->assertSee('bantuan@contoh.test');
        }
    }

    public function test_apartment_detail_sets_payment_expectation_before_booking(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $apartment = $this->makeApartment();

        $this->actingAs($user)->get('/apartments/'.$apartment->slug)
            ->assertOk()
            ->assertSee('Belum ada penagihan pada langkah ini')
            ->assertSee('membayar melalui Midtrans');
    }
}
