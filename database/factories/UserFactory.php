<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Branch;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current pin being used by the factory.
     */
    protected static ?string $pin;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'login' => fake()->unique()->userName(),
            'pin' => static::$pin ??= '1234',
            'initials' => strtoupper(string: substr($this->faker->firstName, 0, 1) . substr($this->faker->lastName, 0, 1)),
            'title' => null,
            'code' => null,
            'phone_number' => fake()->phoneNumber(),
        ];
    }
}
