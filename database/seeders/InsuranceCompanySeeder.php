<?php

namespace Database\Seeders;

use App\Models\InsuranceCompany;
use App\Models\Patient;
use Illuminate\Database\Seeder;

class InsuranceCompanySeeder extends Seeder
{
    public function run(): void
    {
        $companies = InsuranceCompany::factory(10)->create();

        // Assign some patients randomly to insurance companies
        $patients = Patient::all();
        if ($patients->isEmpty()) {
            return;
        }

        foreach ($patients as $patient) {
            if (rand(0, 1) === 1) {
                $patient->insurance_company_id = $companies->random()->id;
                $patient->save();
            }
        }
    }
}
