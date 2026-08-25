<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\ApartmentResource;
use App\Filament\Resources\BookingResource;
use App\Filament\Resources\BookingResource\Pages\ListBookings;
use App\Filament\Resources\FacilityResource;
use App\Filament\Resources\PaymentResource;
use App\Filament\Resources\PaymentResource\Pages\ListPayments;
use App\Filament\Resources\UserResource;
use App\Models\Apartment;
use App\Models\Booking;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

/**
 * Authorization panel harus gagal TERTUTUP.
 *
 * Di luar strict mode, Filament memperlakukan ability yang tidak punya method di
 * policy sebagai ALLOW (vendor/filament/filament/src/helpers.php) — kebalikan
 * dari Laravel Gate. Artinya setiap ability yang belum pernah diputuskan
 * siapa pun, terutama deleteAny yang dipakai aksi massal, terbuka begitu saja.
 *
 * Test ini menjaga dua hal: ability yang memang dipakai panel punya keputusan
 * eksplisit, dan ability yang tidak dikenal tidak pernah menjadi izin.
 */
class PanelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    /** @return array<string, class-string> */
    public static function resources(): array
    {
        return [
            'bookings' => BookingResource::class,
            'payments' => PaymentResource::class,
            'apartments' => ApartmentResource::class,
            'users' => UserResource::class,
            'facilities' => FacilityResource::class,
        ];
    }

    /**
     * Setiap halaman panel dirender sebagai admin. Ini sekaligus jaring
     * penangkap strict mode: ability yang dipakai halaman tapi tidak
     * terdefinisi di policy akan melempar LogicException di sini, bukan di
     * hadapan admin.
     */
    public function test_admin_can_open_every_panel_page(): void
    {
        $apartment = $this->apartment();
        $booking = $this->booking($apartment);
        $facility = Facility::create(['name' => 'Wifi', 'icon' => 'wifi']);

        $urls = [
            '/admin',
            ...array_map(fn (string $resource) => $resource::getUrl('index'), static::resources()),
            BookingResource::getUrl('create'),
            BookingResource::getUrl('edit', ['record' => $booking]),
            ApartmentResource::getUrl('create'),
            ApartmentResource::getUrl('edit', ['record' => $apartment]),
            UserResource::getUrl('create'),
            UserResource::getUrl('edit', ['record' => $this->admin]),
            FacilityResource::getUrl('create'),
            FacilityResource::getUrl('edit', ['record' => $facility]),
        ];

        foreach ($urls as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    /** Panel tetap tertutup untuk user biasa, di setiap URL-nya. */
    public function test_a_non_admin_cannot_reach_any_panel_page(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $apartment = $this->apartment();
        $booking = $this->booking($apartment);
        $facility = Facility::create(['name' => 'Restricted Wifi']);

        $urls = [
            '/admin',
            ...array_map(fn (string $resource) => $resource::getUrl('index'), static::resources()),
            BookingResource::getUrl('create'),
            BookingResource::getUrl('edit', ['record' => $booking]),
            ApartmentResource::getUrl('create'),
            ApartmentResource::getUrl('edit', ['record' => $apartment]),
            UserResource::getUrl('create'),
            UserResource::getUrl('edit', ['record' => $this->admin]),
            FacilityResource::getUrl('create'),
            FacilityResource::getUrl('edit', ['record' => $facility]),
        ];

        foreach ($urls as $url) {
            $this->actingAs($user)->get($url)->assertForbidden();
        }
    }

    /** Tamu diarahkan ke login panel dari setiap resource, bukan diberi halaman. */
    public function test_a_guest_is_sent_to_the_panel_login(): void
    {
        $apartment = $this->apartment();
        $booking = $this->booking($apartment);
        $facility = Facility::create(['name' => 'Guest Wifi']);

        $urls = [
            '/admin',
            ...array_map(fn (string $resource) => $resource::getUrl('index'), static::resources()),
            BookingResource::getUrl('create'),
            BookingResource::getUrl('edit', ['record' => $booking]),
            ApartmentResource::getUrl('create'),
            ApartmentResource::getUrl('edit', ['record' => $apartment]),
            UserResource::getUrl('create'),
            UserResource::getUrl('edit', ['record' => $this->admin]),
            FacilityResource::getUrl('create'),
            FacilityResource::getUrl('edit', ['record' => $facility]),
        ];

        auth()->logout();

        foreach ($urls as $url) {
            $this->get($url)->assertRedirect(route('filament.admin.auth.login'));
        }
    }

    /**
     * Ability yang tidak dikenal tidak boleh menjadi izin. Strict mode
     * mengubahnya menjadi LogicException — gagal keras, bukan gagal diam-diam.
     */
    public function test_an_undefined_ability_fails_closed(): void
    {
        $this->actingAs($this->admin);

        $this->expectException(LogicException::class);

        BookingResource::can('someAbilityNobodyDecided');
    }

    /** Ability tulis payment ditolak eksplisit, termasuk yang massal. */
    public function test_payment_denies_every_write_ability_including_bulk(): void
    {
        // PaymentResource::can*() bertanya lewat Filament::auth()->user(),
        // bukan lewat user yang diberikan ke Gate — jadi keduanya harus disetel.
        $this->actingAs($this->admin);

        $payment = $this->payment();
        $gate = Gate::forUser($this->admin);

        $this->assertTrue($gate->allows('viewAny', Payment::class));
        $this->assertTrue($gate->allows('view', $payment));

        foreach (['create', 'deleteAny', 'restoreAny', 'forceDeleteAny', 'reorder'] as $ability) {
            $this->assertTrue($gate->denies($ability, Payment::class), "{$ability} harus ditolak");
        }

        foreach (['update', 'delete', 'restore', 'forceDelete', 'replicate'] as $ability) {
            $this->assertTrue($gate->denies($ability, $payment), "{$ability} harus ditolak");
        }

        $this->assertFalse(PaymentResource::canDeleteAny());
        $this->assertFalse(PaymentResource::canCreate());
        $this->assertFalse(PaymentResource::canEdit($payment));
        $this->assertFalse(PaymentResource::canDelete($payment));
        $this->assertTrue(PaymentResource::canViewAny());
    }

    /**
     * Booking: kemampuan yang memang dipakai panel diizinkan untuk admin dan
     * ditolak untuk user biasa — termasuk deleteAny, yang sebelumnya tidak
     * pernah diputuskan siapa pun dan karena itu terbuka.
     */
    public function test_booking_abilities_are_explicit_for_admin_and_closed_for_users(): void
    {
        $booking = $this->booking($this->apartment());
        $user = User::factory()->create(['role' => 'user']);

        $admin = Gate::forUser($this->admin);
        $this->assertTrue($admin->allows('viewAny', Booking::class));
        $this->assertTrue($admin->allows('create', Booking::class));
        $this->assertTrue($admin->denies('deleteAny', Booking::class));
        $this->assertTrue($admin->allows('view', $booking));
        $this->assertTrue($admin->allows('update', $booking));
        $this->assertTrue($admin->denies('delete', $booking));

        // Tidak dipakai jalur mana pun, jadi ditolak — bukan dibiarkan terbuka.
        $this->assertTrue($admin->denies('replicate', $booking));
        $this->assertTrue($admin->denies('reorder', Booking::class));
        $this->assertTrue($admin->denies('restoreAny', Booking::class));
        $this->assertTrue($admin->denies('forceDeleteAny', Booking::class));

        $guest = Gate::forUser($user);
        foreach (['viewAny', 'create', 'deleteAny'] as $ability) {
            $this->assertTrue($guest->denies($ability, Booking::class), "{$ability} harus ditolak untuk user");
        }
        foreach (['view', 'update', 'delete'] as $ability) {
            $this->assertTrue($guest->denies($ability, $booking), "{$ability} harus ditolak untuk user");
        }
    }

    /** Aksi massal yang terdaftar tetap berfungsi; payment tidak punya satu pun. */
    public function test_registered_bulk_delete_stays_available_and_payment_has_none(): void
    {
        $this->actingAs($this->admin);
        $booking = $this->booking($this->apartment());
        $payment = $this->payment();

        $this->assertFalse(BookingResource::canDeleteAny());
        $this->assertTrue(ApartmentResource::canDeleteAny());
        $this->assertTrue(FacilityResource::canDeleteAny());
        $this->assertFalse(PaymentResource::canDeleteAny());

        Livewire::test(ListBookings::class)
            ->assertActionDoesNotExist(TestAction::make('delete')->table()->bulk())
            ->assertActionDoesNotExist(TestAction::make('delete')->table($booking));

        Livewire::test(ListPayments::class)
            ->assertActionDoesNotExist(TestAction::make('delete')->table()->bulk())
            ->assertActionDoesNotExist(TestAction::make('delete')->table($payment));

        $this->assertNotNull($booking->fresh());
    }

    private function apartment(): Apartment
    {
        return Apartment::create([
            'title' => 'Panel Apartment',
            'slug' => 'panel-'.Str::random(6),
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

    private function booking(Apartment $apartment): Booking
    {
        return Booking::create([
            'booking_code' => 'APT-'.date('Ymd').'-'.strtoupper(Str::random(5)),
            'user_id' => User::factory()->create(['role' => 'user'])->id,
            'apartment_id' => $apartment->id,
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(15)->toDateString(),
            'total_nights' => 5,
            'total_price' => 2500000,
            'status' => BookingStatus::Pending,
        ]);
    }

    private function payment(): Payment
    {
        $booking = $this->booking($this->apartment());

        return Payment::create([
            'booking_id' => $booking->id,
            'order_id' => Payment::generateOrderId($booking->booking_code),
            'gross_amount' => $booking->total_price,
            'snap_token' => 'SNAP-TEST-TOKEN',
            'status' => PaymentStatus::Pending,
        ]);
    }
}
