<?php

namespace Tests\Feature;

use App\Console\Commands\ExpirePendingBookings;
use App\Models\Apartment;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\PaymentStatusApplier;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regresi konkurensi di atas MySQL/InnoDB sungguhan.
 *
 * ApartmentLockSerializationTest membuktikan SATU bata (antre pada baris
 * unit). Test ini membuktikan bangunannya: persilangan-persilangan penulis
 * yang paling berbahaya bagi integritas booking, dieksekusi sebagai transaksi
 * nyata pada koneksi terpisah dengan interleaving yang dikendalikan — bukan
 * simulasi, karena lock wait dan pembacaan-versi terkunci hanya ada di InnoDB
 * dan tidak pernah tereksekusi di suite SQLite.
 *
 * Kontrak urutan kunci yang diuji tetap di sini (sama seperti komentar di
 * PaymentStatusApplier dan BookingController::store):
 *
 *     Apartment -> Booking -> rentang rival -> Payment
 *
 * Catatan pengukuran: RefreshDatabase membungkus tiap test dalam satu
 * transaksi luar, sehingga sesi default berjalan REPEATABLE READ dengan read
 * view yang bisa lebih tua daripada commit koneksi probe. Karena itu semua
 * assertion pasca-commit dibaca lewat koneksi probe autocommit — dan justru
 * di sanalah nilai regresinya: jalur produksi sengaja membaca ULANG dengan
 * lockForUpdate persis untuk kebal terhadap snapshot usang semacam ini.
 */
class MysqlConcurrencyRegressionTest extends TestCase
{
    use RefreshDatabase;

    private const PRICE = 500_000;

    public function test_dua_tamu_bersaing_pada_rentang_sama_menghasilkan_satu_booking(): void
    {
        if (! $this->onMysql()) {
            return;
        }

        $this->prepareProbeConnections();

        $apartmentId = $this->seedApartment();
        $userId = $this->seedUserId();

        /*
         * Fase 1 — tamu A memegang lock baris unit SEBELUM booking-nya ada.
         * Persis jendela rapuh di BookingController::store: kalau tamu B bisa
         * lolos lewat di sini (kalender masih kosong), double booking lahir.
         */
        $guestA = DB::connection('mysql-regression-a');
        $guestA->statement('SET SESSION innodb_lock_wait_timeout = 5');
        $guestA->statement('START TRANSACTION');

        // Sama seperti worker lain: transaksi tamu A wajib ditutup apa pun
        // hasil testnya, agar tidak ada sesi berisi lock yang menggantung.
        $guestACommitted = false;
        try {
            $guestA->selectOne('select id from apartments where id = ? for update', [$apartmentId]);

            /*
             * Fase 2 — tamu B datang bersamaan: harus ANTRE pada baris unit
             * (1205 saat A belum selesai), bukan melihat kalender kosong lalu
             * lolos bersamaan.
             */
            $guestB = DB::connection('mysql-regression-b');
            $guestB->statement('SET SESSION innodb_lock_wait_timeout = 2');

            try {
                $guestB->selectOne('select id from apartments where id = ? for update', [$apartmentId]);
                $this->fail('Tamu B lolos padahal tamu A memegang lock unit — serialisasi per-unit rusak.');
            } catch (DeadlockException|QueryException $e) {
                $this->assertQueuedOnLock($e);
            }

            /*
             * Fase 3 — tamu A menyelesaikan store()-nya: booking Pending yang
             * memblokir rentang, plus payment-nya, lalu commit.
             */
            $checkIn = now()->addDays(10)->toDateString();
            $checkOut = now()->addDays(13)->toDateString();
            $winnerCode = $this->insertGuestBooking($guestA, $apartmentId, $userId, $checkIn, $checkOut);
            $guestA->statement('COMMIT');
            $guestACommitted = true;
        } finally {
            if (! $guestACommitted) {
                try {
                    $guestA->statement('ROLLBACK');
                } catch (\Throwable) {
                    // Sesi sudah mati — tidak ada lagi yang bisa dibersihkan.
                }
            }
        }

        /*
         * Fase 4 — giliran tamu B: jalur store yang sama, kini lock langsung
         * didapat dan pemeriksaan rival WAJIB melihat booking A.
         */
        $conflict = DB::transaction(function () use ($apartmentId, $checkIn, $checkOut): bool {
            Apartment::whereKey($apartmentId)->lockForUpdate()->first();

            return Booking::conflicting($apartmentId, $checkIn, $checkOut)
                ->lockForUpdate()
                ->exists();
        });

        $this->assertTrue($conflict, 'Pemeriksaan konflik gagal melihat booking yang baru di-commit tamu A.');

        // Hitungan di-scope per unit: database test MySQL dipakai bersama,
        // jadi hitungan global rapuh terhadap sisa baris dari run yang gagal.
        $this->assertSame(
            1,
            $this->scalarOnProbe(
                fn ($q) => $q->from('payments')
                    ->whereIn('booking_id', fn ($inner) => $inner->select('id')->from('bookings')->where('apartment_id', $apartmentId))
                    ->count(),
            ),
            'Hanya satu dari dua tamu bersaing boleh punya payment.',
        );
        $this->assertSame(
            1,
            $this->scalarOnProbe(fn ($q) => $q->from('bookings')->where('apartment_id', $apartmentId)->count()),
            'Hanya satu dari dua tamu bersaing boleh mendapat reservasi.',
        );

        $this->cleanupLater(apartmentId: $apartmentId);
    }

