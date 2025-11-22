<?php

namespace Database\Seeders;

use App\Models\Car;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

class CarSeeder extends Seeder
{
    public function run(): void
    {
        $companies = Company::all();
        $users = User::all();

        // create some cars, associating to random company and user
        Car::factory(20)->create()->each(function ($car) use ($companies, $users) {
            if ($companies->count()) {
                $car->company_id = $companies->random()->id;
            }
            if ($users->count()) {
                $car->user_id = $users->random()->id;
            }
            $car->save();
        });
    }
}
