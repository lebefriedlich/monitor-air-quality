<?php

namespace App\Console\Commands;

use App\Models\PredictIAQI;
use App\Models\Region;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncPredictAirQuality extends Command
{
    protected $signature = 'app:sync-predict-air-quality';
    protected $description = 'Sync air quality predictions per region using single-region API (US EPA + ISPU).';

    public function handle()
    {
        Log::info('[PredictAQI] Start per-region prediction sync');

        $baseUrl  = 'https://predict-air-quality.mhna.my.id';
        $path     = '/predict-single-region';
        $lookback = (int) $this->option('days');

        $monthAgo = Carbon::now()->subDays($lookback);
        $today    = Carbon::now()->toDateString();
        $endpoint = "{$baseUrl}{$path}";

        // Ambil region yang punya data IAQI 30 hari terakhir
        $regions = Region::whereHas('iaqi', function ($q) use ($monthAgo) {
            $q->where('observed_at', '>=', $monthAgo);
        })
            ->with(['iaqi' => function ($q) use ($monthAgo) {
                $q->where('observed_at', '>=', $monthAgo)
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
                $pred = $result['predictions'][0] ?? null;

                if (!$pred || !isset($pred['predicted_aqi'], $pred['predicted_category'])) {
                    Log::warning("[PredictAQI] Invalid/empty prediction for region {$region->name}", ['pred' => $pred, 'result' => $result]);
                    continue;
                }

                // Tanggal prediksi dari API (date_local lebih diprioritaskan)
                $date = Carbon::parse($pred['date_local'] ?? $pred['date'] ?? now())->startOfDay();

                // Siapkan payload lengkap (US EPA + ISPU + metrics + model_info)
                $payloadDB = [
                    'date'                     => $date,
                    'predicted_pm25'           => isset($pred['predicted_pm25']) ? round((float) $pred['predicted_pm25'], 2) : null,
                    'predicted_aqi'            => round((float) $pred['predicted_aqi'], 2),
                    'predicted_category'       => (string) $pred['predicted_category'],
                    'predicted_ispu'           => isset($pred['predicted_ispu']) ? (int) $pred['predicted_ispu'] : null,
                    'predicted_category_ispu'  => $pred['predicted_category_ispu'] ?? null,
                    'cv_metrics_svr'           => $pred['cv_metrics_svr'] ?? null,
                    'cv_metrics_baseline'      => $pred['cv_metrics_baseline'] ?? null,
                    'model_info'               => $result['model_info'] ?? null,
                ];

                // Overwrite satu baris per region_id
                PredictIAQI::updateOrCreate(
                    ['region_id' => $region->id],  // kunci tunggal: region_id
                    $payloadDB
                );

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
