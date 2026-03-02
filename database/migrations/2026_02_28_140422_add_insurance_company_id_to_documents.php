<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->unsignedBigInteger('insurance_company_id')->nullable()->index();
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->unique(
                ['type', 'subtype', 'user_id', 'branch_id', 'period', 'insurance_company_id'],
                'docs_unique_km_batch_insurance'
            );
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropUnique('docs_unique_km_batch_insurance');
            $table->dropIndex(['insurance_company_id']);
            $table->dropColumn('insurance_company_id');
        });
    }
};