    /**
     * Persilangan command expire vs webhook settlement, arah "command lebih
     * dulu": apply() webhook wajib mengantre pada baris booking (1205), lalu
     * SETELAH command commit, settlement terlambat tetap diproses menurut
     * aturan — tanggal sudah bebas, uangnya nyata => Confirmed.
     *
     * Jalur pemulihan ini hanya benar karena applyBookingStatus membaca ulang
     * baris booking lewat lockForUpdate: read view REPEATABLE READ milik
     * sesi webhook masih menunjukkan status lama, sedangkan pembacaan
     * berkunci selalu melihat versi termutakhir. Kalau suatu saat ada yang
     * "merapikan" kode dengan membuang pembacaan ulang itu, test ini merah.
     */
    public function test_webhook_mengantre_ketika_command_expire_memegang_baris_booking(): void
    {
        if (! $this->onMysql()) {
            return;
        }

        $this->prepareProbeConnections();

        $apartmentId = $this->seedApartment();
        $userId = $this->seedUserId();
        $overdueCode = $this->seedBookedAndPaid($apartmentId, $userId, overdue: true);
        $payload = $this->settlementPayload($overdueCode);

        $commandWorker = DB::connection('mysql-regression-a');
        $commandWorker->statement('SET SESSION innodb_lock_wait_timeout = 5');
        $commandWorker->statement('START TRANSACTION');

        /*
         * Transaksi worker wajib ditutup apa pun yang terjadi: assertion yang
         * gagal di tengah tidak boleh meninggalkan sesi berisi lock — sisa itu
         * menggantung test-test berikutnya dengan lock wait timeout.
         */
        $workerClosed = false;
        try {
            // Urutan penulisan command: baris booking dulu, baru payment-nya.
            $commandWorker->selectOne('select id from bookings where booking_code = ? for update', [$overdueCode]);
            $commandWorker->update('update bookings set status = ?, updated_at = ? where booking_code = ?', [
                'expired',
                now(),
                $overdueCode,
            ]);
            $commandWorker->update('update payments p join bookings b on b.id = p.booking_id set p.status = ?, p.updated_at = ? where b.booking_code = ?', [
                'expire',
                now(),
                $overdueCode,
            ]);

            // Webhook datang DI TENGAH transaksi command: wajib antre, bukan
            // membaca status lama lalu menimpanya.
            DB::statement('SET SESSION innodb_lock_wait_timeout = 2');
            try {
                app(PaymentStatusApplier::class)->apply($overdueCode, $payload);
                $this->fail('Webhook lolos menimpa status di tengah transaksi command expire.');
            } catch (DeadlockException|QueryException $e) {
                $this->assertQueuedOnLock($e);
            } finally {
                DB::statement('SET SESSION innodb_lock_wait_timeout = 50');
            }

            $commandWorker->statement('COMMIT');
            $workerClosed = true;
        } finally {
            if (! $workerClosed) {
                try {
                    $commandWorker->statement('ROLLBACK');
                } catch (\Throwable) {
                    // Sesi sudah mati — tidak ada lagi yang bisa dibersihkan.
                }
            }
        }

        // Setelah command commit: tanggal sudah bebas, uangnya masuk —
        // settlement terlambat dihormati (aturan P0-1 versi menguntungkan).
        $result = app(PaymentStatusApplier::class)->apply($overdueCode, $payload);

        $this->assertSame('success', $result);
        $this->assertSame('confirmed', $this->bookingStatusOf($overdueCode));
        $this->assertSame('settlement', $this->paymentStatusOf($overdueCode));

        $this->cleanupLater(apartmentId: $apartmentId);
    }

