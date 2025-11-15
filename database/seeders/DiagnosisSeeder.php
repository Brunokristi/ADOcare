<?php

namespace Database\Seeders;

use App\Models\Diagnosis;
use Illuminate\Database\Seeder;

class DiagnosisSeeder extends Seeder
{
    public function run(): void
    {
        // Create diagnoses idempotently by code
        for ($i = 0; $i < 30; $i++) {
            $attrs = Diagnosis::factory()->make()->toArray();
            \App\Models\Diagnosis::firstOrCreate(
                ['code' => $attrs['code']],
                $attrs
            );
        }
    }
}
