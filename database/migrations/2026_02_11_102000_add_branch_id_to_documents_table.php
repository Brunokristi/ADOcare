<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')
                  ->nullable()
                  ->after('user_id');

            $table->foreign('branch_id')
                  ->references('id')
                  ->on('branches')
                  ->onDelete('set null');

            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropIndex(['branch_id']);
            $table->dropColumn('branch_id');
        });
    }
};
