<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\ApartmentResource\Pages\ListApartments;
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
use Tests\TestCase;

class AdminDeleteGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);
    }

    public function test_booking_history_cannot_be_deleted_with_or_without_a_payment(): void
    {
        $booking = $this->booking($this->apartment());
        $decision = Gate::inspect('delete', $booking);

        $this->assertFalse($decision->allowed());
        $this->assertSame('Riwayat reservasi tidak dapat dihapus dari panel admin.', $decision->message());

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'order_id' => Payment::generateOrderId($booking->booking_code),
            'gross_amount' => $booking->total_price,
            'snap_token' => 'SNAP-DELETE-GUARD',
            'status' => PaymentStatus::Pending,
        ]);

        $this->assertTrue(Gate::denies('delete', $booking->fresh()));
        $this->assertTrue(Gate::denies('delete', $payment));
        $this->assertNotNull($booking->fresh());
        $this->assertNotNull($payment->fresh());
    }

    public function test_referenced_apartment_user_and_facility_are_denied_with_clear_messages(): void
    {
        $apartment = $this->apartment();
        $booking = $this->booking($apartment);
        $facility = Facility::create(['name' => 'Wifi']);
        $apartment->facilities()->attach($facility);

        $this->assertSame(
            'Apartemen tidak dapat dihapus karena masih memiliki riwayat reservasi.',
            Gate::inspect('delete', $apartment)->message(),
        );
        $this->assertSame(
            'Pengguna tidak dapat dihapus karena masih memiliki riwayat reservasi.',
            Gate::inspect('delete', $booking->user)->message(),
        );
        $this->assertSame(
            'Fasilitas tidak dapat dihapus karena masih digunakan oleh apartemen.',
            Gate::inspect('delete', $facility)->message(),
        );
    }

    public function test_bulk_delete_checks_each_record_and_only_deletes_unreferenced_records(): void
    {
        $referenced = $this->apartment();
        $this->booking($referenced);
        $unreferenced = $this->apartment();

        Livewire::test(ListApartments::class)
            ->callTableBulkAction('delete', [$referenced, $unreferenced]);

        $this->assertNotNull($referenced->fresh());
        $this->assertNull($unreferenced->fresh());
    }

    public function test_bulk_delete_removes_all_records_when_every_record_is_unreferenced(): void
    {
        $first = $this->apartment();
        $second = $this->apartment();

        Livewire::test(ListApartments::class)
            ->callTableBulkAction('delete', [$first, $second]);

        $this->assertNull($first->fresh());
        $this->assertNull($second->fresh());
    }

    public function test_bulk_delete_keeps_all_records_when_every_record_is_protected(): void
    {
        $first = $this->apartment();
        $second = $this->apartment();
        $this->booking($first);
        $this->booking($second);

        Livewire::test(ListApartments::class)
            ->callTableBulkAction('delete', [$first, $second]);

        $this->assertNotNull($first->fresh());
        $this->assertNotNull($second->fresh());
    }

    public function test_single_delete_constraint_race_is_reported_without_deleting_history(): void
    {
        $apartment = $this->apartment();
        $booking = null;

        Apartment::deleting(function (Apartment $deleting) use (&$booking): void {
            $booking = $this->booking($deleting);
        });

        Livewire::test(ListApartments::class)
            ->callAction(TestAction::make('delete')->table($apartment))
            ->assertNotified('Penghapusan ditolak');

        $this->assertNotNull($apartment->fresh());
        $this->assertNull($booking->fresh());
    }

    public function test_an_unreferenced_record_can_still_be_deleted_when_policy_allows_it(): void
    {
        $apartment = $this->apartment();

        $this->assertTrue(Gate::allows('delete', $apartment));
        $this->assertTrue($apartment->delete());
        $this->assertNull($apartment->fresh());
    }

    public function test_non_admin_authorization_remains_denied(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $apartment = $this->apartment();

        $this->assertTrue(Gate::forUser($user)->denies('delete', $apartment));
        $this->assertTrue(Gate::forUser($user)->denies('deleteAny', Apartment::class));
        $this->assertTrue(Gate::forUser($user)->denies('deleteAny', Booking::class));
    }

    private function apartment(): Apartment
    {
        return Apartment::create([
            'title' => 'Delete Guard Apartment',
            'slug' => 'delete-guard-'.Str::random(8),
            'description' => 'Test',
            'price_per_night' => 500000,
            'address' => 'Jl. Test',
            'city' => 'Jakarta',
            'bedrooms' => 1,
            'bathrooms' => 1,
            'area_sqm' => 30,
            'capacity' => 2,
            'main_image' => 'test.jpg',
            'is_featured' => false,
            'status' => 'available',
        ]);
    }

    private function booking(Apartment $apartment): Booking
    {
        return Booking::create([
            'booking_code' => 'APT-'.now()->format('Ymd').'-'.strtoupper(Str::random(5)),
            'user_id' => User::factory()->create(['role' => 'user'])->id,
            'apartment_id' => $apartment->id,
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(12)->toDateString(),
            'total_nights' => 2,
            'total_price' => 1000000,
            'status' => BookingStatus::Pending,
        ]);
    }
}
