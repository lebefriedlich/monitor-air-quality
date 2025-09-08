<?php

namespace App\Http\Controllers;

use App\Models\Region;
use Illuminate\Http\Request;

class Index extends Controller
{
    public function index()
    {
        $datas = Region::with('latestAQI')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->filter(function ($region) {
                return $region->latestAQI !== null;
            })
            ->values(); // Reset index agar bisa dibaca dengan baik di JSON

        return view('index', [
            'datas' => $datas
        ]);
    }
}
