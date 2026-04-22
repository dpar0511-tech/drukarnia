<?php

namespace Database\Seeders;

use App\Models\PoziomLojalnosci;
use Illuminate\Database\Seeder;

class PoziomLojalnosciSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $levels = [
            ['nazwa' => 'Standard', 'prog_obrotow' => 0, 'rabat_procent' => 0],
            ['nazwa' => 'Brązowy', 'prog_obrotow' => 5000, 'rabat_procent' => 3],
            ['nazwa' => 'Srebrny', 'prog_obrotow' => 15000, 'rabat_procent' => 7],
            ['nazwa' => 'Złoty', 'prog_obrotow' => 50000, 'rabat_procent' => 12],
            ['nazwa' => 'VIP', 'prog_obrotow' => 100000, 'rabat_procent' => 20],
        ];

        foreach ($levels as $level) {
            PoziomLojalnosci::updateOrCreate(
                ['nazwa' => $level['nazwa']],
                $level
            );
        }
    }
}
