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
            // randomly pick 0-1 roles to attach
            if (random_int(0, 1) === 1) {
                $pick = $roles[array_rand($roles)];
                $pick->users()->syncWithoutDetaching([$u->id]);
            }
        }
    }
}
