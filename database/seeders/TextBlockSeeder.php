<?php

namespace Database\Seeders;

use App\Models\TextBlock;
use Illuminate\Database\Seeder;

class TextBlockSeeder extends Seeder
{
    public function run(): void
    {
        TextBlock::factory(20)->create();
    }
}
