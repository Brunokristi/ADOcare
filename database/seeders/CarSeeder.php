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

        // create some cars, associating to a random company and picking a user from the same company when possible
        Car::factory(20)->create()->each(function ($car) use ($companies) {
            if ($companies->count()) {
                $company = $companies->random();
                $car->company_id = $company->id;

                // pick a user that is assigned to any branch of the selected company
                $userId = User::whereHas('branches', function ($q) use ($company) {
                    $q->where('company_id', $company->id);
                })->inRandomOrder()->value('id');

                if ($userId) {
                    $car->user_id = $userId;
                }
            }

            $car->save();
        });
    }
}
