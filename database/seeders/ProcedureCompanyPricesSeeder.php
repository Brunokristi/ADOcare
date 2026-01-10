<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProcedureCompanyPricesSeeder extends Seeder
{
    public function run()
    {
        $data = [];

        $procedures = DB::table('procedures')->pluck('id');
        $companies = DB::table('insurance_companies')->pluck('id');

        foreach ($procedures as $procedureId) {
            foreach ($companies as $companyId) {
                $data[] = [
                    'procedure_id' => $procedureId,
                    'insurance_company_id' => $companyId,
                    'price' => rand(50, 500), // Random price between 50 and 500
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        DB::table('procedure_company_prices')->insert($data);
    }
}
