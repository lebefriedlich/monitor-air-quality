<?php

namespace App\Console\Commands;

use App\Models\IAQI;
use Illuminate\Console\Command;

class CalculateAqiCategory extends Command
{
    protected $signature = 'iaqi:calculate-category';
    protected $description = 'Hitung AQI ISPU dan US EPA berdasarkan PM2.5 menggunakan interpolasi linier';

    public function handle()
    {
        $this->info("Menghitung AQI dan kategori ISPU + US EPA...");

        $records = IAQI::whereNotNull('pm25')->get();

        foreach ($records as $rec) {
            $pm25 = $rec->pm25;

            if (!is_numeric($pm25)) {
                $this->error("Invalid pm25 value for record ID {$rec->id}. Skipping...");
                continue; // Skip this record if the pm25 value is invalid
            }

            // Hitung AQI dengan interpolasi
            $aqiIspu = $this->calculateIspu($pm25);
            $aqiUs   = $this->calculateUsAqi($pm25);

            // Simpan nilai AQI
            $rec->aqi_ispu = $aqiIspu;
            $rec->aqi_us   = $aqiUs;

            // Simpan kategori
            $rec->category_ispu = $this->getIspuCategory($aqiIspu);
            $rec->category_us   = $this->getUsCategory($aqiUs);

            if (!mb_check_encoding($rec->category_ispu, 'UTF-8')) {
                $this->error("Invalid encoding detected.");
            }

            $rec->save();
        }

        $this->info("✅ Selesai menghitung AQI + kategori ISPU & US EPA.");
        return 0;
    }

    // ===================== RUMUS INTERPOLASI =======================

    private function linearAqi($Cp, $BP_Hi, $BP_Lo, $I_Hi, $I_Lo)
    {
        return (($I_Hi - $I_Lo) / ($BP_Hi - $BP_Lo)) * ($Cp - $BP_Lo) + $I_Lo;
    }


    // ===================== ISPU (KLHK P.14/2020) =======================

    private function calculateIspu($Cp)
    {
        $ranges = [
            [0.0, 15.5, 0, 50],
            [15.6, 55.4, 51, 100],
            [55.5, 150.4, 101, 200],
            [150.5, 250.4, 201, 300],
            [250.5, 500.0, 301, 500],
        ];

        foreach ($ranges as [$BP_Lo, $BP_Hi, $I_Lo, $I_Hi]) {
            if ($Cp >= $BP_Lo && $Cp <= $BP_Hi) {
                return $this->linearAqi($Cp, $BP_Hi, $BP_Lo, $I_Hi, $I_Lo);
            }
        }

        return null;
    }

    private function getIspuCategory($value)
    {
        if ($value === null) return null;

        return match (true) {
            $value <= 50   => 'Baik',
            $value <= 100  => 'Sedang',
            $value <= 200  => 'Tidak Sehat',
            $value <= 300  => 'Sangat Tidak Sehat',
            default        => 'Berbahaya',
        };
    }


    // ===================== US EPA =======================

    private function calculateUsAqi($Cp)
    {
        $ranges = [
            [0.0, 9.0, 0, 50],
            [9.1, 35.4, 51, 100],
            [35.5, 55.4, 101, 150],
            [55.5, 125.4, 151, 200],
            [125.5, 225.4, 201, 300],
            [225.5, 500.4, 301, 500],
        ];

        foreach ($ranges as [$BP_Lo, $BP_Hi, $I_Lo, $I_Hi]) {
            if ($Cp >= $BP_Lo && $Cp <= $BP_Hi) {
                return $this->linearAqi($Cp, $BP_Hi, $BP_Lo, $I_Hi, $I_Lo);
            }
        }

        return null;
    }

    private function getUsCategory($value)
    {
        if ($value === null) return null;

        return match (true) {
            $value <= 50   => 'Baik',
            $value <= 100  => 'Sedang',
            $value <= 150  => 'Tidak Sehat untuk Kelompok Sensitif',
            $value <= 200  => 'Tidak Sehat',
            $value <= 300  => 'Sangat Tidak Sehat',
            default        => 'Berbahaya',
        };
    }
}
