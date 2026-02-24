<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\DiagnosisSeeder;
use Database\Seeders\InsuranceCompanySeeder;
use Database\Seeders\ReportMonthSeeder;
use Database\Seeders\TextBlockSeeder;
use Database\Seeders\VisitSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{

    public function run(): void
    {
        $this->call([
            CountrySeeder::class,
        ]);
    }

}
