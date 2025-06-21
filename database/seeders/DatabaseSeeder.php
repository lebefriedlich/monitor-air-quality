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
        $tokens = [
            '6800add0de94e473e10c4399ab50b898f33f8ad3',
            '9d7d44d4523e7f4926caf1f1c49f49fbde7a3efd',
            '466c042386905f85eba7eaac7213376b335ccbdf'
        ];

        foreach ($tokens as $token) {
            Token::create([
                'token' => $token
            ]);
        }

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
