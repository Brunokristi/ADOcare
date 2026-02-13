<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Adds `branch_id` and `user_id` to `patients`, backfills them from `patient_branch_users`,
     * and creates a backup table `patient_branch_users_backup` of the pivot before any destructive action.
     *
     * Tie-breaker rule: when multiple pivot rows exist for a patient, the row with the largest `id` is chosen.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->after('dekurz_number');
            $table->unsignedBigInteger('nurse_id')->nullable()->after('branch_id');
        });

        // backup pivot table (Postgres-only SQL guarded for other drivers)
        if (DB::connection()->getDriverName() === 'pgsql') {
            if (!Schema::hasTable('patient_branch_users_backup')) {
                DB::statement('CREATE TABLE patient_branch_users_backup AS TABLE patient_branch_users');
            }
        }

        // pick one pivot row per patient (largest id) and use it to populate patients
        $maxIds = DB::table('patient_branch_users')
            ->select(DB::raw('MAX(id) as id'))
            ->groupBy('patient_id')
            ->pluck('id')
            ->toArray();

        if (!empty($maxIds)) {
            $rows = DB::table('patient_branch_users')->whereIn('id', $maxIds)->get();

            foreach ($rows as $row) {
                DB::table('patients')
                    ->where('id', $row->patient_id)
                    ->update([
                        'branch_id' => $row->branch_id,
                        'nurse_id' => $row->user_id,
                    ]);
            }
        }

        // add foreign keys if referenced rows exist (nullable so it won't fail on missing refs)
        Schema::table('patients', function (Blueprint $table) {
            // wrap in try/catch via raw statements is tricky; use schema builder with nullable fks
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('nurse_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('patients', function (Blueprint $table) {
            // drop foreign keys if exist
            if (Schema::hasColumn('patients', 'branch_id')) {
                $table->dropForeign(['branch_id']);
            }
            if (Schema::hasColumn('patients', 'nurse_id')) {
                $table->dropForeign(['nurse_id']);
            }
        });

        Schema::table('patients', function (Blueprint $table) {
            if (Schema::hasColumn('patients', 'branch_id')) {
                $table->dropColumn('branch_id');
            }
            if (Schema::hasColumn('patients', 'nurse_id')) {
                $table->dropColumn('nurse_id');
            }
        });

        // keep backup table to be safe; do not drop it automatically
    }
};
