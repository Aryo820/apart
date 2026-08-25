<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menyimpan order_id yang dikirim ke Midtrans.
 *
 * Sebelum ini order_id dibentuk di dalam MidtransService ('{booking_code}-{time}')
 * lalu dibuang: webhook memang bisa mem-parse booking_code darinya, tapi kita
 * tidak pernah bisa MENANYAKAN status sebuah transaksi ke Midtrans karena
 * komponen timestamp-nya tidak bisa direkonstruksi. Tanpa kolom ini rekonsiliasi
 * saat user kembali dari Snap tidak mungkin dilakukan.
 *
 * Nullable: baris payment yang dibuat sebelum migrasi ini (dan booking yang
 * dibuat admin lewat Filament) tidak punya nilainya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('order_id')->nullable()->after('booking_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
            $table->dropColumn('order_id');
        });
    }
};
