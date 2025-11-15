<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCompaniesTable extends Migration
{
    public function up()
    {
        Schema::create('company', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('ico')->nullable();
            $table->string('dic')->nullable();
            $table->string('ic_dph')->nullable();
            $table->string('iban')->nullable();
            $table->string('bic')->nullable();
            $table->string('register')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('psc')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('company');
    }
}
