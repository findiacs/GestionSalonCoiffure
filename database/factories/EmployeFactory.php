<?php

namespace Database\Factories;

use App\Models\Employe;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeFactory extends Factory
{
    protected $model = Employe::class;

    public function definition(): array
    {
        return [
            'salon_id' => 1,
            'nom' => $this->faker->lastName,
            'prenom' => $this->faker->firstName,
            'actif' => true,
        ];
    }
}
