<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\PaymentResource\Pages\ListPayments;
use App\Models\Apartment;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * F-05 Batch 3.1: insiden finansial settled-but-unavailable punya durable
 * resolution lifecycle. Resolusi operator mencatat timestamp + note — TIDAK
 * menyentuh status gateway (Settlement) maupun status booking. Insiden
 * resolved keluar dari daftar kerja admin tapi tetap teridentifikasi
 * sebagai evidence historis (scopeFinancialIncident).
 */
class FinancialIncidentResolutionTest extends TestCase
{
    use RefreshDatabase;

    private function apartment(): Apartment
    {
        return Apartment::create([
            'title' => 'Resolution Apartment',
            'slug' => 'resolution-'.Str::random(6),
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

    private function payment(BookingStatus $bookingStatus): Payment
    {
        $booking = Booking::create([
            'booking_code' => 'APT-'.date('Ymd').'-'.strtoupper(Str::random(5)),
            'user_id' => User::factory()->create(['role' => 'user'])->id,
            'apartment_id' => $this->apartment()->id,
            'check_in' => now()->addDays(2)->toDateString(),
            'check_out' => now()->addDays(4)->toDateString(),
            'total_nights' => 2,
            'total_price' => 1000000,
            'status' => $bookingStatus,
        ]);

        return Payment::create([
            'booking_id' => $booking->id,
            'gross_amount' => 1000000,
            'snap_token' => 'SNAP-TEST-TOKEN',
            'status' => PaymentStatus::Settlement,
        ]);
    }

    /** T1 — insiden belum ditindak terdeteksi sebagai unresolved. */
    public function test_t1_unresolved_incident_needs_attention(): void
    {
        $payment = $this->payment(BookingStatus::Expired);

        $this->assertNull($payment->attention_resolved_at);
        $this->assertTrue($payment->isNeedingAttention());
        $this->assertSame(1, Payment::needsAttention()->count());
        $this->assertSame(1, Payment::financialIncident()->count());
    }

    /** T2 — resolve hanya mencatat; gateway & booking status tidak berubah. */
    public function test_t2_resolve_keeps_gateway_and_booking_status(): void
    {
        $payment = $this->payment(BookingStatus::Expired);
        $booking = $payment->booking;

        $resolved = $payment->markAttentionResolved('Refund manual Midtrans selesai. Reference: REF-123.');

        $this->assertTrue($resolved);
        $this->assertNotNull($payment->fresh()->attention_resolved_at);
        $this->assertSame('Refund manual Midtrans selesai. Reference: REF-123.', $payment->fresh()->attention_resolution_note);

        // Gateway state & booking state TIDAK boleh tersentuh.
        $this->assertSame(PaymentStatus::Settlement, $payment->fresh()->status);
        $this->assertSame(BookingStatus::Expired, $booking->fresh()->status);
    }

    /** T3 — setelah resolve, payment keluar dari daftar unresolved admin. */
    public function test_t3_resolved_incident_leaves_needs_attention_query(): void
    {
        $payment = $this->payment(BookingStatus::Expired);

        $payment->markAttentionResolved('Refund selesai via dashboard Midtrans.');

        $this->assertSame(0, Payment::needsAttention()->count());
        $this->assertFalse($payment->fresh()->isNeedingAttention());
    }

    /** T4 — bukti historis tetap teridentifikasi lewat predicate dasar. */
    public function test_t4_resolved_incident_remains_identifiable_as_history(): void
    {
        $payment = $this->payment(BookingStatus::Expired);
        $normal = $this->payment(BookingStatus::Confirmed);

        $payment->markAttentionResolved('Refund manual selesai.');

        // Insiden resolved TIDAK hilang dari evidence historis...
        $incidents = Payment::financialIncident()->get();
        $this->assertSame(1, $incidents->count());
        $this->assertSame($payment->id, $incidents->first()->id);
        // ...sementara settlement normal tidak pernah dianggap insiden.
        $this->assertFalse($incidents->contains(fn (Payment $p) => $p->id === $normal->id));
    }

    /** T5 — payment normal tidak bisa di-resolve sebagai insiden. */
    public function test_t5_normal_settlement_cannot_be_resolved(): void
    {
        $payment = $this->payment(BookingStatus::Confirmed);

        $this->assertFalse($payment->isNeedingAttention());
        $this->assertFalse($payment->markAttentionResolved('bukan insiden'));

        $this->assertNull($payment->fresh()->attention_resolved_at);
        $this->assertNull($payment->fresh()->attention_resolution_note);
    }

    /** T6 — non-admin tidak diizinkan melakukan resolusi (Gate). */
    public function test_t6_non_admin_cannot_resolve(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $payment = $this->payment(BookingStatus::Expired);

        $this->assertTrue(
            Gate::forUser($user)->denies('resolveAttention', $payment),
        );

        $admin = User::factory()->create(['role' => 'admin']);
        $this->assertTrue(
            Gate::forUser($admin)->allows('resolveAttention', $payment),
        );
    }

    /** T7 — resolve kedua ditolak gracefully, tidak merusak state. */
    public function test_t7_double_resolve_is_rejected_without_state_corruption(): void
    {
        $payment = $this->payment(BookingStatus::Expired);

        $first = $payment->markAttentionResolved('Refund selesai. Reference: REF-1.');
        $this->assertTrue($first);

        $resolvedAtFirst = $payment->fresh()->attention_resolved_at;

        // Resolve kedua: idempotent — tidak menggeser timestamp resolusi pertama.
        $this->travel(1)->hours();
        $second = $payment->fresh()->markAttentionResolved('Coba resolve lagi.');
        $this->assertFalse($second);

        $fresh = $payment->fresh();
        $this->assertSame($resolvedAtFirst->toDateTimeString(), $fresh->attention_resolved_at->toDateTimeString());
        $this->assertSame('Refund selesai. Reference: REF-1.', $fresh->attention_resolution_note);
    }

    /** T8 — catatan resolusi tersimpan via action panel & divalidasi panjangnya. */
    public function test_t8_admin_action_saves_note_and_validates_length(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $payment = $this->payment(BookingStatus::Expired);

        Livewire::test(ListPayments::class)
            ->callAction(
                TestAction::make('markAttentionResolved')->table($payment),
                ['note' => 'Refund manual Midtrans selesai. Reference: REF-99.'],
            )
            ->assertHasNoActionErrors();

        $fresh = $payment->fresh();
        $this->assertNotNull($fresh->attention_resolved_at);
        $this->assertSame('Refund manual Midtrans selesai. Reference: REF-99.', $fresh->attention_resolution_note);
        $this->assertSame(PaymentStatus::Settlement, $fresh->status);
        $this->assertSame(BookingStatus::Expired, $fresh->booking->status);
    }

    /** Validasi panel: note kosong ditolak oleh form action. */
    public function test_t8b_action_requires_a_note(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $payment = $this->payment(BookingStatus::Expired);

        Livewire::test(ListPayments::class)
            ->callAction(
                TestAction::make('markAttentionResolved')->table($payment),
                ['note' => ''],
            )
            ->assertHasActionErrors(['note']);

        // Tidak ada penulisan separuh — resolusi atomik.
        $this->assertNull($payment->fresh()->attention_resolved_at);
    }

    /** Action tidak terlihat untuk payment normal (visible=false) — jalur UI. */
    public function test_action_is_hidden_for_resolved_and_normal_payments(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $incident = $this->payment(BookingStatus::Expired);
        $normal = $this->payment(BookingStatus::Confirmed);

        Livewire::test(ListPayments::class)
            ->assertActionVisible(TestAction::make('markAttentionResolved')->table($incident))
            ->assertActionHidden(TestAction::make('markAttentionResolved')->table($normal));

        $incident->markAttentionResolved('selesai');
        Livewire::test(ListPayments::class)
            ->assertActionHidden(TestAction::make('markAttentionResolved')->table($incident));
    }
}
