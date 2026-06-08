<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL no permite ALTER COLUMN en ENUM directamente con el Blueprint de Laravel,
        // así que usamos una sentencia SQL cruda.
        DB::statement("ALTER TABLE fy_invoices MODIFY COLUMN status ENUM('paid', 'pending', 'canceled', 'annulled') NOT NULL DEFAULT 'paid'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE fy_invoices MODIFY COLUMN status ENUM('paid', 'pending', 'canceled') NOT NULL DEFAULT 'paid'");
    }
};
