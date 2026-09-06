<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // A Company mid-onboarding legitimately has no billing state yet - null now means
        // "not determined" instead of misleadingly reusing the 'active' default.
        Schema::table('company', function (Blueprint $table) {
            $table->string('subscription_status', 32)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->string('subscription_status', 32)->default('active')->change();
        });
    }
};
