<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('patient_points', function (Blueprint $table) {
            $table->index(['branch_id', 'date', 'patient_id', 'user_id'], 'pp_branch_date_patient_user_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_points', function (Blueprint $table) {
            $table->dropIndex('pp_branch_date_patient_user_idx');
        });
    }
};
