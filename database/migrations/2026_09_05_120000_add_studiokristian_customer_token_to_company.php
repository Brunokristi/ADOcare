<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('company', function (Blueprint $table) {
            if (!Schema::hasColumn('company', 'studiokristian_customer_token')) {
                // Encrypted StudioKristian customer credential (X-Billing-Customer-Token), provisioned via onboarding.
                $table->text('studiokristian_customer_token')->nullable()->after('subscription_notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('company', function (Blueprint $table) {
            if (Schema::hasColumn('company', 'studiokristian_customer_token')) {
                $table->dropColumn('studiokristian_customer_token');
            }
        });
    }
};
