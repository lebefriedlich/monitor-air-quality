<?php

namespace App\Console\Commands;

use App\Models\ForecastIAQI;
use App\Models\Region;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncForecastAirQuality extends Command
{
    protected $signature = 'app:sync-forecast-air-quality';
    protected $description = 'Sync air quality forecast per region using single-region API (US EPA + ISPU).';

    public function handle()
    {
        Log::info('[ForecastAQI] Start per-region forecast sync');

        $baseUrl   = config('services.api_base_url');
        $path      = '/forecast-single-region';
        // $path      = '/run-experiments';
        $today     = Carbon::now()->toDateString();
        $endpoint  = "{$baseUrl}{$path}";
        $startDate = Carbon::parse('2025-06-27')->startOfDay();
        $endDate   = Carbon::now()->endOfDay();
        // $endDate   = Carbon::parse('2026-01-22')->endOfDay();

        $regions = Region::whereHas('iaqi', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('observed_at', [$startDate, $endDate])
                ->orderBy('observed_at', 'asc');
        })
            ->with(['iaqi' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('observed_at', [$startDate, $endDate])
                    ->orderBy('observed_at', 'asc');
            }])
            ->get();

        if ($regions->isEmpty()) {
            Log::info('[ForecastAQI] No IAQI data found for forecast sync.');
            return Command::SUCCESS;
        }

        $forecastRegions = [];

        foreach ($regions as $region) {
            // Unikkan per timestamp
            $iaqi = $region->iaqi->unique('observed_at')->values();

            if ($iaqi->isNotEmpty()) {
                $minDate = $iaqi->min('observed_at');
                $maxDate = $iaqi->max('observed_at');
                Log::info('[ForecastAQI] Data range for region', [
                    'region_id'  => $region->id,
                    'region_name' => $region->name,
                    'start_date' => Carbon::parse($minDate)->format('Y-m-d H:i:s'),
                    'end_date'   => Carbon::parse($maxDate)->format('Y-m-d H:i:s'),
                    'records'    => $iaqi->count(),
                ]);
            }

            if ($iaqi->isEmpty()) {
                Log::warning("[ForecastAQI] Skipped {$region->name}: IAQI empty");
                continue;
            }

            // // Check if the last observed_at date is today
            $lastObservedAt = Carbon::parse($iaqi->last()->observed_at);  // Convert to Carbon instance
            if ($lastObservedAt->toDateString() !== $endDate->toDateString()) {
                Log::warning("[ForecastAQI] Skipped {$region->name}: Last observed_at is not today");
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
                    ->timeout(900)
                    ->retry(3, 5000)
                    ->acceptJson()
                    ->asJson()
                    ->post($endpoint, $payload);

                if (!$response->successful()) {
                    Log::error("[ForecastAQI] HTTP {$response->status()} error while syncing {$region->name}", [
                        'status' => $response->status(),
                        'body'   => $response->body(),
                        'request_payload' => json_encode($payload),
                    ]);
                    continue;
                }

                $result = $response->json();

                // Tangani error dari API Flask
                if (isset($result['error'])) {
                    Log::warning("[ForecastAQI] API returned error for {$region->name}", [
                        'error' => $result['error'],
                        'debug' => $result['debug'] ?? null,
                    ]);
                    continue;
                }

                // Ambil satu prediksi H+1 (asumsi selalu satu)
                $pred = $result['forecasts'] ?? null;

                if ($pred) {
                    $aqi = (isset($pred['forecast_iaqi_us_estimated']) ? round($pred['forecast_iaqi_us_estimated']) : null)
                        ?? (isset($pred['forecast_ispu_estimated']) ? round($pred['forecast_ispu_estimated']) : null)
                        ?? null;

                    $category = $pred['forecast_category_us_estimated']
                        ?? $pred['forecast_category_ispu_estimated']
                        ?? null;
                }

                if ($pred === null || $aqi === null || $category === null) {
                    Log::warning("[ForecastAQI] Invalid/empty forecast for region {$region->name}", [
                        'pred' => $pred,
                        'result' => $result,
                    ]);
                    continue;
                }

                $date = Carbon::parse($pred['date_local'] ?? now())->startOfDay();

                $payloadDB = [
                    'date'                     => $date,
                    'forecast_pm25'           => isset($pred['forecast_pm25_ugm3']) ? round((float) $pred['forecast_pm25_ugm3'], 2) : null,
                    'forecast_aqi'            => round((float) $pred['forecast_iaqi_us_estimated'], 2),
                    'forecast_category'       => (string) $pred['forecast_category_us_estimated'],
                    'forecast_ispu'           => round((float) $pred['forecast_ispu_estimated'], 2),
                    'forecast_category_ispu'  => (string) $pred['forecast_category_ispu_estimated'] ?? null,
                    'cv_metrics_svr'           => $pred['cv_metrics_svr'] ?? null,
                    'cv_metrics_baseline'      => $pred['cv_metrics_baseline'] ?? null,
                    'model_info'               => $result['model_info'] ?? null,
                ];

                ForecastIAQI::updateOrCreate(
                    ['region_id' => $region->id],
                    $payloadDB
                );

                $cacheKey = "forecast_region_{$region->id}";

                Cache::forget($cacheKey);
                Cache::put($cacheKey, [
                    'region_id'   => $region->id,
                    'region_name' => $region->name,
                    'date'        => $date->toDateString(),
                    'forecast_pm25'        => $payloadDB['forecast_pm25'],
                    'forecast_aqi'  => $payloadDB['forecast_aqi'],
                    'forecast_category'  => $payloadDB['forecast_category'],
                    'forecast_ispu'        => $payloadDB['forecast_ispu'],
                    'forecast_category_ispu'    => $payloadDB['forecast_category_ispu'],
                    'cv_metrics_svr'      => is_array($payloadDB['cv_metrics_svr'])
                        ? $payloadDB['cv_metrics_svr']
                        : json_decode($payloadDB['cv_metrics_svr'], true),
                    'cv_metrics_baseline' => is_array($payloadDB['cv_metrics_baseline'])
                        ? $payloadDB['cv_metrics_baseline']
                        : json_decode($payloadDB['cv_metrics_baseline'], true),
                    'model_info'   => is_array($payloadDB['model_info'])
                        ? $payloadDB['model_info']
                        : json_decode($payloadDB['model_info'], true),
                ], 86400);

                $forecastRegions[] = [
                    'region_id'   => $region->id,
                    'region_name' => $region->name,
                    'date'        => $date->toDateString(),
                    'forecast_pm25'        => $payloadDB['forecast_pm25'],
                    'forecast_aqi'  => $payloadDB['forecast_aqi'],
                    'forecast_category'  => $payloadDB['forecast_category'],
                    'forecast_ispu'        => $payloadDB['forecast_ispu'],
                    'forecast_category_ispu'    => $payloadDB['forecast_category_ispu'],
                    'cv_metrics_svr'      => $payloadDB['cv_metrics_svr'],
                    'cv_metrics_baseline' => $payloadDB['cv_metrics_baseline'],
                    'model_info'   => $payloadDB['model_info'],
                ];

                Log::info("Forecast sync completed for region {$region->name}.");
            } catch (\Throwable $e) {
                Log::error("[ForecastAQI] Exception while syncing {$region->name}", [
                    'error'        => $e->getMessage(),
                    'exception'    => get_class($e),
                    'trace'        => $e->getTraceAsString(),
                    'request_payload' => json_encode($payload),
                ]);
                // lanjut region lain
            }
        }

        // Refresh cache
        Cache::forget('forecast_regions');
        Cache::put('forecast_regions', $forecastRegions, 86400);

        Log::info('[ForecastAQI] Finished forecast sync', [
            'regions' => $regions->count(),
            'cached'  => count($forecastRegions),
        ]);

        return Command::SUCCESS;
    }
}
