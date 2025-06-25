<?php

namespace App\Console\Commands;

use App\Models\PredictIAQI;
use App\Models\Region;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncPredictAirQuality extends Command
{
    protected $signature = 'app:sync-predict-air-quality';
    protected $description = 'Sinkronisasi data prediksi kualitas udara dari API PredictIAQI.';

    public function handle()
    {
        Log::info('Sinkronisasi prediksi kualitas udara dimulai.');

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
            Log::info('Tidak ada data IAQI yang ditemukan untuk sinkronisasi prediksi.');
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
                        'dew'         => $iaqi->dew,
                        'h'           => $iaqi->h,
                        'p'           => $iaqi->p,
                        'pm25'        => $iaqi->pm25,
                        't'           => $iaqi->t,
                        'w'           => $iaqi->w,
                    ];
                })->toArray()
            ];
        })->toArray();

        try {
            $response = Http::timeout(300)
                ->post('https://predict-air-quality.mhna.my.id/predict-multiple-regions', $dataToSend);

            if (!$response->successful()) {
                Log::error('Gagal mengirim request ke API prediksi. Status code: ' . $response->status());
                return Command::FAILURE;
            }

            $results = $response->json();

            if (!is_array($results)) {
                Log::error('Respon dari API tidak sesuai format yang diharapkan.');
                return Command::FAILURE;
            }

            foreach ($results as $regionPrediction) {
                $regionId = $regionPrediction['region_id'] ?? null;

                if (!$regionId || !isset($regionPrediction['predictions'])) {
                    Log::warning('Region tidak valid atau tidak memiliki prediksi: ' . json_encode($regionPrediction));
                    continue;
                }

                PredictIAQI::where('region_id', $regionId)->delete();

                foreach ($regionPrediction['predictions'] as $prediction) {
                    if (!isset($prediction['date'], $prediction['predicted_aqi'], $prediction['predicted_category'])) {
                        Log::warning('Data prediksi tidak lengkap: ' . json_encode($prediction));
                        continue;
                    }

                    PredictIAQI::create([
                        'region_id'          => $regionId,
                        'date'               => Carbon::parse($prediction['date']),
                        'predicted_aqi'      => $prediction['predicted_aqi'],
                        'predicted_category' => $prediction['predicted_category'],
                    ]);
                }
            }

            Log::info('Sinkronisasi prediksi kualitas udara selesai.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            Log::error('Terjadi kesalahan saat sinkronisasi: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
