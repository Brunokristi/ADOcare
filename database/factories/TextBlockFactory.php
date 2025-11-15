<?php

namespace Database\Factories;

use App\Models\TextBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

class TextBlockFactory extends Factory
{
    protected $model = TextBlock::class;

    public function definition(): array
    {
        return [
            'text' => $this->faker->paragraph(),
            'position' => $this->faker->numberBetween(1, 10),
        ];
    }
}
