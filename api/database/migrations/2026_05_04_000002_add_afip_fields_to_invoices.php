<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->integer('cbte_tipo')->nullable()->after('invoice_number');
            $table->integer('pto_vta')->nullable()->after('cbte_tipo');
            $table->string('nc_cae')->nullable()->after('cae');
            $table->string('nc_number')->nullable()->after('invoice_number');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['cbte_tipo', 'pto_vta', 'nc_cae', 'nc_number']);
        });
    }
};
