<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure defined roles exist (idempotent)
        $positions = ['manager', 'nurse'];
        $roles = [];
        foreach ($positions as $pos) {
            $roles[$pos] = Role::firstOrCreate(['position' => $pos]);
        }

        // Assign global roles by populating the user's role_id.
        $admin = User::where('code', 'admin')->first();
        if ($admin) {
            $admin->role_id = $roles['manager']->id;
            $admin->save();
        }

        // Attach random roles to some users (idempotent)
        $users = User::where('code', '!=', 'admin')->inRandomOrder()->take(40)->get();
        foreach ($users as $u) {
            // First 10 should be given manager role by default
            if ($u->id < 10) {
                $u->role_id = $roles['manager']->id;
                $u->save();
                continue;
            }
            $pick = $roles[array_rand($roles)];
            $u->role_id = $pick->id;
            $u->save();
        }

        // Ensure every user has at least one role: if a user has no role, assign 'nurse' by default.
        $default = $roles['nurse'];
        \App\Models\User::chunk(100, function ($chunk) use ($default) {
            foreach ($chunk as $user) {
                if (!$user->role_id) {
                    $user->role_id = $default->id;
                    $user->save();
                }
            }
        });
    }
}
