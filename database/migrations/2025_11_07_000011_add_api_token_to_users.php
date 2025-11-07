<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddApiTokenToUsers extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // store sha256 hex (64 chars)
            $table->string('api_token', 64)->nullable()->unique()->after('remember_token');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('api_token');
        });
    }
}
