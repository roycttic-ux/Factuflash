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
        if (!Schema::hasColumn('user_settings', 'arca_pto_vta')) {
            Schema::table('user_settings', function (Blueprint $table) {
                $table->integer('arca_pto_vta')->default(1)->after('arca_connected');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('user_settings', 'arca_pto_vta')) {
            Schema::table('user_settings', function (Blueprint $table) {
                $table->dropColumn('arca_pto_vta');
            });
        }
    }
};
