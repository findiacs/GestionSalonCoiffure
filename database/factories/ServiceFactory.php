<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'salon_id' => 1,
            'nom_service' => $this->faker->word,
            'duree_minu' => 30,
            'prix' => 50,
            'actif' => true,
        ];
    }
}
