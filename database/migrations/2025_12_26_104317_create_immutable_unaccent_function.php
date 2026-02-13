<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
                // Create immutable wrapper (needed for indexes + safe searching) - only for PG
                if (DB::connection()->getDriverName() === 'pgsql') {
                        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION public.immutable_unaccent(txt text)
RETURNS text
LANGUAGE sql
IMMUTABLE
PARALLEL SAFE
AS $$
    SELECT unaccent(txt);
$$;
SQL
                        );
                }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS public.immutable_unaccent(text);');
        }
    }
};
