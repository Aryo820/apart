<?php

namespace Tests\Unit;

use App\Exceptions\PaymentGatewayException;
use App\Services\MidtransService;
use Tests\TestCase;

/**
 * Invariant nominal pembayaran: SDK Midtrans menghitung ulang gross_amount
 * dari item_details (floor(total / malam) × malam), jadi tarif harus positif
 * dan habis dibagi jumlah malam — kalau tidak, tamu tertagih selisih sementara
 * payments.gross_amount mencatat total dan settlement-nya ditolak selamanya.
 */
class MidtransChargeAmountTest extends TestCase
{
    public function test_tarif_rupiah_bulat_ditagih_persis_totalnya(): void
    {
        $this->assertSame(2_550_000, MidtransService::chargeAmount(850_000 * 3, 3));
        $this->assertSame(500_000, MidtransService::chargeAmount(500_000.0, 1));
        $this->assertSame(1_250_000, MidtransService::chargeAmount(625_000.0 * 2, 2));
    }

    public function test_menginap_satu_malam_tetap_lolos_invariant(): void
    {
        $this->assertSame(1_000_000, MidtransService::chargeAmount(1_000_000, 1));
    }

    public function test_jumlah_malam_nol_atau_negatif_diperlakukan_sebagai_satu_malam(): void
    {
        $this->assertSame(750_000, MidtransService::chargeAmount(750_000, 0));
    }

    public function test_total_pecahan_ditolak_keras(): void
    {
        $this->expectException(PaymentGatewayException::class);

        // floor(200001 / 2) × 2 = 200000 — tamu tertagih Rp 1 lebih sedikit
        // dari yang dicatat lokal.
        MidtransService::chargeAmount(200_001.0, 2);
    }

    public function test_total_pecahan_akibat_tarif_desimal_ditolak_keras(): void
    {
        $this->expectException(PaymentGatewayException::class);

        // Tarif 100000.50 × 3 malam = 300001.50 → int 300001, tidak habis
        // dibagi 3 (sisa 1).
        MidtransService::chargeAmount(300_001.5, 3);
    }

    public function test_total_nol_ditolak(): void
    {
        $this->expectException(PaymentGatewayException::class);

        MidtransService::chargeAmount(0, 2);
    }

    public function test_total_negatif_ditolak(): void
    {
        $this->expectException(PaymentGatewayException::class);

        MidtransService::chargeAmount(-150_000, 3);
    }
}
