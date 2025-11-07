<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBranchesTable extends Migration
{
    public function up()
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id')->nullable();
            $table->string('code')->nullable();
            $table->string('identificator')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('psc')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('company')->onDelete('set null');
        });

        // updaste user table to add last_branch foreign key
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('last_branch')->nullable();
            $table->foreign('last_branch')->references('id')->on('branches')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('branches');
    }
}
