<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One payment row per booking — prevents duplicate payment records
        // created by concurrent requests.
        Schema::table('payments', function (Blueprint $table) {
            $table->unique('booking_id');
        });

        // Indexes for the availability-conflict query and list filters.
        Schema::table('bookings', function (Blueprint $table) {
            $table->index('apartment_id');
            $table->index('status');
            $table->index(['apartment_id', 'status']);
            $table->index(['check_in', 'check_out']);
        });

        Schema::table('apartments', function (Blueprint $table) {
            $table->index('status');
            $table->index('city');
        });

        // Prevent deleting users/apartments with booking history (financial
        // records must survive). Supported on all drivers via table rebuild.
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->dropForeign(['apartment_id']);
            $table->foreign('apartment_id')->references('id')->on('apartments')->restrictOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->foreign('booking_id')->references('id')->on('bookings')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['booking_id']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['apartment_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['apartment_id', 'status']);
            $table->dropIndex(['check_in', 'check_out']);
        });

        Schema::table('apartments', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['city']);
        });
    }
};
