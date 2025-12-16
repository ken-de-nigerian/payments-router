<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('payments.logging.table', 'payment_transactions'), function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique()->index();
            $table->string('provider')->index();
            $table->string('status')->index();
            $table->decimal('amount', 15);
            $table->string('currency', 3);
            $table->string('email');
            $table->string('channel')->nullable();
            $table->json('metadata')->nullable();
            $table->json('customer')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('payments.logging.table', 'payment_transactions'));
    }
};
