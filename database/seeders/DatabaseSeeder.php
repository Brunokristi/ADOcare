<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed admin user
        User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'code' => 'admin',
            'email' => 'admin@example.sk',
        ]);

        $this->call(CompanySeeder::class);
        $this->call(DoctorSeeder::class);
        $this->call(PatientSeeder::class);
        $this->call(UserSeeder::class);
        $this->call(CarSeeder::class);

    }
}
