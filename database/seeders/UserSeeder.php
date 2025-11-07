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

        // Create 2 users per branch
        foreach ($branches as $branchId) {
            for ($i = 0; $i < 2; $i++) {
                $first = 'User'.substr((string) Str::random(6), 0, 4);
                $last = 'Demo';
                $login = strtolower($first).'.'.strtolower($last).rand(1, 99);
                $email = $login.'@example.test';

                $user = \App\Models\User::factory()->create([
                    'email' => $email,
                    'password' => Hash::make('password'),
                    'username' => $login,
                    'first_name' => $first,
                    'last_name' => $last,
                    'title' => null,
                    'code' => null,
                    'phone_number' => null,
                    'initials' => strtoupper(substr($first, 0, 1).substr($last, 0, 1)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // attach pivot user_branches (use direct insert to avoid needing Eloquent relation)
                DB::table('user_branches')->insert([
                    'user_id' => $user->id,
                    'branch_id' => $branchId,
                    'working_time' => 8.0,
                ]);
            }
        }
    }
}
