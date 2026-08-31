<?php

namespace App\Http\Controllers;

use App\Models\IAQI;
use App\Models\ForecastIAQI;
use App\Models\Region;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class Index extends Controller
{
    public function index()
    {
        $iaqiData = Cache::get('iaqi_data_all_regions');

        if (!$iaqiData) {
            $targetDate = '2026-01-22';
            // $targetDate = Carbon::now()->toDateString();

            $latestPerRegion = IAQI::selectRaw('region_id, MAX(observed_at) as observed_at')
                ->whereDate('observed_at', $targetDate)
                ->groupBy('region_id');

            $iaqiData = IAQI::joinSub($latestPerRegion, 'latest', function ($join) {
                $join->on('iaqi.region_id', '=', 'latest.region_id')
                    ->on('iaqi.observed_at', '=', 'latest.observed_at');
            })
                ->with('region')
                ->orderBy('iaqi.region_id', 'asc')
                ->get()
                ->map(function ($iaqi) {
                    $region = $iaqi->region;
                    return [
                        'id'        => $region->id,
                        'name'      => $region->name,
                        'city'      => $region->city,
                        'latitude'  => $region->latitude,
                        'longitude' => $region->longitude,
                        'url'       => $region->url,
                        'iaqi'      => $iaqi->toArray(),
                        'status'    => 'database',
                    ];
                })
                ->values()
                ->all();

            Cache::put('iaqi_data_all_regions', $iaqiData, 3600);
        }

        $forecastRegions = Cache::get('forecast_regions');

        if (!$forecastRegions) {
            $forecastRegions = ForecastIAQI::with('region')
                ->get()
                ->map(function ($forecast) {
                    return [
                        'region_id'             => $forecast->region_id,
                        'region_name'           => $forecast->region->name ?? null,
                        'date'                  => optional($forecast->date)->toDateString(),
                        'forecast_pm25'         => $forecast->forecast_pm25,
                        'forecast_aqi'          => $forecast->forecast_aqi,
                        'forecast_category'     => $forecast->forecast_category,
                        'forecast_ispu'         => $forecast->forecast_ispu,
                        'forecast_category_ispu' => $forecast->forecast_category_ispu,
                        'cv_metrics_svr'        => $forecast->cv_metrics_svr,
                        'cv_metrics_baseline'   => $forecast->cv_metrics_baseline,
                        'cv_metrics_xgboost'   => $forecast->cv_metrics_xgboost,
                        'model_info'            => $forecast->model_info,
                    ];
                })
                ->values()
                ->all();

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

        // $targetDate = Carbon::now()->toDateString();
        $targetDate = '2026-01-22';

        $iaqi = IAQI::where('region_id', $region->id)
            ->whereDate('observed_at', $targetDate)
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
            'forecast_pm25'        => $forecast->forecast_pm25,
            'forecast_aqi'  => $forecast->forecast_aqi,
            'forecast_category'  => $forecast->forecast_category,
            'forecast_ispu'        => $forecast->forecast_ispu,
            'forecast_category_ispu'    => $forecast->forecast_category_ispu,
            'cv_metrics_svr' => is_array($forecast->cv_metrics_svr)
                ? $forecast->cv_metrics_svr
                : json_decode($forecast->cv_metrics_svr, true),
            'cv_metrics_baseline' => is_array($forecast->cv_metrics_baseline)
                ? $forecast->cv_metrics_baseline
                : json_decode($forecast->cv_metrics_baseline, true),
            'cv_metrics_xgboost' => is_array($forecast->cv_metrics_xgboost)
                ? $forecast->cv_metrics_xgboost
                : json_decode($forecast->cv_metrics_xgboost, true),
            'model_info'          => is_array($forecast->model_info)
                ? $forecast->model_info
                : json_decode($forecast->model_info, true),
        ];

        // 4. Simpan cache permanent
        Cache::put($cacheKey, $data, 86400);

        // 5. Kirim ke view
        return view('detail', [
            'source' => 'database',
            'region' => $region,
            'iaqi'   => $iaqi,
            'data'   => $data
        ]);
    }
}
