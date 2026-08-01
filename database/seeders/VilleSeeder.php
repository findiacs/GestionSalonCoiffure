<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VilleSeeder extends Seeder
{
    public function run(): void
    {
        $villes = [
            ['nom_ville' => 'Rabat',        'code_postal' => '10000', 'region' => 'Rabat-Salé-Kénitra',         'actif' => 1],
            ['nom_ville' => 'Casablanca',    'code_postal' => '20000', 'region' => 'Grand Casablanca-Settat',    'actif' => 1],
            ['nom_ville' => 'Salé',          'code_postal' => '11000', 'region' => 'Rabat-Salé-Kénitra',         'actif' => 1],
            ['nom_ville' => 'Témara',        'code_postal' => '12000', 'region' => 'Rabat-Salé-Kénitra',         'actif' => 1],
            ['nom_ville' => 'Kénitra',       'code_postal' => '14000', 'region' => 'Rabat-Salé-Kénitra',         'actif' => 1],
            ['nom_ville' => 'Marrakech',     'code_postal' => '40000', 'region' => 'Marrakech-Safi',             'actif' => 1],
            ['nom_ville' => 'Fès',           'code_postal' => '30000', 'region' => 'Fès-Meknès',                 'actif' => 1],
            ['nom_ville' => 'Meknès',        'code_postal' => '50000', 'region' => 'Fès-Meknès',                 'actif' => 1],
            ['nom_ville' => 'Tanger',        'code_postal' => '90000', 'region' => 'Tanger-Tétouan-Al Hoceïma', 'actif' => 1],
            ['nom_ville' => 'Agadir',        'code_postal' => '80000', 'region' => 'Souss-Massa',                'actif' => 1],
            ['nom_ville' => 'Oujda',         'code_postal' => '60000', 'region' => 'Oriental',                   'actif' => 1],
            ['nom_ville' => 'Laâyoune',      'code_postal' => '70000', 'region' => 'Laâyoune-Sakia El Hamra',   'actif' => 0],
        ];

        foreach ($villes as $v) {
            DB::table('villes')->insertOrIgnore(array_merge($v, ['pays' => 'Maroc']));
        }

        $this->command->info('✓ VilleSeeder : '.count($villes).' villes insérées.');
    }
}
