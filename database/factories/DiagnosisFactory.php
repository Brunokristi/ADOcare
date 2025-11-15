<?php

namespace Database\Factories;

use App\Models\Diagnosis;
use Illuminate\Database\Eloquent\Factories\Factory;

class DiagnosisFactory extends Factory
{
    protected $model = Diagnosis::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->bothify('??#')), // e.g. AB1
            'description' => $this->faker->sentence(6),
        ];
    }
}
