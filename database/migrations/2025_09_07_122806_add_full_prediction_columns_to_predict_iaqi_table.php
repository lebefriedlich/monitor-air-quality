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
        Schema::table('predict_iaqi', function (Blueprint $table) {
            // Prediksi konsentrasi mentah
            $table->decimal('predicted_pm25', 6, 2)->after('date');

            // AQI US EPA (ubah panjang string agar cukup)
            $table->string('predicted_category', 100)->change();

            // ISPU RI
            $table->unsignedSmallInteger('predicted_ispu')->nullable()->after('predicted_aqi');
            $table->string('predicted_category_ispu', 100)->nullable()->after('predicted_ispu');

            // Metrics & model info
            $table->json('cv_metrics_svr')->nullable()->after('predicted_category_ispu');
            $table->json('cv_metrics_baseline')->nullable()->after('cv_metrics_svr');
            $table->json('model_info')->nullable()->after('cv_metrics_baseline');
        });
    }

    public function down(): void
    {
        Schema::table('predict_iaqi', function (Blueprint $table) {
            $table->dropColumn([
                'predicted_pm25',
                'predicted_ispu',
                'predicted_category_ispu',
                'cv_metrics_svr',
                'cv_metrics_baseline',
                'model_info'
            ]);

            // Kembalikan panjang predicted_category ke default
            $table->string('predicted_category', 50)->change();
        });
    }
};
