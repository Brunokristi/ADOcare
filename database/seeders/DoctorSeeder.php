<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Doctor;
use Illuminate\Database\Seeder;

class DoctorSeeder extends Seeder
{
    public function run(): void
    {
        Doctor::factory(20)->create();

        $companies = Company::all();
        foreach ($companies as $company) {
            $doctors = Doctor::inRandomOrder()->take(rand(1, 5))->get();
            foreach ($doctors as $doctor) {
                $company->branches->each(function ($branch) use ($doctor) {
                    $branch->favourite_doctors()->attach($doctor->id);
                });
            }
        }
    }
}
