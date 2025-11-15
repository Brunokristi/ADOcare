<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDomainReferenceTables extends Migration
{
    public function up()
    {
        Schema::create('doctors', function (Blueprint $table) {
            $table->increments('id');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('title')->nullable();
            $table->string('zpr')->nullable();
            $table->string('pzs')->nullable();
            $table->timestamps();
        });

        Schema::create('diagnoses', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code')->unique()->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('insurance_companies', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('psc')->nullable();
            $table->string('ico')->nullable();
            $table->string('dic')->nullable();
            $table->string('ic_dph')->nullable();
            $table->string('register')->nullable();
            $table->string('code')->nullable();
            $table->string('branch_code')->nullable();
            $table->timestamps();
        });

        Schema::create('procedures', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('procedures');
        Schema::dropIfExists('insurance_companies');
        Schema::dropIfExists('diagnoses');
        Schema::dropIfExists('doctors');
    }
}
