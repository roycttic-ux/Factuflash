<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $t) {
            $t->string('iibb_regime', 50)->nullable()->default('none');
            $t->decimal('default_iibb_alicuota', 5, 2)->nullable();
        });

        Schema::table('recipients', function (Blueprint $t) {
            $t->decimal('iibb_alicuota', 5, 2)->nullable();
            $t->boolean('iibb_exempt')->default(false);
        });

        Schema::table('invoices', function (Blueprint $t) {
            $t->decimal('total_tributos', 15, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn('total_tributos'));
        Schema::table('recipients', fn (Blueprint $t) => $t->dropColumn(['iibb_alicuota', 'iibb_exempt']));
        Schema::table('user_settings', fn (Blueprint $t) => $t->dropColumn(['iibb_regime', 'default_iibb_alicuota']));
    }
};
