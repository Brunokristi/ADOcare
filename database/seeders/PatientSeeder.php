<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Patient;
use Illuminate\Database\Seeder;

class PatientSeeder extends Seeder
{
    public function run(): void
    {

        // For each branch generate multiple patients
        $branches = Branch::all();

        $branches->each(function (Branch $branch) {
            $patients = Patient::factory()->count(random_int(20, 60))->create();

            // For each patient pick a single random user from the branch (if any)
            foreach ($patients as $patient) {
                $randomUser = $branch->users()->inRandomOrder()->first();

                if ($randomUser) {
                    $patient->nurse_id = $randomUser->id;
                }

                $patient->branch_id = $branch->id;

                // Add a random doctor if available
                $doctor = \App\Models\Doctor::inRandomOrder()->first();
                if ($doctor) {
                    $patient->doctor_id = $doctor->id;
                }

                $patient->save();
            }
        });
    }
}