    /**
     * Arah sebaliknya: webhook meng-commit settlement duluan, command expire
     * yang menyusul harus melewati baris itu tanpa mengubah apa pun.
     */
    public function test_command_expire_melewati_booking_yang_sudah_disettle_webhook(): void
    {
        if (! $this->onMysql()) {
            return;
        }

        $this->prepareProbeConnections();

        $apartmentId = $this->seedApartment();
        $userId = $this->seedUserId();
        $code = $this->seedBookedAndPaid($apartmentId, $userId, overdue: true);

        // Webhook "menang" balapan: settlement ter-commit sebelum command
        // sempat membaca apa pun.
        $settler = DB::connection('mysql-regression-b');
        $settlerClosed = false;
        try {
            $settler->statement('START TRANSACTION');
            $settler->update('update bookings set status = ?, updated_at = ? where booking_code = ?', [
                'confirmed',
                now(),
                $code,
            ]);
            $settler->update('update payments p join bookings b on b.id = p.booking_id set p.status = ?, p.updated_at = ? where b.booking_code = ?', [
                'settlement',
                now(),
                $code,
            ]);
            $settler->statement('COMMIT');
            $settlerClosed = true;
        } finally {
            if (! $settlerClosed) {
                try {
                    $settler->statement('ROLLBACK');
                } catch (\Throwable) {
                    // Sesi sudah mati — tidak ada lagi yang bisa dibersihkan.
                }
            }
        }

        $this->artisan(ExpirePendingBookings::class)
            ->expectsOutputToContain('Booking expired: 0')
            ->assertSuccessful();

        $this->assertSame('confirmed', $this->bookingStatusOf($code), 'Command tidak boleh menimpa hasil webhook.');
        $this->assertSame('settlement', $this->paymentStatusOf($code));

        $this->cleanupLater(apartmentId: $apartmentId);
    }

    /**
     * Notifikasi ganda pada satu sesi logis: guard nominal menolak payload
     * yang jumlahnya tidak cocok tanpa efek, settlement diproses tepat sekali,
     * dan notifikasi berikutnya terminal (already_processed). Versi kontensi
     * (dua penulis hampir bersamaan) dites di test ketiga.
     */
    public function test_settlement_ganda_dan_nominal_beda_hanya_berdampak_sekali(): void
    {
        if (! $this->onMysql()) {
            return;
        }

        $this->prepareProbeConnections();

        $apartmentId = $this->seedApartment();
        $userId = $this->seedUserId();
        $code = $this->seedBookedAndPaid($apartmentId, $userId, overdue: false);

        // Nominal beda: ditolak keras, tidak ada efek ke status mana pun.
        $tampered = $this->settlementPayload($code, '999999.00');
        $this->assertSame('amount_mismatch', app(PaymentStatusApplier::class)->apply($code, $tampered));
        $this->assertSame('pending', $this->bookingStatusOf($code));
        $this->assertSame('pending', $this->paymentStatusOf($code));

        // Settlement pertama: satu-satunya mutasi.
        $payload = $this->settlementPayload($code);
        $this->assertSame('success', app(PaymentStatusApplier::class)->apply($code, $payload));
        $this->assertSame('confirmed', $this->bookingStatusOf($code));
        $this->assertSame('settlement', $this->paymentStatusOf($code));

        // Notifikasi yang sama datang lagi: terminal, bukan mutasi kedua.
        $this->assertSame('already_processed', app(PaymentStatusApplier::class)->apply($code, $payload));
        $this->assertSame('confirmed', $this->bookingStatusOf($code));
        $this->assertSame('settlement', $this->paymentStatusOf($code));

        $this->cleanupLater(apartmentId: $apartmentId);
    }

