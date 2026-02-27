<?php

namespace Database\Seeders\Demo;

use App\Models\TextBlock;
use Illuminate\Database\Seeder;

class TextBlockSeeder extends Seeder
{
    public function run(): void
    {
        if (!env('SEED_SAMPLE_DATA', false)) {
            return;
        }

        TextBlock::factory(20)->create();
    }
}
