<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        Company::factory(5)->create()->each(function ($company) {
            // create 1-3 branches per company
            $branches = Branch::factory(rand(1, 3))->make()->toArray();
            foreach ($branches as $b) {
                $company->branches()->create($b);
            }
        });
    }
}
