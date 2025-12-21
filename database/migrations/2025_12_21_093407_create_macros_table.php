<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('macros', function (Blueprint $table) {
            $table->id();
            $table->text('name');
            $table->text('abbreviation');
            $table->text('text');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            

            // Optional: prevent duplicate abbreviations per user
            $table->unique(['user_id', 'abbreviation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('macros');
    }
};
