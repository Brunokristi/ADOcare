<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserMacrosDekurz extends Migration
{
    public function up()
    {
        Schema::create('user_macros', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->text('text')->nullable();
            $table->string('abbreviation')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('dekurz', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('storage_path')->nullable();
            $table->string('type')->nullable();
            $table->unsignedInteger('month_id')->nullable();
            $table->unsignedInteger('patient_id')->nullable();
            $table->timestamps();
            $table->foreign('month_id')->references('id')->on('report_months')->onDelete('set null');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('dekurz');
        Schema::dropIfExists('user_macros');
    }
}
