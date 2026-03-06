<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropUnique('docs_unique_km_batch');

        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropUnique('docs_unique_km_batch_insurance');

            $table->unique(
                ['type', 'subtype', 'user_id', 'branch_id', 'period'],
                'docs_unique_km_batch'
            );
        });
    }
};
