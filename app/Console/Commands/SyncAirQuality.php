<?php

namespace App\Console\Commands;

use App\Models\IAQI;
use App\Models\Region;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncAirQuality extends Command
{
    protected $signature = 'app:sync-air-quality';
    protected $description = 'Sinkronisasi data IAQI dari API WAQI untuk setiap region.';

    public function handle()
    {
        Log::info('Sinkronisasi data IAQI dimulai');

        $tokens = [
            '6800add0de94e473e10c4399ab50b898f33f8ad3',
            '9d7d44d4523e7f4926caf1f1c49f49fbde7a3efd',
            '466c042386905f85eba7eaac7213376b335ccbdf'
        ];

        $allIAQIData = [];

        Region::chunk(5, function ($regions) use ($tokens, &$allIAQIData) {
            foreach ($regions as $index => $region) {

                $data = null;

                // =============================================================
                // 1. AMBIL DATA DARI API → COBA SEMUA TOKEN
                // =============================================================
                foreach ($tokens as $token) {
                    $url = "https://api.waqi.info/feed/{$region->url}/?token={$token}";

                    try {
                        $response = Http::timeout(15)->retry(3, 5000)->get($url);

                        if ($response->successful() && $response['status'] === 'ok') {
                            $data = $response['data'];
                            break;
                        }
                    } catch (\Exception $e) {
                        Log::error("Error fetching region {$region->name} | token {$token}: " . $e->getMessage());
                    }
                }

                // =============================================================
                // 2. FALLBACK: API GAGAL → AMBIL DATA TERAKHIR DI DATABASE
                // =============================================================
                if (!$data) {
                    Log::warning("API gagal → fallback DB untuk region: {$region->name}");

                    $last = IAQI::where('region_id', $region->id)
                        ->orderBy('observed_at', 'desc')
                        ->first();

                    $allIAQIData[$index] = [
                        'region' => [
                            'id'        => $region->id,
                            'name'      => $region->name,
                            'city'      => $region->city,
                            'latitude'  => $region->latitude,
                            'longitude' => $region->longitude,
                            'url'       => $region->url,
                            'iaqi'      => $last ? $last->toArray() : null,
                            'status'    => $last ? 'fallback-db' : 'no-data'
                        ]
                    ];
                    continue;
                }

                // =============================================================
                // 3. JIKA API BERHASIL → SIMPAN DATA
                // =============================================================
                try {
                    $iaqi = $data['iaqi'];
                    $time = $data['time']['s'];

                    $attributes = [
                        'dominent_pol' => $data['dominentpol'] ?? 'pm25',
                        'dew'          => $iaqi['dew']['v'] ?? null,
                        'h'            => $iaqi['h']['v'] ?? null,
                        'p'            => $iaqi['p']['v'] ?? null,
                        'pm25'         => $iaqi['pm25']['v'] ?? null,
                        'r'            => $iaqi['r']['v'] ?? null,
                        't'            => $iaqi['t']['v'] ?? null,
                        'w'            => $iaqi['w']['v'] ?? null,
                    ];

                    // =========================================================
                    // 4. HITUNG AQI ISPU + US EPA + KATEGORINYA
                    // =========================================================
                    $pm25 = $attributes['pm25'];

                    if ($pm25 !== null) {
                        $aqiIspu = $this->calcIspu($pm25);
                        $aqiUs   = $this->calcUs($pm25);

                        // simpan nilai AQI
                        $attributes['aqi_ispu'] = $aqiIspu;
                        $attributes['aqi_us']   = $aqiUs;

                        // simpan kategori
                        $attributes['category_ispu'] = $this->categoryIspu($aqiIspu);
                        $attributes['category_us']   = $this->categoryUs($aqiUs);
                    }

                    // =========================================================
                    // SIMPAN DATABASE
                    // =========================================================
                    $record = IAQI::updateOrCreate(
                        [
                            'region_id'   => $region->id,
                            'observed_at' => $time,
                        ],
                        $attributes
                    );

                    $allIAQIData[$index] = [
                        'region' => [
                            'id'       => $region->id,
                            'name'     => $region->name,
                            'city'     => $region->city,
                            'latitude' => $region->latitude,
                            'longitude'=> $region->longitude,
                            'url'      => $region->url,
                            'iaqi'     => $record->toArray(),
                        ]
                    ];

                    DB::disconnect();
                } catch (\Exception $e) {
                    Log::error("Gagal simpan IAQI untuk region {$region->name}: " . $e->getMessage());
                }

                sleep(1);
            }
        });

        Cache::forget('iaqi_data_all_regions');
        Cache::put('iaqi_data_all_regions', $allIAQIData, 3600);

        Log::info('Sinkronisasi data IAQI selesai.');
    }

    // =========================================================
    // ------------ RUMUS INTERPOLASI LINIER (WAQI) -----------
    // =========================================================
    private function linear($Cp, $BP_Hi, $BP_Lo, $I_Hi, $I_Lo)
    {
        return (($I_Hi - $I_Lo) / ($BP_Hi - $BP_Lo)) * ($Cp - $BP_Lo) + $I_Lo;
    }

    // =========================================================
    // ----------------------- ISPU ----------------------------
    // =========================================================
    private function calcIspu($Cp)
    {
        $bp = [
            [0.0, 15.5, 0, 50],
            [15.6, 55.4, 51, 100],
            [55.5, 150.4, 101, 200],
            [150.5, 250.4, 201, 300],
            [250.5, 500.0, 301, 500],
        ];

        foreach ($bp as [$lo, $hi, $Ilo, $Ihi]) {
            if ($Cp >= $lo && $Cp <= $hi) {
                return $this->linear($Cp, $hi, $lo, $Ihi, $Ilo);
            }
        }
        return null;
    }

    private function categoryIspu($v)
    {
        return match (true) {
            $v === null => null,
            $v <= 50    => 'Baik',
            $v <= 100   => 'Sedang',
            $v <= 200   => 'Tidak Sehat',
            $v <= 300   => 'Sangat Tidak Sehat',
            default     => 'Berbahaya',
        };
    }

    // =========================================================
    // ---------------------- US EPA ---------------------------
    // =========================================================
    private function calcUs($Cp)
    {
        $bp = [
            [0.0, 9.0, 0, 50],
            [9.1, 35.4, 51, 100],
            [35.5, 55.4, 101, 150],
            [55.5, 125.4, 151, 200],
            [125.5, 225.4, 201, 300],
            [225.5, 500.4, 301, 500],
        ];

        foreach ($bp as [$lo, $hi, $Ilo, $Ihi]) {
            if ($Cp >= $lo && $Cp <= $hi) {
                return $this->linear($Cp, $hi, $lo, $Ihi, $Ilo);
            }
        }
        return null;
    }

    private function categoryUs($v)
    {
        return match (true) {
            $v === null => null,
            $v <= 50    => 'Baik',
            $v <= 100   => 'Sedang',
            $v <= 150   => 'Tidak Sehat untuk Kelompok Sensitif',
            $v <= 200   => 'Tidak Sehat',
            $v <= 300   => 'Sangat Tidak Sehat',
            default     => 'Berbahaya',
        };
    }
}
