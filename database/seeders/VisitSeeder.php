<?php

namespace Database\Seeders;

use App\Models\Visit;
use App\Models\VisitText;
use App\Models\TextBlock;
use App\Models\Patient;
use App\Models\User;
use App\Models\Branch;
use Carbon\Carbon;
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

                // Assign optional new fields: user, branch and timeline metrics
                $userId = User::inRandomOrder()->value('id') ?? null;
                $branchId = Branch::inRandomOrder()->value('id') ?? null;

                // Base terrain time on visit date (if set) or now
                try {
                    $baseDate = $visit->date ? Carbon::parse($visit->date) : Carbon::now();
                } catch (\Throwable $e) {
                    $baseDate = Carbon::now();
                }

                // pick a random time during the day for terrain and administrative times
                $terrainTime = $baseDate->copy()->setTime(rand(6, 17), rand(0, 59), rand(0, 59));
                $administrativeTime = $terrainTime->copy()->addMinutes(rand(5, 60));

                $timeOnLocation = rand(60, 3600); // seconds spent at patient
                $distanceToLocation = rand(0, 20000); // meters from previous location
                $timeToLocation = rand(30, 3600); // seconds to reach location

                $visit->user_id = $userId;
                $visit->branch_id = $branchId;
                $visit->terrain_time = $terrainTime;
                $visit->administrative_time = $administrativeTime;
                $visit->time_on_location = $timeOnLocation;
                $visit->distance_to_location = $distanceToLocation;
                $visit->time_to_location = $timeToLocation;
                $visit->save();

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
