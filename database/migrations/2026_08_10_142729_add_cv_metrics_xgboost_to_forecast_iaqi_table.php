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
        Schema::table('forecast_iaqi', function (Blueprint $table) {
            $table->json('cv_metrics_xgboost')->nullable()->after('cv_metrics_baseline');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('forecast_iaqi', function (Blueprint $table) {
            $table->dropColumn('cv_metrics_xgboost');
        });
    }
};
