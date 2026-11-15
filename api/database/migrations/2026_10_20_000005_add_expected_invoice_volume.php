<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('users', fn (Blueprint $t) => $t->unsignedInteger('expected_monthly_invoices')->nullable());
        Schema::table('users', fn (Blueprint $t) => $t->unsignedInteger('simultaneous_registers')->nullable());
    }
    public function down(): void {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['expected_monthly_invoices', 'simultaneous_registers']));
    }
};
