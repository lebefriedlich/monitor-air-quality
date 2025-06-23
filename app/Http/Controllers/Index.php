<?php

namespace App\Http\Controllers;

use App\Models\Region;
use Illuminate\Http\Request;

class Index extends Controller
{
    public function index()
    {
        $datas = Region::with('latestIaqi')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->filter(function ($region) {
                return $region->latestIaqi !== null;
            })
            ->values(); // Reset index agar bisa dibaca dengan baik di JSON

        return view('index', [
            'datas' => $datas
        ]);
    }
}
