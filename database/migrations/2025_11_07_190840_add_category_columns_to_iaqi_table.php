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
            $table->decimal('aqi_ispu',5,2)->nullable()->after('pm25');
            $table->string('category_ispu')->nullable()->after('aqi_ispu');
            $table->decimal('aqi_us',5,2)->nullable()->after('category_ispu');
            $table->string('category_us')->nullable()->after('aqi_us');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('iaqi', function (Blueprint $table) {
            $table->dropColumn(['category_ispu', 'category_us', 'aqi_ispu', 'aqi_us']);
        });
    }
};