    /**
     * Dua penulis hampir bersamaan pada satu payment: penulis pertama memegang
     * baris booking + payment (urutan kontrak), penulis kedua mengantre (1205),
     * dan penulis pertama yang di-ROLLBACK tidak meninggalkan jejak apa pun —
     * status tetap persis seperti sebelum percobaan.
     */
    public function test_penulis_ganda_terserialisasi_dan_rollback_tidak_berjejak(): void
    {
        if (! $this->onMysql()) {
            return;
        }

        $this->prepareProbeConnections();

        $apartmentId = $this->seedApartment();
        $userId = $this->seedUserId();
        $code = $this->seedBookedAndPaid($apartmentId, $userId, overdue: false);

        /*
         * Penulis pertama: mengunci dalam URUTAN KONTRAK — booking dulu,
         * payment kemudian. Keduanya dipegang sampai akhir transaksi, jadi
         * dari luar ia tampak atomik. finally menjamin ROLLBACK selalu jalan
         * (happy path maupun assertion yang gagal lebih dulu) supaya tidak ada
         * sesi berisi lock yang menggantung test lain.
         */
        $firstWriter = DB::connection('mysql-regression-a');
        $firstWriter->statement('SET SESSION innodb_lock_wait_timeout = 5');
        try {
            $firstWriter->statement('START TRANSACTION');
            $firstWriter->update('update bookings set status = ?, updated_at = ? where booking_code = ?', [
                'confirmed',
                now(),
                $code,
            ]);
            $firstWriter->update('update payments p join bookings b on b.id = p.booking_id set p.status = ?, p.updated_at = ? where b.booking_code = ?', [
                'settlement',
                now(),
                $code,
            ]);

            // Penulis kedua (retry webhook atau rekonsiliasi) mengantre pada
            // baris booking — titik temu semua penulis dalam urutan kontrak.
            DB::statement('SET SESSION innodb_lock_wait_timeout = 2');
            try {
                app(PaymentStatusApplier::class)->apply($code, $this->settlementPayload($code));
                $this->fail('Penulis kedua berhasil menulis paralel dengan penulis pertama.');
            } catch (DeadlockException|QueryException $e) {
                $this->assertQueuedOnLock($e);
            } finally {
                DB::statement('SET SESSION innodb_lock_wait_timeout = 50');
            }
        } finally {
            // Penulis pertama GAGAL di tingkat aplikasi dan di-roll back:
            // status harus kembali persis seperti sebelum percobaan.
            try {
                $firstWriter->statement('ROLLBACK');
            } catch (\Throwable) {
                // Sesi sudah mati — tidak ada lagi yang bisa dibersihkan.
            }
        }

        $this->assertSame('pending', $this->bookingStatusOf($code), 'Penulis yang gagal tidak boleh meninggalkan jejak.');
        $this->assertSame('pending', $this->paymentStatusOf($code));

        // Setelah lock lepas, penulis yang sah menang tanpa hambatan.
        $this->assertSame(
            'success',
            app(PaymentStatusApplier::class)->apply($code, $this->settlementPayload($code)),
        );
        $this->assertSame('confirmed', $this->bookingStatusOf($code));
        $this->assertSame('settlement', $this->paymentStatusOf($code));

        $this->cleanupLater(apartmentId: $apartmentId);
    }

    /* ------------------------------------------------------------------ */

