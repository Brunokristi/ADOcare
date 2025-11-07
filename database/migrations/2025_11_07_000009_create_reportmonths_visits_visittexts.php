<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReportMonthsVisitsVisitTexts extends Migration
{
    public function up()
    {
        Schema::create('report_months', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('month')->nullable();
            $table->integer('year')->nullable();
            $table->time('examination_start')->nullable();
            $table->time('examination_end')->nullable();
            $table->time('statement_start')->nullable();
            $table->time('statement_end')->nullable();
            $table->date('first_day')->nullable();
            $table->date('last_day')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('branch_id')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
        });

        Schema::create('visits', function (Blueprint $table) {
            $table->increments('id');
            $table->date('date')->nullable();
            $table->dateTime('examination')->nullable();
            $table->dateTime('statement')->nullable();
            $table->unsignedInteger('patient_id')->nullable();
            $table->unsignedInteger('month_id')->nullable();
            $table->timestamps();
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('month_id')->references('id')->on('report_months')->onDelete('set null');
        });

        Schema::create('visit_texts', function (Blueprint $table) {
            $table->unsignedInteger('visit_id');
            $table->unsignedInteger('text_id');
            $table->primary(['visit_id', 'text_id']);
            $table->foreign('visit_id')->references('id')->on('visits')->onDelete('cascade');
            $table->foreign('text_id')->references('id')->on('text_blocks')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('visit_texts');
        Schema::dropIfExists('visits');
        Schema::dropIfExists('report_months');
    }
}
