<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create immutable wrapper (needed for indexes + safe searching)
        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION public.immutable_unaccent(txt text)
RETURNS text
LANGUAGE sql
IMMUTABLE
PARALLEL SAFE
AS $$
  SELECT unaccent(txt);
$$;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS public.immutable_unaccent(text);');
    }
};
