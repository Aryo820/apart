<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('transaction_id')->nullable();
            $table->string('snap_token')->nullable();
            $table->string('payment_type')->nullable();
            $table->decimal('gross_amount', 12, 2);
            $table->enum('status', ['pending', 'settlement', 'expire', 'cancel', 'failed'])->default('pending');
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
