<?php

namespace Database\Seeders\Demo;

use App\Models\Branch;
use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        if (!env('SEED_SAMPLE_DATA', false)) {
            // no companies seeded by default; call this seeder manually if demo data required
            return;
        }

        // demo path: factories used to populate random companies/branches
        Company::factory(5)->create()->each(function ($company) {
            // create 1-3 branches per company
            $branches = Branch::factory(rand(2, 3))->make()->toArray();
            foreach ($branches as $b) {
                $company->branches()->create($b);
            }
        });
    }
}
