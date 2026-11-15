<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', fn (Blueprint $t) => $t->unsignedTinyInteger('iva_id')->nullable());
        Schema::table('recipients', fn (Blueprint $t) => $t->unsignedTinyInteger('iva_condition_id')->nullable());
        Schema::table('invoices', function (Blueprint $t) {
            $t->json('fiscal_snapshot')->nullable();
            $t->date('nc_cae_expiry')->nullable();
            $t->timestamp('nc_issued_at')->nullable();
        });
        Schema::table('invoice_items', function (Blueprint $t) {
            $t->string('description_snapshot', 150)->nullable();
            $t->unsignedTinyInteger('iva_id')->nullable();
            $t->decimal('net_amount', 15, 2)->nullable();
            $t->decimal('vat_amount', 15, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', fn (Blueprint $t) => $t->dropColumn(['description_snapshot', 'iva_id', 'net_amount', 'vat_amount']));
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn(['fiscal_snapshot', 'nc_cae_expiry', 'nc_issued_at']));
        Schema::table('recipients', fn (Blueprint $t) => $t->dropColumn('iva_condition_id'));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('iva_id'));
    }
};
