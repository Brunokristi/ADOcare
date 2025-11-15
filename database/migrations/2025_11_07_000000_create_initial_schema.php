<?php

// Deprecated monolithic migration: replaced by split, ordered migrations in this directory.
// Keeping this file as a no-op placeholder so existing VCS history remains clear.

use Illuminate\Database\Migrations\Migration;

class CreateInitialSchema extends Migration
{
    public function up()
    {
        // intentionally left blank — use the split migrations instead
    }

    public function down()
    {
        // intentionally left blank
    }
}
