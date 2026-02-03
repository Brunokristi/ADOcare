<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('id');
            $table->index('company_id');
        });

        // If the companies table exists, add FK constraint
        if (Schema::hasTable('companies')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('set null');
            });
        }

        // Backfill company_id for existing users from their branches where possible.
        // Use a correlated subquery to pick the company_id from the most recent pivot row.
        if (Schema::hasTable('user_branches') && Schema::hasTable('branches')) {
            DB::statement(<<<'SQL'
                UPDATE users
                SET company_id = (
                    SELECT b.company_id
                    FROM user_branches ub
                    JOIN branches b ON b.id = ub.branch_id
                    WHERE ub.user_id = users.id
                    ORDER BY ub.branch_id DESC
                    LIMIT 1
                )
                WHERE EXISTS (
                    SELECT 1 FROM user_branches ub2 WHERE ub2.user_id = users.id
                );
            SQL
            );
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropIndex(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
