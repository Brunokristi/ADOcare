<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('branch_doctor', 'branch_favourite_doctors');
    }

    public function down(): void
    {
        Schema::rename('branch_favourite_doctors', 'branch_doctor');
    }
};
