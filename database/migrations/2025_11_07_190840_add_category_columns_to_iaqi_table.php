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
            $table->integer('aqi_ispu')->nullable()->after('pm25');
            $table->string('category_ispu')->nullable()->after('aqi_ispu');
            $table->integer('aqi_us')->nullable()->after('category_ispu');
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
