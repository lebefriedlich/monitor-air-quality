<?php

namespace App\Console\Commands;

use App\Models\Region;
use App\Models\Token;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncRegionAirQuality extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-region-air-quality';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi data wilayah dari API WAQI menggunakan token yang tersedia.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info('Sinkronisasi data wilayah dari API WAQI dimulai');
        $tokens = Token::pluck('token')->toArray(); // Ambil semua token sebagai array

        $data = null;

        foreach ($tokens as $token) {
            $response = Http::get("https://api.waqi.info/search/", [
                'keyword' => 'indonesia',
                'token' => $token
            ]);

            if ($response->successful() && isset($response['data'])) {
                $data = $response['data'];
                Log::info("Sukses mengambil data dengan token: $token");
                break; // Keluar dari loop jika sukses
            } else {
                Log::warning("Gagal mengambil data dengan token: $token. Response: " . $response->body());
            }
        }

        if (!$data) {
            Log::error('Tidak ada data yang berhasil diambil dari API WAQI dengan semua token yang tersedia.');
            return;
        }

        $fetchedUrls = [];

        foreach ($data as $item) {
            $station = $item['station'];
            $geo = $station['geo'];
            $url = $station['url'];

            Region::updateOrCreate(
                ['url' => $url],
                [
                    'name' => $station['name'],
                    'latitude' => $geo[0],
                    'longitude' => $geo[1],
                    'url' => $url
                ]
            );

            $fetchedUrls[] = $url;
        }

        Region::whereNotIn('url', $fetchedUrls)->delete();

        Log::info('Sinkronisasi data wilayah dari API WAQI selesai');
    }
}
