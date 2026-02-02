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
            $branchId = Branch::inRandomOrder()->value('id') ?? null;
            $userId = null;
            if ($branchId) {
                $userId = User::whereHas('branches', function ($q) use ($branchId) {
                    $q->where('id', $branchId);
                })->inRandomOrder()->value('id');
            }

            $m->user_id = $userId;
            $m->branch_id = $branchId;
            $m->save();
        });
    }
}
