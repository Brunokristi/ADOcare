<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProcedureCompanyPricesSeeder extends Seeder
{
    public function run()
    {
        $data = [];

        for ($procedureId = 2; $procedureId <= 31; $procedureId++) {
            for ($insuranceId = 1; $insuranceId <= 50; $insuranceId++) {
                $data[] = [
                    'procedure_id'          => $procedureId,
                    'insurance_company_id'  => $insuranceId,
                    'price'                 => 2.00,
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ];
            }
        }

        foreach (array_chunk($data, 1000) as $chunk) {
            DB::table('procedure_company_prices')->insert($chunk);
        }
    }
}
