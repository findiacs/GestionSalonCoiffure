<?php

namespace Database\Factories;

use App\Models\Salon;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalonFactory extends Factory
{
    protected $model = Salon::class;

    public function definition(): array
    {
        return [
            'user_id' => 1,
            'ville_id' => 1,
            'nom_salon' => $this->faker->company, // Changed 'nom' to 'nom_salon'
            'description' => $this->faker->paragraph,
            'adresse' => $this->faker->address,
            'telephone' => $this->faker->phoneNumber,
            'valide' => 1,
        ];
    }
}
