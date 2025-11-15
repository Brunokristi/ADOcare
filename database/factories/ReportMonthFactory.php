<?php

namespace Database\Factories;

use App\Models\ReportMonth;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportMonthFactory extends Factory
{
    protected $model = ReportMonth::class;

    public function definition(): array
    {
        $date = $this->faker->dateTimeBetween('-6 months', 'now');
        return [
            'month' => (int) $date->format('m'),
            'year' => (int) $date->format('Y'),
            'examination_start' => $this->faker->time('H:i:s'),
            'examination_end' => $this->faker->time('H:i:s'),
            'statement_start' => $this->faker->time('H:i:s'),
            'statement_end' => $this->faker->time('H:i:s'),
            'first_day' => $date->format('Y-m-01'),
            'last_day' => $date->format('Y-m-t'),
            'user_id' => null,
            'branch_id' => null,
        ];
    }
}
