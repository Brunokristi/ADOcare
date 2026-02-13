<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlansSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'company_id' => 1,
                'name' => 'Dekubit',
                'text' => 'návšteva v pracovnom čase, návšteva mimo pracovného času, ošetrovateľská starostlivosť zameraná na prevenciu dekubitov, ošetrenie dekubitu nad 5cm, priebežné hodnotenie KOS',
                'sort_order' => 1,
            ],
            [
                'company_id' => 1,
                'name' => 'Vred',
                'text' => 'návšteva v pracovnom čase, návšteva mimo pracovného času, aplikácia neinjekčnej liečby, preväz rany nad 5cm, priebežné hodnotenie KOS.',
                'sort_order' => 2,
            ],
            [
                'company_id' => 1,
                'name' => 'Odber',
                'text' => 'návšteva v pracovnom čase, odber krvi venepunkciou, doprava biologického materiálu',
                'sort_order' => 3,
            ],
            [
                'company_id' => 1,
                'name' => 'Injekcia',
                'text' => 'návšteva v pracovnom čase, aplikácia liečiva intramuskulárne.',
                'sort_order' => 4,
            ],
            [
                'company_id' => 1,
                'name' => 'Infúzia',
                'text' => 'návšteva v pracovnom čase, príprava a podávanie infúzie',
                'sort_order' => 6,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::create($plan);
        }
    }
}
