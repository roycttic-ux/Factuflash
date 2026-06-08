<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('user_settings', 'invoice_prefix')) {
                $table->string('invoice_prefix', 10)->default('F')->after('company_cuit');
            }
            if (!Schema::hasColumn('user_settings', 'tax_condition')) {
                $table->string('tax_condition', 50)->default('Responsable Inscripto')->after('invoice_prefix');
            }
            if (!Schema::hasColumn('user_settings', 'catalog_theme')) {
                $table->string('catalog_theme', 20)->default('white')->after('mp_public_key');
            }
            if (!Schema::hasColumn('user_settings', 'dollar_rate_mode')) {
                $table->string('dollar_rate_mode', 20)->default('manual')->after('dollar_rate');
            }
            if (!Schema::hasColumn('user_settings', 'dollar_rate_manual')) {
                $table->decimal('dollar_rate_manual', 12, 2)->default(0)->after('dollar_rate_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->dropColumn(['invoice_prefix', 'tax_condition', 'catalog_theme', 'dollar_rate_mode', 'dollar_rate_manual']);
        });
    }
};
