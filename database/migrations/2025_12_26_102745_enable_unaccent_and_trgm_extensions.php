<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Enable extensions (Postgres only; safe to run multiple times)
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent;');
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm;');
        }
    }

    public function down(): void
    {
        // Optional: usually you DO NOT want to drop these in production
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP EXTENSION IF EXISTS pg_trgm;');
            DB::statement('DROP EXTENSION IF EXISTS unaccent;');
        }
    }
};
