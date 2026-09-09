<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Operational resolution state untuk insiden finansial
     * "settled-but-unavailable" (F-05, Batch 3.1).
     *
     * Dua kolom nullable — murni tambahan, tidak mengubah semantics kolom
     * apa pun yang sudah ada. Payment yang sudah ada tetap valid: tanpa
     * nilai, insiden yang belum ditindak tetap "unresolved" (backward-
     * compatible dengan predicate Batch 3).
     *
     * Ini BUKAN bagian dari gateway/booking lifecycle: Payment.status tetap
     * catatan Midtrans, Booking.status tetap fakta reservasi. Kolom ini
     * hanya mencatat bahwa operator telah menyelesaikan follow-up manual
     * (mis. refund di dashboard Midtrans) — tiga konsep yang dipisah.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('attention_resolved_at')->nullable()->after('raw_response');
            $table->text('attention_resolution_note')->nullable()->after('attention_resolved_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['attention_resolved_at', 'attention_resolution_note']);
        });
    }
};
