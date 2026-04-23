<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('company_subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->date('received_at');
            $table->decimal('amount', 10, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('company')->cascadeOnDelete();
            $table->index(['company_id', 'received_at']);
        });

        Schema::create('company_subscription_paid_months', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('company')->cascadeOnDelete();
            $table->unique(['company_id', 'year', 'month'], 'company_subscription_paid_months_unique');
            $table->index(['company_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_subscription_paid_months');
        Schema::dropIfExists('company_subscription_payments');
    }
};