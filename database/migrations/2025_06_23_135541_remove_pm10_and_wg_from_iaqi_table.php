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
        Schema::table('iaqi', function (Blueprint $table) {
            $table->dropColumn(['pm10', 'wg']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('aqi', function (Blueprint $table) {
            $table->decimal('pm10', 5, 2)->nullable();
            $table->decimal('wg', 5, 2)->nullable();
        });
    }
};
