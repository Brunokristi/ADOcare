<?php

namespace Database\Factories;

use App\Models\Visit;
use App\Models\Patient;
use App\Models\ReportMonth;
use Illuminate\Database\Eloquent\Factories\Factory;

class VisitFactory extends Factory
{
    protected $model = Visit::class;

    public function definition(): array
    {
        return [
            'date' => $this->faker->dateTimeBetween('-1 years', 'now')->format('Y-m-d'),
            'examination' => $this->faker->dateTimeBetween('-1 years', 'now')->format('Y-m-d H:i:s'),
            'statement' => $this->faker->dateTimeBetween('-1 years', 'now')->format('Y-m-d H:i:s'),
            'patient_id' => Patient::inRandomOrder()->value('id') ?? null,
            'month_id' => ReportMonth::inRandomOrder()->value('id') ?? null,
        ];
    }
}
