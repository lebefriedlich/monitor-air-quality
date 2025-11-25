<?php

namespace App\Console\Commands;

use App\Models\PredictIAQI;
use App\Models\Region;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncPredictAirQuality extends Command
{
    protected $signature = 'app:sync-predict-air-quality';
    protected $description = 'Sync air quality predictions per region using single-region API (US EPA + ISPU).';

    public function handle()
    {
        Log::info('[PredictAQI] Start per-region prediction sync');

        $baseUrl  = 'http://127.0.0.1:5000/';
        $path     = '/predict-single-region';
        $today    = Carbon::now()->toDateString();
        $endpoint = "{$baseUrl}{$path}";

        $regions = Region::whereHas('iaqi', function ($query) {
            $query->where('observed_at', '>=', '2025-08-27')  // Filter iaqi based on observed_at date
                ->orderBy('observed_at', 'asc');
        })
            ->with(['iaqi' => function ($q) {
                $q->where('observed_at', '>=', '2025-08-27')  // Ensure 'observed_at' filter is applied in the 'with' query as well
                    ->orderBy('observed_at', 'asc');
            }])
            ->get();

        if ($regions->isEmpty()) {
            Log::info('[PredictAQI] No IAQI data found for prediction sync.');
            return Command::SUCCESS;
        }

        $predictedRegions = [];

        foreach ($regions as $region) {
            // Unikkan per timestamp
            $iaqi = $region->iaqi->unique('observed_at')->values();

            Log::info('[PredictAQI] Sending region payload', [
                'region_id' => $region->id,
                'name'      => $region->name,
                'records'   => $iaqi->count(),
            ]);

            if ($iaqi->isEmpty()) {
                Log::warning("[PredictAQI] Skipped {$region->name}: IAQI empty");
                continue;
            }

            // Check if the last observed_at date is today
            $lastObservedAt = Carbon::parse($iaqi->last()->observed_at);  // Convert to Carbon instance
            if ($lastObservedAt->toDateString() !== Carbon::today()->toDateString()) {
                Log::warning("[PredictAQI] Skipped {$region->name}: Last observed_at is not today");
                continue;  // Skip if the last observed_at is not today
            }


            // Susun payload sesuai app.py (kolom waktu masuk kandidat: observed_at)
            $payload = [
                'id'        => (string) $region->id,
                'name'      => (string) $region->name,
                'latitude'  => (float) $region->latitude,
                'longitude' => (float) $region->longitude,
                'url'       => $region->url,
                'date_now'  => $today,
                'iaqi'      => $iaqi->map(function ($row) {
                    return [
                        'observed_at' => $row->observed_at !== null ? $row->observed_at : null,
                        'pm25'        => $row->pm25 !== null ? (float) $row->pm25 : null,
                        't'           => $row->t    !== null ? (float) $row->t    : null,
                        'h'           => $row->h    !== null ? (float) $row->h    : null,
                        'p'           => $row->p    !== null ? (float) $row->p    : null,
                        'w'           => $row->w    !== null ? (float) $row->w    : null,
                        'dew'         => $row->dew  !== null ? (float) $row->dew  : null,
                    ];
                })->toArray(),
            ];

            try {
                $response = Http::withHeaders([
                    'x-api-key' => config('services.api_key'),
                ])
                    ->timeout(120)
                    ->retry(3, 5000)
                    ->acceptJson()
                    ->asJson()
                    ->post($endpoint, $payload);

                if (!$response->successful()) {
                    Log::error("[PredictAQI] API failed for {$region->name}", [
                        'status' => $response->status(),
                        'body'   => $response->body(),
                    ]);
                    continue;
                }

                $result = $response->json();

                // Tangani error dari API Flask
                if (isset($result['error'])) {
                    Log::warning("[PredictAQI] API returned error for {$region->name}", [
                        'error' => $result['error'],
                        'debug' => $result['debug'] ?? null,
                    ]);
                    continue;
                }

                // Ambil satu prediksi H+1 (asumsi selalu satu)
                $pred = $result['predictions'] ?? null;

                if ($pred) {
                    // Normalisasi agar backward-compatible
                    $aqi = $pred['predicted_aqi']
                        ?? $pred['predicted_ispu_estimated']        // ISPU Indonesia
                        ?? (isset($pred['predicted_iaqi_pm25']) ? round($pred['predicted_iaqi_pm25']) : null); // US IAQI PM2.5

                    $category = $pred['predicted_category']
                        ?? $pred['predicted_category_ispu_estimated'] // ISPU Indonesia
                        ?? $pred['predicted_category_us']             // US AQI
                        ?? null;
                }

                if ($pred === null || $aqi === null || $category === null) {
                    Log::warning("[PredictAQI] Invalid/empty prediction for region {$region->name}", [
                        'pred' => $pred,
                        'result' => $result,
                    ]);
                    continue;
                }

                // Tanggal prediksi dari API (date_local lebih diprioritaskan)
                $date = Carbon::parse($pred['date_local'] ?? $pred['date'] ?? now())->startOfDay();

                // Siapkan payload lengkap (US EPA + ISPU + metrics + model_info)
                $payloadDB = [
                    'date'                     => $date,
                    'predicted_pm25'           => isset($pred['predicted_pm25_ugm3']) ? round((float) $pred['predicted_pm25_ugm3'], 2) : null,
                    'predicted_aqi'            => round((float) $pred['predicted_iaqi_pm25'], 2),
                    'predicted_category'       => (string) $pred['predicted_category_us'],
                    'predicted_ispu'           => isset($pred['predicted_ispu_estimated']) ? (int) $pred['predicted_ispu_estimated'] : null,
                    'predicted_category_ispu'  => $pred['predicted_category_ispu_estimated'] ?? null,
                    'cv_metrics_svr'           => $pred['cv_metrics_svr'] ?? null,
                    'cv_metrics_baseline'      => $pred['cv_metrics_baseline'] ?? null,
                    'model_info'               => $result['model_info'] ?? null,
                ];

                // Overwrite satu baris per region_id
                PredictIAQI::updateOrCreate(
                    ['region_id' => $region->id],  // kunci tunggal: region_id
                    $payloadDB
                );

                // Cache khusus per region
                $cacheKey = "predicted_region_{$region->id}";

                Cache::forget($cacheKey);
                Cache::put($cacheKey, [
                    'region_id'   => $region->id,
                    'region_name' => $region->name,
                    'date'        => $date->toDateString(),
                    'pm25'        => $payloadDB['predicted_pm25'],
                    'aqi_us_epa'  => $payloadDB['predicted_aqi'],
                    'cat_us_epa'  => $payloadDB['predicted_category'],
                    'ispu'        => $payloadDB['predicted_ispu'],
                    'cat_ispu'    => $payloadDB['predicted_category_ispu'],
                    'cv_metrics_svr'      => is_array($payloadDB['cv_metrics_svr'])
                        ? $payloadDB['cv_metrics_svr']
                        : json_decode($payloadDB['cv_metrics_svr'], true),
                    'cv_metrics_baseline' => is_array($payloadDB['cv_metrics_baseline'])
                        ? $payloadDB['cv_metrics_baseline']
                        : json_decode($payloadDB['cv_metrics_baseline'], true),
                    'model_info'   => is_array($payloadDB['model_info'])
                        ? $payloadDB['model_info']
                        : json_decode($payloadDB['model_info'], true),
                ], now()->addDay());

                // (Opsional) ringkas buat cache/UI
                $predictedRegions[] = [
                    'region_id'   => $region->id,
                    'region_name' => $region->name,
                    'date'        => $date->toDateString(),
                    'pm25'        => $payloadDB['predicted_pm25'],
                    'aqi_us_epa'  => $payloadDB['predicted_aqi'],
                    'cat_us_epa'  => $payloadDB['predicted_category'],
                    'ispu'        => $payloadDB['predicted_ispu'],
                    'cat_ispu'    => $payloadDB['predicted_category_ispu'],
                    'cv_metrics_svr'      => $payloadDB['cv_metrics_svr'],
                    'cv_metrics_baseline' => $payloadDB['cv_metrics_baseline'],
                    'model_info'   => $payloadDB['model_info'],
                ];

                Log::info("Prediction sync completed for region {$region->name}.");
            } catch (\Throwable $e) {
                Log::error("[PredictAQI] Exception while syncing {$region->name}", [
                    'error' => $e->getMessage(),
                ]);
                // lanjut region lain
            }
        }

        // Refresh cache
        Cache::forget('predicted_regions');
        Cache::put('predicted_regions', $predictedRegions, now()->addDay());

        Log::info('[PredictAQI] Finished prediction sync', [
            'regions' => $regions->count(),
            'cached'  => count($predictedRegions),
        ]);

        return Command::SUCCESS;
    }
}
