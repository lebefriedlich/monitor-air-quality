<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\IAQI;
use Carbon\Carbon;

class AggregateIAQI extends Command
{
    protected $signature = 'iaqi:aggregate-daily';
    protected $description = 'Agregasi harian (median) dan simpan 1 baris per hari per region';

    public function handle()
    {
        $this->info("Mulai agregasi harian IAQI (median-only)...");

        // Ambil daftar region unik
        $regions = IAQI::select('region_id')->distinct()->pluck('region_id');

        foreach ($regions as $regionId) {
            // Ambil tanggal unik untuk region ini
            $dates = IAQI::where('region_id', $regionId)
                         ->selectRaw('DATE(observed_at) as d')
                         ->distinct()
                         ->pluck('d');

            foreach ($dates as $date) {
                $this->processDay($regionId, $date);
            }
        }

        $this->info("Agregasi selesai.");
        return Command::SUCCESS;
    }

    private function processDay($regionId, $date)
    {
        $this->info("Mengolah region $regionId tanggal $date ...");

        // Ambil seluruh data harian
        $rows = IAQI::where('region_id', $regionId)
                    ->whereDate('observed_at', $date)
                    ->orderBy('observed_at')
                    ->get();

        if ($rows->isEmpty()) {
            $this->warn("Tidak ada data untuk tanggal $date, skip.");
            return;
        }

        // Kolom numerik untuk median
        $cols = ['dew', 'h', 'p', 'pm25', 'r', 't', 'w'];

        // Hasil agregasi per hari
        $result = [
            'region_id'     => $regionId,
            'observed_at'   => Carbon::parse($date)->setTime(1, 0, 0), // Jam 01:00:00
            'dominent_pol'  => 'pm25', // ✅ selalu pm25 sesuai aturan
        ];

        // Hitung median untuk tiap kolom
        foreach ($cols as $col) {
            $values = $rows->pluck($col)
                           ->filter(fn ($v) => !is_null($v))
                           ->sort()
                           ->values();

            if ($values->isEmpty()) {
                $result[$col] = null;
                continue;
            }

            $count = $values->count();

            // Median ganjil/genap
            if ($count % 2 === 1) {
                $median = $values->get(intval($count / 2));
            } else {
                $median = (
                    $values->get($count / 2 - 1) +
                    $values->get($count / 2)
                ) / 2;
            }

            $result[$col] = $median;
        }

        // Hapus data harian lama
        IAQI::where('region_id', $regionId)
            ->whereDate('observed_at', $date)
            ->delete();

        // Insert 1 baris agregasi
        IAQI::create($result);

        $this->info("→ Selesai agregasi & replace data untuk tanggal $date");
    }
}
