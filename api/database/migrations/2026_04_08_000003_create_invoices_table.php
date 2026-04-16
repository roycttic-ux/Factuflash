<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number', 50)->unique();
            $table->string('cae', 50)->nullable();
            $table->date('cae_expiry')->nullable();
            $table->decimal('dollar_rate', 12, 4);
            $table->decimal('total_usd', 12, 4);
            $table->decimal('total_local', 12, 2);
            $table->enum('status', ['paid', 'pending', 'canceled'])->default('paid');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
