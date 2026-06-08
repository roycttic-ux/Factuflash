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
            $table->string('catalog_title')->nullable()->after('catalog_theme');
            $table->text('catalog_description')->nullable()->after('catalog_title');
            $table->string('catalog_instagram')->nullable()->after('catalog_description');
            $table->string('catalog_facebook')->nullable()->after('catalog_instagram');
            $table->boolean('catalog_show_stock')->default(true)->after('catalog_facebook');
            $table->boolean('catalog_show_price')->default(true)->after('catalog_show_stock');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->dropColumn([
                'catalog_title',
                'catalog_description',
                'catalog_instagram',
                'catalog_facebook',
                'catalog_show_stock',
                'catalog_show_price',
            ]);
        });
    }
};
