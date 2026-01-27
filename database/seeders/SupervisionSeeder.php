<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Airport;
use App\Models\Airline;

class SupervisionSeeder extends Seeder
{
    public function run()
    {
        $airports = [
            'Sultan Aji Muhammad Sulaiman Sepinggan Balikpapan',
            'Juwata Tarakan',
            'Aji Pangeran Tumenggung Pranoto Samarinda',
            'Iskandar Pangkalan Bun',
            'Tjilik Riwut Palangka Raya',
            'Kalimaru Berau',
            'RA Bessing Malinau',
        ];

        foreach ($airports as $airport) {
            Airport::firstOrCreate(['name' => $airport]);
        }

        $airlines = [
            ['name' => 'Citilink', 'color' => '#4CAF50'],
            ['name' => 'Lion Air', 'color' => '#F44336'],
            ['name' => 'Super Air Jet', 'color' => '#FF9800'],
            ['name' => 'Air Asia', 'color' => '#E91E63'],
            ['name' => 'Fly Jaya', 'color' => '#9C27B0'],
            ['name' => 'Wings Air', 'color' => '#673AB7'],
            ['name' => 'Sriwijaya', 'color' => '#3F51B5'],
            ['name' => 'Garuda Indonesia', 'color' => '#2196F3'],
            ['name' => 'Batik Air', 'color' => '#03A9F4'],
            ['name' => 'Pelita Air', 'color' => '#00BCD4'],
            ['name' => 'Nam Air', 'color' => '#009688'],
            ['name' => 'Susi Air', 'color' => '#8BC34A'],
        ];

        foreach ($airlines as $airline) {
            Airline::updateOrCreate(
                ['name' => $airline['name']],
                ['color' => $airline['color']]
            );
        }
    }
}
