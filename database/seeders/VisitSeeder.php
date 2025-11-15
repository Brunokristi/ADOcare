<?php

namespace Database\Seeders;

use App\Models\Visit;
use App\Models\VisitText;
use App\Models\TextBlock;
use App\Models\Patient;
use Illuminate\Database\Seeder;

class VisitSeeder extends Seeder
{
    public function run(): void
    {
        $patients = Patient::all();
        if ($patients->isEmpty()) {
            return;
        }

        $textIds = TextBlock::pluck('id');

        foreach ($patients as $patient) {
            $count = rand(0, 4);
            for ($i = 0; $i < $count; $i++) {
                $visit = Visit::factory()->create([
                    'patient_id' => $patient->id,
                ]);

                // attach 0-3 text blocks to each visit
                if ($textIds->isNotEmpty()) {
                    $k = rand(0, min(3, $textIds->count()));
                    if ($k > 0) {
                        $attach = $textIds->random($k);

                        // normalize to array of ids
                        if ($attach instanceof \Illuminate\Support\Collection) {
                            $attachIds = $attach->all();
                        } elseif (is_array($attach)) {
                            $attachIds = $attach;
                        } else {
                            $attachIds = [$attach];
                        }

                        foreach ($attachIds as $t) {
                            // visit_texts is a pivot-like table without an auto-increment id;
                            // use query builder insert to avoid Eloquent expecting an 'id' column.
                            \Illuminate\Support\Facades\DB::table('visit_texts')->insert([
                                'visit_id' => $visit->id,
                                'text_id' => $t,
                            ]);
                        }
                    }
                }
            }
        }
    }
}
