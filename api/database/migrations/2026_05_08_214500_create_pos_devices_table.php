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
        Schema::create('pos_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // El dueño del comercio
            $table->string('name'); // Nombre descriptivo: "Caja 1", "Local Centro", etc.
            $table->enum('type', ['mercadopago_point', 'traditional_pos'])->default('mercadopago_point');
            $table->string('external_id')->unique(); // El S/N de Mercado Pago o el ID del agente local
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Agregamos una columna a las facturas para saber en qué dispositivo se cobró
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('pos_device_id')->nullable()->constrained('pos_devices')->nullOnDelete();
            $table->string('payment_transaction_id')->nullable(); // ID de MP o del POS
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pos_device_id');
            $table->dropColumn('payment_transaction_id');
        });
        Schema::dropIfExists('pos_devices');
    }
};
