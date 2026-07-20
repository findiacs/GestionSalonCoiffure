<?php

namespace Database\Factories;

use App\Models\Ville;
use Illuminate\Database\Eloquent\Factories\Factory;

class VilleFactory extends Factory
{
    protected $model = Ville::class;

    public function definition(): array
    {
        return [
            'nom_ville' => $this->faker->city,
            'code_postal' => $this->faker->postcode,
            'region' => 'Rabat-Salé-Kénitra',
            'pays' => 'Maroc',
            'actif' => true,
        ];
    }
}
