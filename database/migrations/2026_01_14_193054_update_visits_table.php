<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            // --- Add missing identifiers ---
            if (!Schema::hasColumn('visits', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('patient_id')->index();
            }

            if (!Schema::hasColumn('visits', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('user_id')->index();
            }

            // --- Add timeline fields ---
            if (!Schema::hasColumn('visits', 'terrain_time')) {
                $table->timestamp('terrain_time', 0)->nullable()->after('branch_id');
            }

            if (!Schema::hasColumn('visits', 'administrative_time')) {
                $table->timestamp('administrative_time', 0)->nullable()->after('terrain_time');
            }

            if (!Schema::hasColumn('visits', 'time_on_location')) {
                $table->unsignedInteger('time_on_location')->nullable()->after('administrative_time')
                    ->comment('Seconds spent at patient location');
            }

            if (!Schema::hasColumn('visits', 'distance_to_location')) {
                $table->unsignedInteger('distance_to_location')->nullable()->after('time_on_location')
                    ->comment('Meters from previous location to this patient');
            }

            if (!Schema::hasColumn('visits', 'time_to_location')) {
                $table->unsignedInteger('time_to_location')->nullable()->after('distance_to_location')
                    ->comment('Seconds from previous location to this patient');
            }
        });

        // --- Drop old / unused columns + rename if you want ---
        Schema::table('visits', function (Blueprint $table) {
            // drop month_id (FK + column) if present
            if (Schema::hasColumn('visits', 'month_id')) {
                try {
                    $table->dropForeign(['month_id']);
                } catch (\Throwable $e) {
                    // FK name might differ; ignore to avoid migration fail
                }
                $table->dropColumn('month_id');
            }

            // drop old columns (if you don't need them anymore)
            if (Schema::hasColumn('visits', 'examination')) {
                $table->dropColumn('examination');
            }

            if (Schema::hasColumn('visits', 'statement')) {
                $table->dropColumn('statement');
            }
        });

        // --- Add foreign keys (separate step is safer) ---
        Schema::table('visits', function (Blueprint $table) {
            // user FK
            try {
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            } catch (\Throwable $e) {
                // ignore if already exists
            }

            // branch FK
            try {
                $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            } catch (\Throwable $e) {
                // ignore if already exists
            }
        });

        // --- Uniqueness: one row per patient per day per user+branch ---
        Schema::table('visits', function (Blueprint $table) {
            try {
                $table->unique(['date', 'patient_id', 'user_id', 'branch_id'], 'visits_unique_day_patient_user_branch');
            } catch (\Throwable $e) {
                // ignore if already exists
            }
        });
    }

    public function down(): void
    {
        // Reverse unique + drop new columns and restore old ones.
        Schema::table('visits', function (Blueprint $table) {
            try {
                $table->dropUnique('visits_unique_day_patient_user_branch');
            } catch (\Throwable $e) {}

            // drop new FKs first (names can vary)
            try { $table->dropForeign(['user_id']); } catch (\Throwable $e) {}
            try { $table->dropForeign(['branch_id']); } catch (\Throwable $e) {}

            foreach ([
                'user_id',
                'branch_id',
                'terrain_time',
                'administrative_time',
                'time_on_location',
                'distance_to_location',
                'time_to_location',
            ] as $col) {
                if (Schema::hasColumn('visits', $col)) {
                    $table->dropColumn($col);
                }
            }

            // restore old columns (best-effort)
            if (!Schema::hasColumn('visits', 'examination')) {
                $table->timestamp('examination', 0)->nullable();
            }
            if (!Schema::hasColumn('visits', 'statement')) {
                $table->timestamp('statement', 0)->nullable();
            }
            if (!Schema::hasColumn('visits', 'month_id')) {
                $table->unsignedBigInteger('month_id')->nullable();
            }
        });

        // restore month_id FK (optional; only if report_months exists)
        Schema::table('visits', function (Blueprint $table) {
            try {
                $table->foreign('month_id')->references('id')->on('report_months')->onDelete('set null');
            } catch (\Throwable $e) {}
        });
    }
};
