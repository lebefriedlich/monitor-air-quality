<?php

namespace App\Console\Commands;

use App\Models\IAQI;
use App\Models\Region;
use App\Models\Token;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncAirQuality extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-air-quality';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi data IAQI dari API WAQI untuk setiap region yang ada.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info('Sinkronisasi data IAQI dimulai');
        $tokens = [
            '6800add0de94e473e10c4399ab50b898f33f8ad3',
            '9d7d44d4523e7f4926caf1f1c49f49fbde7a3efd',
            '466c042386905f85eba7eaac7213376b335ccbdf'
        ];
        $regions = Region::all();

        foreach ($regions as $region) {
            $data = null;

            foreach ($tokens as $token) {
                $url = "https://api.waqi.info/feed/{$region->url}/?token={$token}";

                try {
                    $response = Http::timeout(15)->retry(3, 5000)->get($url);

                    if ($response->successful() && $response['status'] === 'ok') {
                        $data = $response['data'];
                        break;
                    }
                } catch (\Exception $e) {
                    Log::error("Error fetching region {$region->name} with token: {$token}. Error: " . $e->getMessage());
                }
            }

            if (!$data) {
                Log::warning("Gagal ambil data untuk region: {$region->name}");
                continue;
            }

            try {
                $iaqi = $data['iaqi'];
                $timestamp = $data['time']['s'];

                IAQI::updateOrCreate(
                    [
                        'region_id'   => $region->id,
                        'observed_at' => $timestamp,
                    ],
                    [
                        'dominent_pol'  => $data['dominentpol'] ?? '-',
                        'dew'           => $iaqi['dew']['v'] ?? null,
                        'h'             => $iaqi['h']['v'] ?? null,
                        'p'             => $iaqi['p']['v'] ?? null,
                        'pm25'          => $iaqi['pm25']['v'] ?? null,
                        'r'             => $iaqi['r']['v'] ?? null,
                        't'             => $iaqi['t']['v'] ?? null,
                        'w'             => $iaqi['w']['v'] ?? null,
                    ]
                );
            } catch (\Exception $e) {
                Log::error("Gagal menyimpan data IAQI untuk region {$region->name}: " . $e->getMessage());
            }

            sleep(1); // delay kecil untuk menghindari rate limit
        }

        Log::info('Sinkronisasi data IAQI selesai');
    }
}
