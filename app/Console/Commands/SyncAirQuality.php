<?php

namespace App\Console\Commands;

use App\Models\IAQI;
use App\Models\Region;
use App\Models\Token;
use Carbon\Carbon;
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
        $tokens = Token::pluck('token')->toArray();
        $regions = Region::all();

        foreach ($regions as $region) {
            $data = null;

            // Coba tiap token sampai dapat data
            foreach ($tokens as $token) {
                $url = "https://api.waqi.info/feed/{$region->url}/?token={$token}";
                $response = Http::get($url);

                if ($response->successful() && $response['status'] === 'ok') {
                    $data = $response['data'];
                    break;
                }
            }

            if (!$data) {
                Log::warning("Gagal ambil data untuk region: {$region->name}");
                continue;
            }

            $iaqi = $data['iaqi'];
            $timestamp = $data['time']['s'];

            // Simpan data ke tabel iaqi
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
        }

        Log::info('Sinkronisasi data IAQI selesai');
    }
}
