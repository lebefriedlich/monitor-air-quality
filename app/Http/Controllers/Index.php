<?php

namespace App\Http\Controllers;

use App\Models\IAQI;
use App\Models\ForecastIAQI;
use App\Models\Region;
use Illuminate\Support\Facades\Cache;

class Index extends Controller
{
    public function index()
    {
        $iaqiData = Cache::get('iaqi_data_all_regions');

        if (!$iaqiData) {
            $iaqiData = IAQI::with('region')
                // ->whereDate('observed_at', '2026-01-03')
                ->orderBy('region_id', 'asc')
                ->get();

            Cache::put('iaqi_data_all_regions', $iaqiData, 3600);
        }

        $forecastRegions = Cache::get('forecast_regions');

        if (!$forecastRegions) {
            $forecastRegions = ForecastIAQI::with('region')->get();
            Cache::put('forecast_regions', $forecastRegions, 86400);
        }

        return view('index', compact('iaqiData', 'forecastRegions'));
    }

    public function show($name)
    {
        $region = Region::where('name', $name)->first();
        if (!$region) {
            return redirect()->back()->with('error', 'Wilayah tidak ditemukan');
        }

        $iaqi = IAQI::where('region_id', $region->id)
            // ->whereDate('observed_at', '2026-01-03')
            ->latest('observed_at')
            ->first();
        if (!$iaqi) {
            return redirect()->back()->with('error', 'Data IAQI tidak ditemukan untuk wilayah ini');
        }

        $cacheKey = "forecast_region_{$region->id}";

        if (Cache::has($cacheKey)) {
            $data = Cache::get($cacheKey);

            return view('detail', [
                'source' => 'cache',
                'region' => $region,
                'iaqi'   => $iaqi,
                'data'   => $data
            ]);
        }

        $forecast = ForecastIAQI::with('region')
            ->where('region_id', $region->id)
            ->first();

        if (!$forecast) {
            return redirect()->back()->with('error', 'Data peramalan tidak ditemukan');
        }

        $data = [
            'region_id'   => $forecast->region_id,
            'region_name' => $forecast->region->name ?? null,
            'date'        => optional($forecast->date)->toDateString(),
            'pm25'        => $forecast->forecast_pm25,
            'aqi_us_epa'  => $forecast->forecast_aqi,
            'cat_us_epa'  => $forecast->forecast_category,
            'ispu'        => $forecast->forecast_ispu,
            'cat_ispu'    => $forecast->forecast_category_ispu,
            'cv_metrics_svr' => is_array($forecast->cv_metrics_svr)
                ? $forecast->cv_metrics_svr
                : json_decode($forecast->cv_metrics_svr, true),
            'cv_metrics_baseline' => is_array($forecast->cv_metrics_baseline)
                ? $forecast->cv_metrics_baseline
                : json_decode($forecast->cv_metrics_baseline, true),
            'model_info'          => is_array($forecast->model_info)
                ? $forecast->model_info
                : json_decode($forecast->model_info, true),
        ];

        // 4. Simpan cache permanent
        Cache::forever($cacheKey, $data);

        // 5. Kirim ke view
        return view('detail', [
            'source' => 'database',
            'region' => $region,
            'iaqi'   => $iaqi,
            'data'   => $data
        ]);
    }
}
