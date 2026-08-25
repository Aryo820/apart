<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * bookings.status adalah kolom ENUM, jadi nilai baru harus didaftarkan di
     * skema — bukan hanya di App\Enums\BookingStatus. Tanpa migrasi ini MySQL
     * menolak (atau memotong menjadi string kosong) setiap penulisan 'expired'.
     *
     * Baris lama tidak disentuh: booking yang sebelumnya dibatalkan scheduler
     * karena tidak dibayar tetap berstatus 'cancelled'. Menerjemahkannya ulang
     * menjadi 'expired' butuh keputusan produk (lihat Remaining Decisions),
     * dan tidak ada kolom yang bisa membedakan keduanya secara pasti.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed', 'expired'])
                ->default('pending')
                ->change();
        });
    }

    public function down(): void
    {
        // Nilai 'expired' harus dinormalkan lebih dulu, kalau tidak baris
        // tersebut menjadi tidak valid terhadap definisi enum yang lama.
        DB::table('bookings')
            ->where('status', 'expired')
            ->update(['status' => 'cancelled']);

        Schema::table('bookings', function (Blueprint $table) {
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed'])
                ->default('pending')
                ->change();
        });
    }
};
