<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('operator_id')->constrained('users')->restrictOnDelete();
            $table->uuid('submission_key');
            $table->string('submission_hash', 64);
            $table->string('status', 20)->default('pending');
            $table->json('snapshot');
            $table->foreignId('invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'submission_key']);
            $table->index(['user_id', 'operator_id', 'status']);
        });
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->uuid('variant_id')->nullable();
            $table->json('variant_snapshot')->nullable();
            $table->boolean('stock_reserved')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', fn (Blueprint $table) => $table->dropColumn(['variant_id', 'variant_snapshot', 'stock_reserved']));
        Schema::dropIfExists('invoice_submissions');
    }
};
