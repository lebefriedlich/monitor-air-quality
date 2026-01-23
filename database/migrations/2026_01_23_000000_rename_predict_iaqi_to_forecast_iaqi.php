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
        Schema::rename('predict_iaqi', 'forecast_iaqi');

        Schema::table('forecast_iaqi', function (Blueprint $table) {
            $table->renameColumn('predicted_pm25', 'forecast_pm25');
            $table->renameColumn('predicted_aqi', 'forecast_aqi');
            $table->renameColumn('predicted_category', 'forecast_category');
            $table->renameColumn('predicted_ispu', 'forecast_ispu');
            $table->renameColumn('predicted_category_ispu', 'forecast_category_ispu');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('forecast_iaqi', function (Blueprint $table) {
            $table->renameColumn('forecast_pm25', 'predicted_pm25');
            $table->renameColumn('forecast_aqi', 'predicted_aqi');
            $table->renameColumn('forecast_category', 'predicted_category');
            $table->renameColumn('forecast_ispu', 'predicted_ispu');
            $table->renameColumn('forecast_category_ispu', 'predicted_category_ispu');
        });

        Schema::rename('forecast_iaqi', 'predict_iaqi');
    }
};
