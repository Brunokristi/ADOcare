<?php

namespace Database\Factories;

use App\Models\Macro;
use Illuminate\Database\Eloquent\Factories\Factory;

class MacroFactory extends Factory
{
    protected $model = Macro::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'abbreviation' => strtoupper($this->faker->bothify('??##')),
            'text' => $this->faker->paragraphs(2, true),
            'user_id' => null,
        ];
    }
}
