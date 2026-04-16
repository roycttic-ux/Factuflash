<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 50)->nullable();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->decimal('cost_usd', 12, 4)->default(0);
            $table->decimal('margin_percent', 5, 2)->default(0);
            $table->decimal('recommended_price', 12, 2)->default(0);
            $table->integer('stock')->default(0);
            $table->integer('min_stock_alert')->default(5);
            $table->string('category', 50)->nullable();
            $table->string('image_url', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
