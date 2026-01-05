<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Doctor;
use Illuminate\Database\Seeder;

class DoctorSeeder extends Seeder
{
    public function run(): void
    {

        $companies = Company::all();
        foreach ($companies as $company) {
            $doctors = Doctor::factory(rand(4, 10))->create();
            foreach ($doctors as $doctor) {
                $company->branches->each(function ($branch) use ($doctor) {
                    $branch->doctors()->attach($doctor->id);
                });
            }
        }
    }
}
