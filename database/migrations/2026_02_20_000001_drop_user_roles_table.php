<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropUserRolesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('user_roles')) {
            Schema::dropIfExists('user_roles');
        }
    }

    public function down()
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('role_id');
            $table->primary(['user_id', 'role_id']);
        });
    }
}
