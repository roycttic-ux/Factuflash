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
        Schema::table('user_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('user_settings', 'whatsapp_number')) {
                $table->string('whatsapp_number', 20)->nullable()->after('company_phone');
            }
            if (!Schema::hasColumn('user_settings', 'show_whatsapp_button')) {
                $table->boolean('show_whatsapp_button')->default(false)->after('whatsapp_number');
            }
            if (!Schema::hasColumn('user_settings', 'mp_access_token')) {
                $table->string('mp_access_token', 255)->nullable()->after('inflation_rate');
            }
            if (!Schema::hasColumn('user_settings', 'mp_public_key')) {
                $table->string('mp_public_key', 255)->nullable()->after('mp_access_token');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_number', 'show_whatsapp_button', 'mp_access_token', 'mp_public_key']);
        });
    }
};
