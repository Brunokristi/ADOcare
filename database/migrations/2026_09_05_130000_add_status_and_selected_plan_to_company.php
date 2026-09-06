<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('company', function (Blueprint $table) {
            // Null means 'active' (existing companies keep working as-is); new registrations set 'onboarding' explicitly.
            if (!Schema::hasColumn('company', 'status')) {
                $table->string('status', 32)->nullable()->after('studiokristian_customer_token');
            }

            // Local hint of the plan the Company picked during onboarding - display only, never authoritative billing state.
            if (!Schema::hasColumn('company', 'selected_plan_price_id')) {
                $table->unsignedBigInteger('selected_plan_price_id')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('company', function (Blueprint $table) {
            if (Schema::hasColumn('company', 'selected_plan_price_id')) {
                $table->dropColumn('selected_plan_price_id');
            }

            if (Schema::hasColumn('company', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
