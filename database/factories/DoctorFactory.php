<?php

namespace Database\Factories;

use App\Models\Doctor;
use Illuminate\Database\Eloquent\Factories\Factory;

class DoctorFactory extends Factory
{
    protected $model = Doctor::class;

    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'title' => $this->faker->title(),
            'zpr' => $this->faker->bothify('ZPR#####'),
            'pzs' => $this->faker->bothify('PZS#####'),
        ];
    }
}
