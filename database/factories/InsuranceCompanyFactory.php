<?php

namespace Database\Factories;

use App\Models\InsuranceCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

class InsuranceCompanyFactory extends Factory
{
    protected $model = InsuranceCompany::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'psc' => $this->faker->postcode(),
            'ico' => $this->faker->numerify('########'),
            'dic' => $this->faker->numerify('#########'),
            'ic_dph' => $this->faker->boolean(20) ? 'yes' : 'no',
            'register' => $this->faker->companySuffix(),
            'code' => strtoupper($this->faker->bothify('IC-###')),
            'branch_code' => strtoupper($this->faker->bothify('BR-#')),
        ];
    }
}
