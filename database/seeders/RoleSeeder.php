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

        // Assign roles to users:
        // - Give the admin user (code = 'admin') the 'manager' role
        $admin = User::where('code', 'admin')->first();
        if ($admin) {
            $roles['manager']->users()->syncWithoutDetaching([$admin->id]);
        }

        // Attach random roles to some users (idempotent)
        $users = User::where('code', '!=', 'admin')->inRandomOrder()->take(40)->get();
        foreach ($users as $u) {
            // First 10 should have both
            if ($u->id < 10) {
                $roles['manager']->users()->syncWithoutDetaching([$u->id]);
                $roles['nurse']->users()->syncWithoutDetaching([$u->id]);
                continue;
            }
            $pick = $roles[array_rand($roles)];
            $pick->users()->syncWithoutDetaching([$u->id]);
        }

        // Ensure every user has at least one role: if a user has no role, assign 'nurse' by default.
        $default = $roles['nurse'];
        \App\Models\User::chunk(100, function ($chunk) use ($default) {
            foreach ($chunk as $user) {
                if (!$user->roles()->exists()) {
                    $default->users()->syncWithoutDetaching([$user->id]);
                }
            }
        });
    }
}
