<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->uuid('submission_key')->nullable();
            $table->unique(['user_id', 'submission_key']);
        });
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->string('transaction_id', 191)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropColumn('transaction_id');
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'submission_key']);
            $table->dropColumn('submission_key');
        });
    }
};
