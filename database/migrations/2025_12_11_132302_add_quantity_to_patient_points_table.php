<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('patient_points', function (Blueprint $table) {
            $table->smallInteger('quantity')->nullable()->after('reference_date');
        });
    }

    public function down()
    {
        Schema::table('patient_points', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }
};
