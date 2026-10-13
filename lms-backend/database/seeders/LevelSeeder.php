<?php

namespace Database\Seeders;

use App\Models\Level;
use Illuminate\Database\Seeder;

class LevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            ['name' => 'Pemula', 'min_xp' => 0],
            ['name' => 'Menengah', 'min_xp' => 100],
            ['name' => 'Mahir', 'min_xp' => 500],
            ['name' => 'Ahli', 'min_xp' => 1000],
        ];

        foreach ($levels as $level) {
            Level::updateOrCreate(
                ['name' => $level['name']],
                ['min_xp' => $level['min_xp']]
            );
        }
    }
}
