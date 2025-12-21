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
            $branch->users()->each(function ($user) use ($patients, $branch) {
                // Attach each patient to the user with the branch pivot
                foreach ($patients as $patient) {
                    $patient->assignedUsers()->attach($user->id, ['branch_id' => $branch->id]);
                    // Add doctors and insurance companies randomly
                    $patient->doctor_id = \App\Models\Doctor::inRandomOrder()->first()->id;

                    $patient->save();
                }
            });


        });
    }
}
