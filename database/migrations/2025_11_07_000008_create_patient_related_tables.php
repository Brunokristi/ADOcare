<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePatientRelatedTables extends Migration
{
    public function up()
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->increments('id');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('title')->nullable();
            $table->string('personal_number')->nullable();
            $table->enum('sex', ['M', 'F'])->nullable();
            $table->string('contact')->nullable();
            $table->unsignedInteger('doctor_id')->nullable();
            $table->unsignedInteger('insurance_company_id')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('zip')->nullable();
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();
            $table->date('reference_date')->nullable()->after('doctor_id');
            $table->timestamps();
            $table->foreign('doctor_id')->references('id')->on('doctors')->onDelete('set null');
            $table->foreign('insurance_company_id')->references('id')->on('insurance_companies')->onDelete('set null');
        });

        Schema::create('patient_branch_users', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('patient_id');
            $table->unsignedInteger('branch_id');
            $table->unsignedInteger('user_id');
            $table->timestamps();
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('patient_points', function (Blueprint $table) {
            $table->increments('id');
            $table->date('date')->nullable();
            $table->string('patient_personal_number')->nullable();
            $table->string('patient_name')->nullable();
            $table->unsignedInteger('patient_id')->nullable();
            $table->string('diagnosis_code')->nullable();
            $table->unsignedInteger('diagnosis_id')->nullable();
            $table->string('procedure_code')->nullable();
            $table->unsignedInteger('procedure_id')->nullable();
            $table->string('doctor_pzs')->nullable();
            $table->string('doctor_zpr')->nullable();
            $table->unsignedInteger('doctor_id')->nullable();
            $table->date('reference_date')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('branch_id')->nullable();
            $table->smallInteger('quantity')->nullable()->after('reference_date');
            $table->timestamps();
            $table->foreign('diagnosis_id')->references('id')->on('diagnoses')->onDelete('set null');
            $table->foreign('procedure_id')->references('id')->on('procedures')->onDelete('set null');
            $table->foreign('doctor_id')->references('id')->on('doctors')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('patient_points');
        Schema::dropIfExists('patient_branch_users');
        Schema::dropIfExists('patients');
    }
}
