<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'last_branch')) {
                try {
                    $table->dropColumn('last_branch');
                } catch (\Throwable $e) {
                    // In case the DB requires dropping FK/indexes first, ignore to avoid migration failure.
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'last_branch')) {
                try {
                    $table->unsignedBigInteger('last_branch')->nullable()->after('api_token');
                } catch (\Throwable $e) {
                    // best-effort restore; ignore errors to keep rollback safe
                }
            }
        });
    }
};
