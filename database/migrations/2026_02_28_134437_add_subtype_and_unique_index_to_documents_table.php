<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('subtype', 10)->nullable()->after('type')->index();
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->unique(['type', 'subtype', 'user_id', 'branch_id', 'period'], 'docs_unique_km_batch');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropUnique('docs_unique_km_batch');
            $table->dropIndex(['subtype']);
            $table->dropColumn('subtype');
        });
    }
};