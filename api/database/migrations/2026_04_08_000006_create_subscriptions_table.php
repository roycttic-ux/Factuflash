<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('mercadopago_id', 100)->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('status', 50)->nullable();
            $table->date('start_date')->nullable();
            $table->date('next_payment_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