    private function onMysql(): bool
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            return true;
        }

        $this->markTestSkipped('Interleaving transaksi nyata hanya berarti pada MySQL.');

        return false;
    }

    private function prepareProbeConnections(): void
    {
        $base = config('database.connections.'.config('database.default'));

        config([
            'database.connections.mysql-regression-a' => $base,
            'database.connections.mysql-regression-b' => $base,
        ]);

        DB::purge('mysql-regression-a');
        DB::purge('mysql-regression-b');

        /*
         * Sisa baris dari run sebelumnya yang gagal di tengah jalan tetap
         * ter-commit (koneksi probe autocommit) dan tidak akan tersentuh
         * rollback RefreshDatabase. Bersihkan lewat koneksi autocommit yang
         * sama — pola yang sama dipakai ApartmentLockSerializationTest.
         */
        $this->purgeProbeRows(DB::connection('mysql-regression-a'));
    }

    /**
     * Hapus seluruh jejak probe lewat satu koneksi autocommit. Statement
     * mentah JOIN DELETE: builder tidak punya API alias-target untuk delete,
     * dan WHERE IN subquery pada tabel yang sama ditolak MySQL (1093).
     */
    private function purgeProbeRows(ConnectionInterface $on): void
    {
        $on->statement(
            'delete sp from payments sp '
            .'inner join bookings sb on sb.id = sp.booking_id '
            .'inner join apartments sa on sa.id = sb.apartment_id '
            .'where sa.slug like ?',
            ['conc-regression-%'],
        );

        $on->statement(
            'delete sb2 from bookings sb2 '
            .'inner join apartments sa2 on sa2.id = sb2.apartment_id '
            .'where sa2.slug like ?',
            ['conc-regression-%'],
        );

        $on->table('users')->where('email', 'like', 'regression-%@example.test')->delete();
        $on->table('apartments')->where('slug', 'like', 'conc-regression-%')->delete();
    }

    private function seedApartment(): int
    {
        $id = DB::connection('mysql-regression-a')->table('apartments')->insertGetId([
            'title' => 'Concurrency Regression Unit',
            'slug' => 'conc-regression-'.Str::random(6),
            'description' => 'Unit untuk regresi konkurensi.',
            'price_per_night' => self::PRICE,
            'address' => 'Jl. Regression No. 1',
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

        // Koneksi seed autocommit; apartemen ini nyata bagi semua koneksi.
        DB::purge('mysql-regression-a');

        return (int) $id;
    }

    /**
     * User juga lewat koneksi autocommit: booking yang diseed lewat koneksi
     * probe memiliki FK user_id restrict — baris yang tak terlihat koneksi
     * probe akan menggagalkan insert-nya.
     */
    private function seedUserId(): int
    {
        $id = DB::connection('mysql-regression-a')->table('users')->insertGetId([
            'name' => 'Regression Guest',
            'email' => 'regression-'.Str::random(6).'@example.test',
            'password' => 'x',
            'role' => 'user',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::purge('mysql-regression-a');

        return (int) $id;
    }

    /**
     * Satu pasang booking Pending + payment Pending yang sudah ter-commit.
     * Overdue=true meniru booking yang layak di-expire command; created_at
     * ditulis langsung karena kolom timestamp otomatis hanya aktif via Eloquent.
     */
    private function seedBookedAndPaid(int $apartmentId, int $userId, bool $overdue = true): string
    {
        $code = 'APT-R'.strtoupper(Str::random(8));
        $createdAt = $overdue
            ? now()->subMinutes((int) config('booking.payment_expiry_minutes') + 30)
            : now();

        $connection = DB::connection('mysql-regression-b');
        $connection->table('bookings')->insert([
            'booking_code' => $code,
            'user_id' => $userId,
            'apartment_id' => $apartmentId,
            'check_in' => now()->addDays(20)->toDateString(),
            'check_out' => now()->addDays(23)->toDateString(),
            'total_nights' => 3,
            'total_price' => self::PRICE * 3,
            'status' => 'pending',
            'notes' => null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $bookingId = $connection->table('bookings')->where('booking_code', $code)->value('id');

        $connection->table('payments')->insert([
            'booking_id' => $bookingId,
            'gross_amount' => self::PRICE * 3,
            'snap_token' => 'SNAP-REGRESSION',
            'status' => 'pending',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        DB::purge('mysql-regression-b');

        return $code;
    }

    /** Payload settlement dengan signature SHA-512 valid untuk server key test. */
    private function settlementPayload(string $bookingCode, string $grossAmount = '1500000.00'): array
    {
        $serverKey = (string) config('midtrans.server_key', 'test-server-key');
        $payload = [
            'order_id' => $bookingCode.'-1700000000',
            'status_code' => '200',
            'gross_amount' => $grossAmount,
            'transaction_status' => 'settlement',
            'transaction_id' => 'TRX-'.strtoupper(Str::random(10)),
        ];

        $payload['signature_key'] = hash(
            'sha512',
            $payload['order_id'].$payload['status_code'].$payload['gross_amount'].$serverKey,
        );

        return $payload;
    }

    private function insertGuestBooking(
        ConnectionInterface $connection,
        int $apartmentId,
        int $userId,
        string $checkIn,
        string $checkOut,
    ): string {
        $code = 'APT-W'.strtoupper(Str::random(8));
        $now = now();

        $connection->table('bookings')->insert([
            'booking_code' => $code,
            'user_id' => $userId,
            'apartment_id' => $apartmentId,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'total_nights' => 3,
            'total_price' => self::PRICE * 3,
            'status' => 'pending',
            'notes' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $bookingId = $connection->table('bookings')->where('booking_code', $code)->value('id');

        $connection->table('payments')->insert([
            'booking_id' => $bookingId,
            'gross_amount' => self::PRICE * 3,
            'snap_token' => 'SNAP-REGRESSION',
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $code;
    }

    /*
     * Pembacaan assertion SELALU lewat koneksi probe autocommit: read view
     * REPEATABLE READ milik sesi default tidak melihat commit koneksi lain,
     * sedangkan yang mau diverifikasi justru hasil commit mereka.
     */

    private function scalarOnProbe(\Closure $query): mixed
    {
        return $query(DB::connection('mysql-regression-b')->query());
    }

    /*
     * Pembacaan STATUS hasil apply() HARUS lewat sesi default dengan kunci:
     * tulisan PaymentStatusApplier hidup di dalam transaksi luar RefreshDatabase
     * (tak terlihat koneksi lain sampai commit), dan read view REPEATABLE READ
     * milik sesi ini bisa lebih tua dari commit probe. Pembacaan berkunci
     * menghindari kedua jebakan itu: selalu versi termutakhir, termasuk
     * tulisan transaksi sendiri.
     */

    private function bookingStatusOf(string $bookingCode): ?string
    {
        return Booking::where('booking_code', $bookingCode)
            ->lockForUpdate()
            ->first(['status'])
            ?->status?->value;
    }

    private function paymentStatusOf(string $bookingCode): ?string
    {
        return Payment::query()
            ->where('booking_id', fn ($q) => $q->select('id')->from('bookings')->where('booking_code', $bookingCode))
            ->lockForUpdate()
            ->first(['status'])
            ?->status?->value;
    }

    /**
     * 1205 yang keluar dari DB::transaction dibungkus DeadlockException oleh
     * Laravel (bukan QueryException), dan untuk HY000 errorInfo[1] bisa kosong
     * — jadi kecocokannya dicek dari kedua sumber.
     */
    private function assertQueuedOnLock(\Throwable $e): void
    {
        $code = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : 0;
        $this->assertTrue(
            $code === 1205 || str_contains($e->getMessage(), '1205 Lock wait timeout'),
            'Diharapkan mengantre pada lock (1205), mendapat: '.get_class($e).' '.$e->getMessage(),
        );
    }

    /**
     * Baris yang dibuat lewat koneksi probe ter-commit di luar transaksi luar
     * RefreshDatabase, jadi penghapusannya didaftarkan ke hook yang berjalan
     * setelah rollback — saat semua lock sudah lepas dan penghapusan tidak
     * ikut ter-roll-back.
     */
    private function cleanupLater(int $apartmentId): void
    {
        $this->beforeApplicationDestroyed(function (): void {
            /*
             * Lewat koneksi probe autocommit: saat hook ini berjalan, rollback
             * transaksi luar RefreshDatabase sudah melepas semua lock, dan
             * penghapusan di sini tidak ikut ter-roll-back.
             */
            $this->purgeProbeRows(DB::connection('mysql-regression-a'));
        });
    }
}
