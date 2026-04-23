<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Legacy schema enforced uniqueness only on (procedure_id, insurance_company_id).
        // Drop it so we can materialize per-company rows first.
        DB::statement('ALTER TABLE procedure_company_prices DROP CONSTRAINT IF EXISTS uix_procedure_company_prices_proc_ins');
        DB::statement('DROP INDEX IF EXISTS uix_procedure_company_prices_proc_ins');

        Schema::table('procedure_company_prices', function (Blueprint $table) {
            if (!Schema::hasColumn('procedure_company_prices', 'company_id')) {
                $table->unsignedInteger('company_id')->nullable()->after('insurance_company_id');
                $table->foreign('company_id')->references('id')->on('company')->onDelete('cascade');
            }
        });

        $companyIds = DB::table('company')->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (!empty($companyIds)) {
            $seedRows = DB::table('procedure_company_prices')
                ->whereNull('company_id')
                ->select(['id', 'procedure_id', 'insurance_company_id', 'price', 'created_at', 'updated_at'])
                ->get();

            foreach ($seedRows as $row) {
                $inserts = [];
                foreach ($companyIds as $companyId) {
                    $inserts[] = [
                        'procedure_id' => $row->procedure_id,
                        'insurance_company_id' => $row->insurance_company_id,
                        'company_id' => $companyId,
                        'price' => $row->price,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ];
                }

                if (!empty($inserts)) {
                    DB::table('procedure_company_prices')->insert($inserts);
                }
            }

            DB::table('procedure_company_prices')->whereNull('company_id')->delete();
        }

        $duplicates = DB::table('procedure_company_prices')
            ->select([
                'procedure_id',
                'insurance_company_id',
                'company_id',
                DB::raw('MAX(id) as keep_id'),
                DB::raw('COUNT(*) as rows_count'),
            ])
            ->groupBy('procedure_id', 'insurance_company_id', 'company_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            DB::table('procedure_company_prices')
                ->where('procedure_id', $dup->procedure_id)
                ->where('insurance_company_id', $dup->insurance_company_id)
                ->where('company_id', $dup->company_id)
                ->where('id', '!=', $dup->keep_id)
                ->delete();
        }

        Schema::table('procedure_company_prices', function (Blueprint $table) {
            $table->unique(
                ['procedure_id', 'insurance_company_id', 'company_id'],
                'pcp_procedure_insurance_company_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('procedure_company_prices', function (Blueprint $table) {
            $table->dropUnique('pcp_procedure_insurance_company_unique');

            if (Schema::hasColumn('procedure_company_prices', 'company_id')) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            }
        });
    }
};
