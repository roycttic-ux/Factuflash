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
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'notify_afip')) {
                $table->boolean('notify_afip')->default(false)->after('total_local');
            }
            if (!Schema::hasColumn('invoices', 'payment_method')) {
                $table->string('payment_method', 50)->nullable()->after('notify_afip');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['notify_afip', 'payment_method']);
        });
    }
};
