<?php

namespace Database\Seeders\Demo;

use App\Models\Procedure;
use Illuminate\Database\Seeder;

class ProcedureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (!env('SEED_SAMPLE_DATA', false)) {
            return;
        }

        for ($i = 0; $i < 30; $i++) {
            $attrs = Procedure::factory()->make()->toArray();
            \App\Models\Procedure::firstOrCreate(
                ['code' => $attrs['code']],
                $attrs
            );
        }
    }
}
