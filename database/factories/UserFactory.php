<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

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
            'password' => static::$password ??= 'password',
            'initials' => strtoupper(substr($this->faker->firstName, 0, 1).substr($this->faker->lastName, 0, 1)),
            'title' => null,
            'code' => null,
            'phone_number' => fake()->phoneNumber(),
            'remember_token' => Str::random(10),
        ];
    }
}
