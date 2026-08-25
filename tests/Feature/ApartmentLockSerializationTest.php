<?php

namespace Tests\Feature;

use App\Models\Apartment;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Membuktikan mekanisme serialisasi per-unit yang diandalkan ketiga penilai
 * ketersediaan (BookingController::store, guard panel BookingResource, dan
 * PaymentStatusApplier): begitu satu transaksi memegang lock baris apartment,
 * transaksi lain pada koneksi terpisah HARUS menunggu — bukan melihat kalender
 * kosong lalu lolos bersamaan.
 *
 * Sebelum parent-row lock ada, jaminan ini hanya kebetulan perilaku gap-lock
 * InnoDB dan tidak pernah tereksekusi di suite SQLite karena SQLite menulis
 * secara serialisasi penuh. Row-level lock hanya ada di MySQL, jadi test ini
 * dilewati di driver lain dan dijalankan serius oleh job CI test-mysql.
 */
class ApartmentLockSerializationTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaksi_kedua_mengantre_ketika_lock_unit_dipegang(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Row-level locking hanya berlaku pada MySQL.');
        }

        /*
         * Kegagalan proses sebelumnya bisa meninggalkan baris probe yang sudah
         * ter-commit (koneksi seed berjalan autocommit). Bersihkan lebih dulu
         * supaya test yang menuntut katalog kosong tidak melihat sisa ini,
         * apa pun urutan eksekusinya.
         */
        $this->prepareProbeConnection();
        DB::connection('apartment-lock-seed')
            ->table('apartments')
            ->where('slug', 'like', 'lock-probe-%')
            ->delete();

        /*
         * RefreshDatabase membungkus setiap test dalam satu transaksi luar,
         * sehingga baris yang dibuat lewat koneksi utama BELUM ter-commit dan
         * mustahil dilihat koneksi probe. Karena itu seeding dilakukan lewat
         * koneksi kedua yang berjalan autocommit — datanya langsung nyata bagi
         * semua koneksi.
         */
        $apartmentId = DB::connection('apartment-lock-seed')->table('apartments')->insertGetId([
            'title' => 'Lock Probe Apartment',
            'slug' => 'lock-probe-'.Str::random(6),
            'description' => 'Unit untuk probe lock.',
            'price_per_night' => 500_000,
            'address' => 'Jl. Test No. 1',
            'city' => 'Jakarta',
            'bedrooms' => 1,
            'bathrooms' => 1,
            'area_sqm' => 30,
            'capacity' => 2,
            'main_image' => 'https://example.com/img.jpg',
            'is_featured' => false,
            'status' => 'available',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::transaction(function () use ($apartmentId): void {
            // Transaksi "tamu pertama": memegang lock baris unit.
            Apartment::whereKey($apartmentId)->lockForUpdate()->first();

            $probe = DB::connection('apartment-lock-probe');
            $probe->statement('SET SESSION innodb_lock_wait_timeout = 2');

            try {
                // Transaksi "tamu kedua" yang datang bersamaan: harus antre
                // pada lock yang sama, bukan lolos karena hasil query konflik
                // kosong.
                $probe->selectOne('select id from apartments where id = ? for update', [$apartmentId]);

                $this->fail('Koneksi kedua berhasil mengunci baris unit yang sedang dipegang — serialisasi per-unit rusak.');
            } catch (QueryException $e) {
                $this->assertSame(
                    1205,
                    (int) ($e->errorInfo[1] ?? 0),
                    'Diharapkan lock wait timeout (ER_LOCK_WAIT_TIMEOUT), mendapat: '.$e->getMessage(),
                );
            }
        });

        /*
         * Baris seed ter-commit (autocommit) sehingga rollback teardown tidak
         * menghapusnya. Penghapusan didaftarkan lewat hook yang berjalan
         * SETELAH callback rollback transaksi luar RefreshDatabase — saat itu
         * lock baris sudah benar-benar lepas, jadi koneksi seed bisa menghapus
         * tanpa mengantre 50 detik; dan karena di luar transaksi luar,
         * penghapusannya tidak ikut ter-roll-back.
         */
        $this->beforeApplicationDestroyed(function () use ($apartmentId): void {
            DB::connection('apartment-lock-seed')
                ->table('apartments')
                ->where('id', $apartmentId)
                ->delete();
        });
    }

    private function prepareProbeConnection(): void
    {
        $base = config('database.connections.'.config('database.default'));

        config(['database.connections.apartment-lock-probe' => $base]);
        config(['database.connections.apartment-lock-seed' => $base]);

        DB::purge('apartment-lock-probe');
        DB::purge('apartment-lock-seed');
    }
}
