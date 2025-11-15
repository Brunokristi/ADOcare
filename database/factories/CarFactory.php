<?php

namespace Database\Factories;

use App\Models\Car;
use Illuminate\Database\Eloquent\Factories\Factory;

class CarFactory extends Factory
{
    protected $model = Car::class;

    public function definition(): array
    {
        return [
            'evc' => strtoupper($this->faker->bothify('??-####')),
            'company_id' => null,
            'user_id' => null,
        ];
    }
}
