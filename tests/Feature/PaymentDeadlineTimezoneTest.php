<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Apartment;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * F-04 regression: deadline pembayaran internalnya UTC, tamunya WIB.
 * Test di sini membuktikan nilai WAKTU yang tampil benar — bukan sekadar
 * string "WIB" ada — termasuk rollover tanggal saat konversi menambah 7 jam
 * melewati tengah malam. now() dibekukan (setTestNow) supaya panel mana yang
 * dirender tidak bergantung pada tanggal test dijalankan.
 */
class PaymentDeadlineTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private function apartment(): Apartment
    {
        return Apartment::create([
            'title' => 'Timezone Apartment',
            'slug' => 'tz-'.Str::random(6),
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

    /**
     * Booking Pending dengan created_at ditetapkan eksplisit (UTC, timezone
     * aplikasi), sehingga deadline = created_at + 24 jam juga eksplisit.
     */
    private function pendingBookingWithCreatedAt(Carbon $createdAt): Booking
    {
        $booking = Booking::create([
            'booking_code' => 'APT-'.date('Ymd').'-'.strtoupper(Str::random(5)),
            'user_id' => User::factory()->create(['role' => 'user'])->id,
            'apartment_id' => $this->apartment()->id,
            'check_in' => now()->addDays(2)->toDateString(),
            'check_out' => now()->addDays(4)->toDateString(),
            'total_nights' => 2,
            'total_price' => 1000000,
            'status' => BookingStatus::Pending,
        ]);

        // created_at ditulis langsung agar timestamps otomatis tidak menimpanya.
        Booking::whereKey($booking->id)->update(['created_at' => $createdAt]);

        return $booking->fresh();
    }

    public function test_deadline_is_displayed_in_jakarta_time_not_utc(): void
    {
        // created 2026-09-08 13:00 UTC + 24 jam => deadline 2026-09-09 13:00 UTC
        // => 2026-09-09 20:00 WIB. UTC ditampilkan apa adanya adalah bug F-04.
        Carbon::setTestNow(Carbon::parse('2026-09-08 14:00:00', 'UTC'));

        try {
            $booking = $this->pendingBookingWithCreatedAt(
                Carbon::parse('2026-09-08 13:00:00', 'UTC'),
            );

            $this->actingAs($booking->user)->get('/booking/'.$booking->booking_code)
                ->assertOk()
                ->assertSee('Batas waktu pembayaran')
                // nilai waktu WIB, bukan nilai UTC yang salah label
                ->assertSee('20:00')
                ->assertDontSee('13:00 WIB')
                // tanggal yang tampil adalah tanggal deadline versi WIB (d = 09)
                ->assertSee('09 September 2026', false)
                ->assertDontSee('08 September 2026 pukul', false);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_deadline_rolls_over_to_the_next_day_when_conversion_passes_midnight(): void
    {
        // created 2026-09-08 23:30 UTC => deadline 2026-09-09 23:30 UTC
        // => 2026-09-10 06:30 WIB: tanggal bergeser, bukan hanya jamnya.
        Carbon::setTestNow(Carbon::parse('2026-09-09 00:00:00', 'UTC'));

        try {
            $booking = $this->pendingBookingWithCreatedAt(
                Carbon::parse('2026-09-08 23:30:00', 'UTC'),
            );

            $this->actingAs($booking->user)->get('/booking/'.$booking->booking_code)
                ->assertOk()
                ->assertSee('Batas waktu pembayaran')
                ->assertSee('06:30')
                ->assertDontSee('23:30 WIB')
                // 9 September versi WIB salah; harusnya 10 September
                ->assertDontSee('09 September 2026 pukul', false)
                ->assertSee('10 September 2026', false);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_expired_panel_shows_the_deadline_in_jakarta_time(): void
    {
        // created 2026-09-05 10:00 UTC => deadline 2026-09-06 10:00 UTC
        // => 06 September 2026, 17:00 WIB. "Sekarang" dibekukan setelah
        // deadline supaya panel kedaluwarsa yang dirender.
        Carbon::setTestNow(Carbon::parse('2026-09-07 00:00:00', 'UTC'));

        try {
            $booking = $this->pendingBookingWithCreatedAt(
                Carbon::parse('2026-09-05 10:00:00', 'UTC'),
            );

            $this->actingAs($booking->user)->get('/booking/'.$booking->booking_code)
                ->assertOk()
                ->assertSee('Reservasi kedaluwarsa')
                ->assertSee('06 September 2026, 17:00', false)
                ->assertDontSee('06 September 2026, 10:00', false);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_displayed_deadline_still_derives_from_payment_expiry_config(): void
    {
        // Perhitungan deadline TIDAK boleh berubah oleh fix tampilan:
        // created 2026-09-08 13:00 UTC + 1440 menit = 2026-09-09 13:00 UTC
        // = 2026-09-09 20:00 WIB. Kalau konversi menggeser nilai internal
        // (bukan hanya tampilan), test ini gagal.
        config(['booking.payment_expiry_minutes' => 1440]);

        $booking = $this->pendingBookingWithCreatedAt(
            Carbon::parse('2026-09-08 13:00:00', 'UTC'),
        );

        $this->assertSame(
            '2026-09-09 13:00:00',
            $booking->paymentDeadlineAt()->format('Y-m-d H:i:s'),
            'Deadline internal tetap UTC; hanya tampilan yang WIB.',
        );

        $deadlineWib = $booking->paymentDeadlineAt()->copy()->timezone('Asia/Jakarta');

        $this->assertSame('2026-09-09', $deadlineWib->toDateString());
        $this->assertSame('20:00', $deadlineWib->format('H:i'));
    }

    public function test_issued_at_timestamp_is_displayed_in_jakarta_time(): void
    {
        // "Diterbitkan" memakai created_at; wajib ikut zona WIB agar tamu
        // tidak membaca waktu penerbitan yang salah 7 jam.
        Carbon::setTestNow(Carbon::parse('2026-09-08 14:00:00', 'UTC'));

        try {
            $booking = $this->pendingBookingWithCreatedAt(
                Carbon::parse('2026-09-08 13:00:00', 'UTC'),
            );

            $this->actingAs($booking->user)->get('/booking/'.$booking->booking_code)
                ->assertOk()
                ->assertSee('Diterbitkan')
                ->assertSee('20:00 WIB')
                ->assertDontSee('13:00 WIB');
        } finally {
            Carbon::setTestNow();
        }
    }
}
