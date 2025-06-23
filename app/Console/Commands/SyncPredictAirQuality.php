<?php

namespace App\Console\Commands;

use App\Models\PredictIAQI;
use App\Models\Region;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncPredictAirQuality extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-predict-air-quality';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi data prediksi kualitas udara dari API PredictIAQI.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info('Sinkronisasi prediksi kualitas udara dimulai', [
            'timestamp' => Carbon::now()->toDateTimeString()
        ]);
        $sevenDaysAgo = Carbon::now()->subDays(7);
        $today = Carbon::now()->format('Y-m-d');

        $regions = Region::whereHas('iaqi', function ($query) use ($sevenDaysAgo) {
            $query->where('observed_at', '>=', $sevenDaysAgo);
        })
            ->with(['iaqi' => function ($query) use ($sevenDaysAgo) {
                $query->where('observed_at', '>=', $sevenDaysAgo)
                    ->orderBy('observed_at');
            }])
            ->get();

        if ($regions->isEmpty()) {
            Log::info('Tidak ada data IAQI yang ditemukan untuk sinkronisasi prediksi.', [
                'timestamp' => Carbon::now()->toDateTimeString()
            ]);
            return Command::SUCCESS;
        }

        $dataToSend = $regions->map(function ($region) use ($today) {
            return [
                'id' => $region->id,
                'name' => $region->name,
                'latitude' => (float) $region->latitude,
                'longitude' => (float) $region->longitude,
                'url' => $region->url,
                'date_now' => $today,
                'iaqi' => $region->iaqi->map(function ($iaqi) {
                    return [
                        'observed_at' => $iaqi->observed_at,
                        'dew' => $iaqi->dew,
                        'h' => $iaqi->h,
                        'p' => $iaqi->p,
                        'pm25' => $iaqi->pm25,
                        't' => $iaqi->t,
                        'w' => $iaqi->w,
                    ];
                })->toArray()
            ];
        })->toArray();

        $response = Http::timeout(300)->post('https://predict-air-quality.mhna.my.id/predict-multiple-regions', $dataToSend);

        $results = $response->json();

        foreach ($results as $regionPrediction) {
            $regionId = $regionPrediction['region_id'];
            PredictIAQI::where('region_id', $regionId)->delete();

            foreach ($regionPrediction['predictions'] as $prediction) {
                PredictIaqi::create([
                    'region_id' => $regionId,
                    'date' => Carbon::parse($prediction['date']),
                    'predicted_aqi' => $prediction['predicted_aqi'],
                    'predicted_category' => $prediction['predicted_category'],
                ]);
            }
        }

        Log::info('Sinkronisasi prediksi kualitas udara selesai',[
            'timestamp' => Carbon::now()->toDateTimeString()
        ]);
    }
}
