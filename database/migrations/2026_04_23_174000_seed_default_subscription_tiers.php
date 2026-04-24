<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();

        $defaults = [
            [
                'name' => 'Štart',
                'price_monthly' => 19.00,
                'users_limit' => 2,
                'description' => 'všetky funkcie bez obmedzení',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Rast',
                'price_monthly' => 39.00,
                'users_limit' => 10,
                'description' => 'všetky funkcie bez obmedzení',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Pro',
                'price_monthly' => null,
                'users_limit' => null,
                'description' => 'všetky funkcie bez obmedzení, neobmedzený počet používateľov, individuálna cena',
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($defaults as $tier) {
            $exists = DB::table('subscription_tiers')->where('name', $tier['name'])->exists();
            if ($exists) {
                continue;
            }

            DB::table('subscription_tiers')->insert([
                ...$tier,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('subscription_tiers')
            ->whereIn('name', ['Štart', 'Rast', 'Pro'])
            ->delete();
    }
};
