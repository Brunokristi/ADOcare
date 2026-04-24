<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price_monthly', 10, 2)->nullable();
            $table->unsignedInteger('users_limit')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('name');
        });

        Schema::table('company', function (Blueprint $table) {
            if (!Schema::hasColumn('company', 'subscription_tier_id')) {
                $table->unsignedBigInteger('subscription_tier_id')->nullable()->after('visit_locations');
                $table->foreign('subscription_tier_id')->references('id')->on('subscription_tiers')->nullOnDelete();
            }

            if (!Schema::hasColumn('company', 'subscription_price_monthly')) {
                $table->decimal('subscription_price_monthly', 10, 2)->nullable()->after('subscription_tier_id');
            }

            if (!Schema::hasColumn('company', 'subscription_users_limit_override')) {
                $table->unsignedInteger('subscription_users_limit_override')->nullable()->after('subscription_price_monthly');
            }

            if (!Schema::hasColumn('company', 'subscription_status')) {
                $table->string('subscription_status', 32)->default('active')->after('subscription_users_limit_override');
            }

            if (!Schema::hasColumn('company', 'subscription_started_at')) {
                $table->date('subscription_started_at')->nullable()->after('subscription_status');
            }

            if (!Schema::hasColumn('company', 'subscription_ends_at')) {
                $table->date('subscription_ends_at')->nullable()->after('subscription_started_at');
            }

            if (!Schema::hasColumn('company', 'subscription_notes')) {
                $table->text('subscription_notes')->nullable()->after('subscription_ends_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('company', function (Blueprint $table) {
            if (Schema::hasColumn('company', 'subscription_tier_id')) {
                $table->dropForeign(['subscription_tier_id']);
            }

            $columns = [
                'subscription_tier_id',
                'subscription_price_monthly',
                'subscription_users_limit_override',
                'subscription_status',
                'subscription_started_at',
                'subscription_ends_at',
                'subscription_notes',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('company', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('subscription_tiers');
    }
};
