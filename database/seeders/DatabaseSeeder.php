<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\DiagnosisSeeder;
use Database\Seeders\InsuranceCompanySeeder;
use Database\Seeders\ReportMonthSeeder;
use Database\Seeders\TextBlockSeeder;
use Database\Seeders\VisitSeeder;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed admin user (idempotent)
        \App\Models\User::updateOrCreate(
            ['code' => 'admin'],
            [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'email' => 'admin@example.sk',
                'login' => 'admin',
                'pin' => '0000',
                'initials' => 'AU',
            ]
        );

        // companies -> branches
        $this->call(CompanySeeder::class);

        // users need branches to exist
        $this->call(UserSeeder::class);

        // roles depend on users; seed roles and assign to users
        $this->call(RoleSeeder::class);

        // doctors, patients and cars
        $this->call(DoctorSeeder::class);
        $this->call(PatientSeeder::class);
        $this->call(CarSeeder::class);

        // insurance companies and diagnoses
        $this->call(InsuranceCompanySeeder::class);
        $this->call(DiagnosisSeeder::class);

        // reusable text blocks, report months and visits
        $this->call(TextBlockSeeder::class);
        $this->call(ReportMonthSeeder::class);
        $this->call(VisitSeeder::class);

    }
}
