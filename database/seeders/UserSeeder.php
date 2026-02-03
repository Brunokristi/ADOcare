<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $branches = DB::table('branches')->pluck('id');
        if ($branches->isEmpty()) {
            // no branches to attach users to
            return;
        }
        $usersCreated = 0;
        // Create 2 users per branch
        foreach ($branches as $branchId) {
            for ($i = 0; $i < 2; $i++) {
                $first = 'User' . substr((string) Str::random(6), 0, 4);
                $last = 'Demo';
                $login = 'user' . $usersCreated++;
                $email = $login . '@example.test';

                $code = $branchId . '-' . $i;

                // skip if a user with this code already exists (make seeder idempotent)
                if (\App\Models\User::where('code', $code)->exists()) {
                    continue;
                }

                $user = \App\Models\User::factory()->create([
                    'email' => $email,
                    'login' => $login,
                    'first_name' => $first,
                    'last_name' => $last,
                    'title' => null,
                    'code' => $code,
                    'phone_number' => null,
                    'initials' => strtoupper(substr($first, 0, 1) . substr($last, 0, 1)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // attach pivot user_branches (use direct insert to avoid needing Eloquent relation)
                DB::table('user_branches')->insert([
                    'user_id' => $user->id,
                    'branch_id' => $branchId,
                    'working_time' => 1.0,
                ]);

                // If this is an early user (id < 10), ensure they are assigned to at least two branches
                if ($user->id < 10) {
                    // find a different branch to attach within the same company as the original branch
                    $companyId = DB::table('branches')->where('id', $branchId)->value('company_id');

                    $otherBranchId = DB::table('branches')
                        ->where('id', '!=', $branchId)
                        ->when($companyId, function ($q) use ($companyId) {
                            return $q->where('company_id', $companyId);
                        })
                        ->inRandomOrder()
                        ->value('id');

                    if ($otherBranchId) {
                        $exists = DB::table('user_branches')
                            ->where('user_id', $user->id)
                            ->where('branch_id', $otherBranchId)
                            ->exists();

                        if (!$exists) {
                            DB::table('user_branches')->insert([
                                'user_id' => $user->id,
                                'branch_id' => $otherBranchId,
                                'working_time' => 1.0,
                            ]);
                        }
                    }
                }
            }
        }
    }
}
