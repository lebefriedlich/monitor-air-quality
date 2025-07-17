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
    protected $description = 'Sync air quality predictions per region using new single-region API.';

    public function handle()
    {
        Log::info('Starting per-region air quality prediction sync.');

        $monthAgo = Carbon::now()->subDays(30);
        $today = Carbon::now()->format('Y-m-d');

        $regions = Region::whereHas('iaqi', function ($query) use ($monthAgo) {
            $query->where('observed_at', '>=', $monthAgo);
        })
        ->with(['iaqi' => function ($query) use ($monthAgo) {
            $query->where('observed_at', '>=', $monthAgo)
                  ->orderBy('observed_at', 'asc');
        }])
        ->get();

        if ($regions->isEmpty()) {
            Log::info('No IAQI data found for prediction sync.');
            return Command::SUCCESS;
        }

        $predictedRegions = [];
        foreach ($regions as $region) {
            $iaqi = $region->iaqi->unique('observed_at')->values();

            Log::info("Sending region {$region->name} with {$iaqi->count()} IAQI records.");

            $payload = [
                'id'       => $region->id,
                'name'     => $region->name,
                'latitude' => (float) $region->latitude,
                'longitude'=> (float) $region->longitude,
                'url'      => $region->url,
                'date_now' => $today,
                'iaqi'     => $iaqi->map(function ($item) {
                    return [
                        'observed_at' => $item->observed_at,
                        'dew'         => $item->dew,
                        'h'           => $item->h,
                        'p'           => $item->p,
                        'pm25'        => $item->pm25,
                        't'           => $item->t,
                        'w'           => $item->w,
                    ];
                })->toArray(),
            ];

            try {
                $response = Http::timeout(120)->retry(3, 5000)
                    ->post('https://predict-air-quality.mhna.my.id/predict-single-region', $payload);

                if (!$response->successful()) {
                    Log::error("Prediction API failed for region {$region->name}. Status: " . $response->status());
                    continue;
                }

                $result = $response->json();

                if (!isset($result['region_id']) || !isset($result['predictions'])) {
                    Log::warning("Incomplete response for region {$region->name}: " . json_encode($result));
                    continue;
                }

                PredictIAQI::where('region_id', $region->id)->delete();

                foreach ($result['predictions'] as $index => $prediction) {
                    if (!isset($prediction['date'], $prediction['predicted_aqi'], $prediction['predicted_category'])) {
                        Log::warning("Invalid prediction data: " . json_encode($prediction));
                        continue;
                    }

                    PredictIAQI::create([
                        'region_id'          => $region->id,
                        'date'               => Carbon::parse($prediction['date']),
                        'predicted_aqi'      => $prediction['predicted_aqi'],
                        'predicted_category' => $prediction['predicted_category'],
                    ]);

                    $predictedRegions[$index] = [
                        'region_id'          => $region->id,
                        'date'               => Carbon::parse($prediction['date'])->format('Y-m-d'),
                        'predicted_aqi'      => $prediction['predicted_aqi'],
                        'predicted_category' => $prediction['predicted_category'],
                    ];
                }

                Log::info("Prediction sync completed for region {$region->name}.");
            } catch (\Exception $e) {
                Log::error("Exception while syncing region {$region->name}: " . $e->getMessage());
            }
        }

        // Save all predicted regions to cache
        // Cache::forget('predicted_regions');
        // Cache::put('predicted_regions', $predictedRegions, now()->addDay());

        Log::info('Per-region prediction sync finished.');
        return Command::SUCCESS;
    }
}
