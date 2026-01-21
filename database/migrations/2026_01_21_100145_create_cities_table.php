<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('zip', 10)->nullable();

            $table->timestamps();

            $table->index('name');
            $table->index('zip');
            $table->unique(['name', 'zip']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
