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
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('payment_method'); // cash, card, transfer, etc.
            $table->decimal('amount', 12, 2);
            $table->string('details')->nullable(); // Ej: "Visa Débito ****1234"
            $table->timestamps();
        });

        // Agregamos una columna para indicar si es pago múltiple en la factura principal
        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('is_multiple_payment')->default(false)->after('payment_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
        
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('is_multiple_payment');
        });
    }
};
