<?php

namespace Database\Seeders;

use App\Models\Macro;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

class MacroSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        foreach ($users as $user) {
            Macro::factory()->count(20)->create([
                'user_id' => $user->id,
            ]);
        }
    }
}
