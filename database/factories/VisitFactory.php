<?php

namespace Database\Factories;

use App\Models\Visit;
use App\Models\Patient;
use App\Models\User;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

class VisitFactory extends Factory
{
    protected $model = Visit::class;

    public function definition(): array
    {
        return [
            'date' => $this->faker->dateTimeBetween('-1 years', 'now')->format('Y-m-d'),
            'patient_id' => Patient::inRandomOrder()->value('id') ?? null,
            // Associate to an existing user and branch when available
            'user_id' => User::inRandomOrder()->value('id') ?? null,
            'branch_id' => Branch::inRandomOrder()->value('id') ?? null,
            // Times: terrain_time and administrative_time are stored as timestamps
            'terrain_time' => $this->faker->dateTimeBetween('-1 years', 'now')->format('Y-m-d H:i:s'),
            'administrative_time' => $this->faker->dateTimeBetween('-1 years', 'now')->format('Y-m-d H:i:s'),
            'time_on_location' => $this->faker->numberBetween(60, 7200),
            'distance_to_location' => $this->faker->numberBetween(100, 50000),
            'time_to_location' => $this->faker->numberBetween(60, 3600),
        ];
    }
}
