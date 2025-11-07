<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProcPricesCarsDistances extends Migration
{
    public function up()
    {
        Schema::create('procedure_company_prices', function (Blueprint $table) {
            $table->increments('id');
            $table->decimal('price', 10, 2)->nullable();
            $table->unsignedInteger('procedure_id')->nullable();
            $table->unsignedInteger('insurance_company_id')->nullable();
            $table->timestamps();
            $table->foreign('procedure_id')->references('id')->on('procedures')->onDelete('cascade');
            $table->foreign('insurance_company_id')->references('id')->on('insurance_companies')->onDelete('cascade');
        });

        Schema::create('cars', function (Blueprint $table) {
            $table->increments('id');
            $table->string('evc')->nullable();
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('company')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('distances', function (Blueprint $table) {
            $table->increments('id');
            $table->double('latitude_start')->nullable();
            $table->double('longitude_start')->nullable();
            $table->double('latitude_end')->nullable();
            $table->double('longitude_end')->nullable();
            $table->double('distance')->nullable();
            $table->integer('time')->nullable();
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('branch_id')->nullable();
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('company')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('distances');
        Schema::dropIfExists('cars');
        Schema::dropIfExists('procedure_company_prices');
    }
}
