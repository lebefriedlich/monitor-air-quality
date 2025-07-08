<?php

namespace Database\Seeders;

use App\Models\Attribution;
use App\Models\Token;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $attributions = [
            [
                'name' => 'BMKG | Badan Meteorologi, Klimatologi dan Geofisika',
                'logo' => 'Indonesia-Badan-Meteorologi-Klimatologi-dan-Geofisika.png',
                'url' => 'http://www.bmkg.go.id/'
            ],
            [
                'name' => 'World Air Quality Index Project',
                'logo' => 'WAQI.png',
                'url' => 'https://waqi.info/'
            ],
        ];

        foreach ($attributions as $attribution) {
            Attribution::create($attribution);
        }
    }
}
