<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTextblocksPositions extends Migration
{
    public function up()
    {
        Schema::create('text_blocks', function (Blueprint $table) {
            $table->increments('id');
            $table->text('text')->nullable();
            $table->integer('position')->nullable();
            $table->timestamps();
        });

        Schema::create('position_colors', function (Blueprint $table) {
            $table->integer('position')->primary();
            $table->string('color')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('position_colors');
        Schema::dropIfExists('text_blocks');
    }
}
