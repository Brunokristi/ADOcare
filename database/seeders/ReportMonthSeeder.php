<?php

namespace Database\Seeders;

use App\Models\ReportMonth;
use App\Models\User;
use App\Models\Branch;
use Illuminate\Database\Seeder;

class ReportMonthSeeder extends Seeder
{
    public function run(): void
    {
        $months = ReportMonth::factory(12)->create()->each(function ($m) {
            $m->user_id = User::inRandomOrder()->value('id') ?? null;
            $m->branch_id = Branch::inRandomOrder()->value('id') ?? null;
            $m->save();
        });
    }
}
