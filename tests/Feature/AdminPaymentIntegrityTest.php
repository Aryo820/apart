<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\PaymentResource;
use App\Filament\Resources\PaymentResource\Pages\ListPayments;
use App\Models\Apartment;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentStatusApplier;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Payment adalah catatan dari Midtrans. Panel boleh membacanya, tidak boleh
 * menulisnya — kalau boleh, admin bisa membuat sebuah reservasi tampak lunas
 * tanpa uang yang pernah masuk, dan nominal pembanding yang dipakai
 * PaymentStatusApplier untuk menolak notifikasi palsu bisa digeser.
 *
 * Yang diuji di sini batas mutasinya, bukan ada/tidaknya tombol.
 */
class AdminPaymentIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);
    }

    private function payment(PaymentStatus $status = PaymentStatus::Pending): Payment
    {
        $apartment = Apartment::create([
            'title' => 'Payment Apartment',
            'slug' => 'payment-'.Str::random(6),
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

        $booking = Booking::create([
            'booking_code' => 'APT-'.date('Ymd').'-'.strtoupper(Str::random(5)),
            'user_id' => User::factory()->create(['role' => 'user'])->id,
            'apartment_id' => $apartment->id,
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(13)->toDateString(),
            'total_nights' => 3,
            'total_price' => 1500000,
            'status' => BookingStatus::Pending,
        ]);

        return Payment::create([
            'booking_id' => $booking->id,
            'order_id' => Payment::generateOrderId($booking->booking_code),
            'transaction_id' => 'TRX-ASLI',
            'gross_amount' => 1500000,
            'snap_token' => 'SNAP-TEST-TOKEN',
            'status' => $status,
        ]);
    }

    /** Daftar payment tetap bisa dibaca — yang hilang hanya jalur tulisnya. */
    public function test_admin_can_still_read_the_payment_list(): void
    {
        $payment = $this->payment();

        Livewire::test(ListPayments::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$payment])
            ->assertActionDoesNotExist(TestAction::make('edit')->table($payment))
            ->assertActionDoesNotExist(TestAction::make('delete')->table($payment))
            ->assertActionDoesNotExist(TestAction::make('create'));
    }

    /**
     * Panggilan action buatan sendiri — jalur yang tidak butuh tombol sama
     * sekali — tidak boleh mengubah satu kolom pun.
     */
    public function test_a_crafted_edit_call_cannot_change_payment_state(): void
    {
        $payment = $this->payment();
        $before = $payment->only(['status', 'gross_amount', 'booking_id', 'order_id', 'transaction_id']);

        try {
            Livewire::test(ListPayments::class)
                ->callAction(TestAction::make('edit')->table($payment), [
                    'status' => PaymentStatus::Settlement->value,
                    'gross_amount' => 1,
                    'transaction_id' => 'TRX-PALSU',
                ]);
        } catch (\Throwable) {
            // Tidak ada action yang bisa di-resolve. Itu memang hasil yang
            // diinginkan; yang menentukan tetap assertion di bawah.
        }

        $this->assertEquals($before, $payment->fresh()->only(array_keys($before)));
    }

    /** Tidak ada route panel yang menulis payment, jadi tidak ada URL untuk ditembak. */
    public function test_the_panel_registers_no_write_route_for_payments(): void
    {
        $this->assertTrue(Route::has('filament.admin.resources.payments.index'));
        $this->assertFalse(Route::has('filament.admin.resources.payments.create'));
        $this->assertFalse(Route::has('filament.admin.resources.payments.edit'));
    }

    /**
     * Batas sesungguhnya: Gate menolak setiap ability tulis, bahkan untuk admin.
     * Ini yang tetap berlaku ketika request tidak datang dari UI.
     */
    public function test_every_write_ability_is_denied_even_for_an_admin(): void
    {
        $payment = $this->payment();
        $gate = Gate::forUser($this->admin);

        $this->assertTrue($gate->allows('viewAny', Payment::class));
        $this->assertTrue($gate->allows('view', $payment));

        foreach (['create'] as $ability) {
            $this->assertTrue($gate->denies($ability, Payment::class), "{$ability} seharusnya ditolak");
        }

        foreach (['update', 'delete', 'restore', 'forceDelete'] as $ability) {
            $this->assertTrue($gate->denies($ability, $payment), "{$ability} seharusnya ditolak");
        }

        // Filament menanyakan ability yang sama sebelum me-resolve action apa pun.
        $this->assertFalse(PaymentResource::canCreate());
        $this->assertFalse(PaymentResource::canEdit($payment));
        $this->assertFalse(PaymentResource::canDelete($payment));
        $this->assertTrue(PaymentResource::canViewAny());
    }

    /**
     * Read-only itu untuk admin, bukan untuk gateway: jalur yang sudah
     * dikeraskan harus tetap bisa menulis status payment dan mengonfirmasi
     * booking-nya. Tanpa test ini, "read-only" bisa berarti membekukan
     * pembayaran yang sah.
     */
    public function test_the_gateway_path_can_still_write_payment_state(): void
    {
        $payment = $this->payment();

        $result = app(PaymentStatusApplier::class)->apply($payment->booking->booking_code, [
            'transaction_status' => 'settlement',
            'gross_amount' => '1500000.00',
            'transaction_id' => 'TRX-GATEWAY',
            'payment_type' => 'bank_transfer',
        ]);

        $this->assertSame('success', $result);
        $this->assertSame(PaymentStatus::Settlement, $payment->fresh()->status);
        $this->assertSame(BookingStatus::Confirmed, $payment->booking->fresh()->status);
    }
}
