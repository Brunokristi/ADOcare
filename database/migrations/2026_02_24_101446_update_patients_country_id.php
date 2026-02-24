<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        DB::table('patients')->update(['country_id' => 207]);
    }

    public function down()
    {
        DB::table('patients')->update(['country_id' => null]);
    }
};
