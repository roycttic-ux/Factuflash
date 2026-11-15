<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->string('fiscal_jurisdiction', 100)->nullable();
            $table->string('business_activity', 150)->nullable();
            $table->string('iibb_number', 50)->nullable();
            $table->enum('iibb_condition', ['local', 'convenio_multilateral', 'exento', 'no_inscripto', 'otros'])->nullable();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('submission_hash', 64)->nullable();
        });

        Schema::create('invoice_tribute_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50);
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_tribute_audits');
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn('submission_hash'));
        Schema::table('user_settings', fn (Blueprint $table) => $table->dropColumn([
            'fiscal_jurisdiction', 'business_activity', 'iibb_number', 'iibb_condition',
        ]));
    }
};
