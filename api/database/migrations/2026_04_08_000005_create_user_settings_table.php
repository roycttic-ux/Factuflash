<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('company_name', 150)->nullable();
            $table->string('company_logo', 255)->nullable();
            $table->string('company_address', 255)->nullable();
            $table->string('company_phone', 20)->nullable();
            $table->string('company_email', 150)->nullable();
            $table->string('company_cuit', 20)->nullable();
            $table->string('arca_cuit', 20)->nullable();
            $table->string('arca_cert_path', 255)->nullable();
            $table->string('arca_key_path', 255)->nullable();
            $table->boolean('arca_connected')->default(false);
            $table->decimal('dollar_rate', 10, 2)->default(0);
            $table->decimal('inflation_rate', 5, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
