<?php

namespace App\Http\Controllers;

use App\Models\IAQI;
use App\Models\PredictIAQI;
use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Index extends Controller
{
    public function index()
    {
        // $datas = Region::with('latestIAQI')
        //     ->whereNotNull('latitude')
        //     ->whereNotNull('longitude')
        //     ->get()
        //     ->filter(function ($region) {
        //         return $region->latestIAQI !== null;
        //     })
        //     ->values(); // Reset index agar bisa dibaca dengan baik di JSON

        // Mengambil data IAQI dari cache atau database
        $iaqiData = Cache::get('iaqi_data_all_regions');

        // Jika data tidak ada di cache, ambil dari database
        if (!$iaqiData) {
            $iaqiData = IAQI::with('region')->get(); // Mengambil semua data IAQI dan terkait dengan Region
            // Menyimpan data di cache agar tidak perlu mengambil dari database lagi di waktu berikutnya
            Cache::put('iaqi_data_all_regions', $iaqiData, 3600); // Cache selama 1 jam
        }

        // Mengambil data prediksi IAQI dari cache atau database
        $predictedRegions = Cache::get('predicted_regions');

        // Jika data tidak ada di cache, ambil dari database
        if (!$predictedRegions) {
            $predictedRegions = PredictIAQI::with('region')->get(); // Mengambil semua data prediksi IAQI dan terkait dengan Region
            // Menyimpan data di cache agar tidak perlu mengambil dari database lagi di waktu berikutnya
            Cache::put('predicted_regions', $predictedRegions, 86400); // Cache selama 24 jam
        }

        // Menggabungkan kedua data dari cache atau database untuk ditampilkan di halaman
        return view('index', compact('iaqiData', 'predictedRegions'));
    }

    public function show($region_id)
    {
        $region = Region::find($region_id);
        if (!$region) {
            return redirect()->back()->with('error', 'Wilayah tidak ditemukan');
        }

        $iaqi = IAQI::where('region_id', $region_id)
            ->orderBy('observed_at', 'desc')
            ->first();
        if (!$iaqi) {
            return redirect()->back()->with('error', 'Data IAQI tidak ditemukan untuk wilayah ini');
        }

        $cacheKey = "predicted_region_{$region_id}";

        // 1. Cek cache
        if (Cache::has($cacheKey)) {
            $data = Cache::get($cacheKey);

            return view('detail', [
                'source' => 'cache',
                'iaqi'   => $iaqi,
                'data'   => $data
            ]);
        }

        // 2. Ambil data prediksi berdasarkan region_id
        $prediction = PredictIAQI::with('region')
            ->where('region_id', $region_id)
            ->first();

        if (!$prediction) {
            // Bisa redirect atau tampilkan halaman khusus
            return redirect()->back()->with('error', 'Data prediksi tidak ditemukan');
        }

        // 3. Format data untuk dikirim ke view
        $data = [
            'region_id'   => $prediction->region_id,
            'region_name' => $prediction->region->name ?? null,
            'date'        => optional($prediction->date)->toDateString(),
            'pm25'        => $prediction->predicted_pm25,
            'aqi_us_epa'  => $prediction->predicted_aqi,
            'cat_us_epa'  => $prediction->predicted_category,
            'ispu'        => $prediction->predicted_ispu,
            'cat_ispu'    => $prediction->predicted_category_ispu,
            'cv_metrics_svr'      => json_decode($prediction->cv_metrics_svr, true),
            'cv_metrics_baseline' => json_decode($prediction->cv_metrics_baseline, true),
            'model_info'          => json_decode($prediction->model_info, true),
        ];

        // 4. Simpan cache 1 hari
        Cache::put($cacheKey, $data, now()->addDay());

        // 5. Kirim ke view
        return view('detail', [
            'source' => 'database',
            'iaqi'   => $iaqi,
            'data'   => $data
        ]);
    }
}
