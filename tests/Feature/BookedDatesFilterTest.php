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
 * F-06 regression: kalender halaman detail unit hanya memuat booking yang
 * masih relevan ke depan (check_out >= today). Booking selesai kemarin
 * tidak ikut dimuat, tapi SEMANTICS BLOKING TIDAK BERUBAH: status yang
 * memblokir tetap ditentukan scopeBlocking, dan pengecekan konflik server
 * (scopeConflicting) tetap memperhatikan data historis.
 */
class BookedDatesFilterTest extends TestCase
{
    use RefreshDatabase;

    private function apartment(): Apartment
    {
        return Apartment::create([
            'title' => 'Booked Dates Apartment',
            'slug' => 'booked-'.Str::random(6),
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

    private function booking(Apartment $apartment, BookingStatus $status, string $checkIn, string $checkOut): Booking
    {
        return Booking::create([
            'booking_code' => 'APT-'.date('Ymd').'-'.strtoupper(Str::random(5)),
            'user_id' => User::factory()->create(['role' => 'user'])->id,
            'apartment_id' => $apartment->id,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'total_nights' => 2,
            'total_price' => 1000000,
            'status' => $status,
        ]);
    }

    public function test_past_booking_is_not_in_booked_dates_but_today_boundary_is(): void
    {
        $apartment = $this->apartment();

        // check_out kemarin (7 Sep) -> EXCLUDE
        $past = $this->booking($apartment, BookingStatus::Confirmed, '2026-09-05', '2026-09-07');
        // check_out hari ini (8 Sep) -> INCLUDE (batas eksplisit)
        $today = $this->booking($apartment, BookingStatus::Confirmed, '2026-09-06', '2026-09-08');
        // check_out 10 Sep -> INCLUDE
        $future = $this->booking($apartment, BookingStatus::Confirmed, '2026-09-09', '2026-09-10');

        Carbon::setTestNow(Carbon::parse('2026-09-08 12:00:00', 'UTC'));

        try {
            $this->get('/apartments/'.$apartment->slug)
                ->assertOk()
                ->assertSee('Tanggal tidak tersedia (2 periode)')
                // hari ini & masa depan tetap tampil di kalender
                ->assertSee($today->check_in->format('Y-m-d'), false)
                ->assertSee($future->check_in->format('Y-m-d'), false)
                // booking masa lalu tidak dimuat ke halaman
                ->assertDontSee($past->check_in->format('Y-m-d'), false)
                ->assertDontSee($past->check_out->format('Y-m-d'), false);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_booked_dates_only_contains_blocking_statuses(): void
    {
        $apartment = $this->apartment();

        $confirmed = $this->booking($apartment, BookingStatus::Confirmed, '2026-09-10', '2026-09-12');
        // cancelled tidak memblokir kalender — semantics scopeBlocking
        $this->booking($apartment, BookingStatus::Cancelled, '2026-09-20', '2026-09-22');
        // expired juga tidak memblokir
        $this->booking($apartment, BookingStatus::Expired, '2026-09-24', '2026-09-26');
        // completed tidak memblokir — dan ini yang paling banyak menumpuk
        $this->booking($apartment, BookingStatus::Completed, '2026-09-01', '2026-09-03');

        Carbon::setTestNow(Carbon::parse('2026-09-08 12:00:00', 'UTC'));

        try {
            $this->get('/apartments/'.$apartment->slug)
                ->assertOk()
                ->assertSee('Tanggal tidak tersedia (1 periode)')
                ->assertSee($confirmed->check_in->format('Y-m-d'), false)
                // status non-blokir tetap tidak tampil — tidak ada status logic baru
                ->assertDontSee('2026-09-20', false)
                ->assertDontSee('2026-09-24', false)
                ->assertDontSee('2026-09-01', false);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_pending_within_payment_window_still_blocks_the_calendar(): void
    {
        $apartment = $this->apartment();

        // Pending yang masih dalam jendela pembayaran (dibuat barusan)
        // tetap memblokir tanggal — tanpa mempedulikan filter historis.
        $pending = $this->booking($apartment, BookingStatus::Pending, '2026-09-15', '2026-09-17');

        Carbon::setTestNow(Carbon::parse('2026-09-08 12:00:00', 'UTC'));

        try {
            $this->get('/apartments/'.$apartment->slug)
                ->assertOk()
                ->assertSee('Tanggal tidak tersedia (1 periode)')
                ->assertSee($pending->check_in->format('Y-m-d'), false);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_today_boundary_booking_still_blocks_conflict_checks_server_side(): void
    {
        $apartment = $this->apartment();

        // Booking dengan check_out HARI INI masih memegang tanggalnya: rentang
        // baru yang menabrak harus tetap ditolak server, walau filter kalender
        // hanya urusan tampilan. Adjacency hari yang sama tetap boleh.
        $this->booking($apartment, BookingStatus::Confirmed, '2026-09-06', '2026-09-08');

        Carbon::setTestNow(Carbon::parse('2026-09-08 12:00:00', 'UTC'));

        try {
            // scopeConflicting tetap melihat booking boundary hari ini:
            // rentang 06–09 masih menabrak wilayah 06–08.
            $this->assertTrue(
                Booking::conflicting($apartment->id, '2026-09-06', '2026-09-09')->exists(),
                'Booking dengan check_out hari ini masih memblokir rentang yang menabrak.',
            );

            // adjacency: check-in baru = check-out booking lama (8 Sep), bebas.
            $this->assertFalse(
                Booking::conflicting($apartment->id, '2026-09-08', '2026-09-10')->exists(),
                'Adjacency hari yang sama tetap diizinkan.',
            );

            // endpoint availability setuju dengan scope: valid & bebas
            $this->post('/apartments/'.$apartment->id.'/availability', [
                'check_in' => '2026-09-08',
                'check_out' => '2026-09-10',
            ])->assertOk()->assertJson(['available' => true]);
        } finally {
            Carbon::setTestNow();
        }
    }
}
