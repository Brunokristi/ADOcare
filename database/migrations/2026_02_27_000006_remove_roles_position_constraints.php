<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class RemoveRolesPositionConstraints extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('roles')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            // drop the check constraint entirely; no further DB-level enforcement
            Schema::disableForeignKeyConstraints();
            \DB::statement('ALTER TABLE roles DROP CONSTRAINT IF EXISTS roles_position_check');
            Schema::enableForeignKeyConstraints();
        } elseif ($driver === 'mysql') {
            // convert enum type to plain varchar so future values are unrestricted
            \DB::statement("ALTER TABLE `roles` MODIFY `position` VARCHAR(50) NULL");
        }
        // sqlite uses TEXT and had no constraints.
    }

    public function down()
    {
        if (!Schema::hasTable('roles')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            // re-add the constraint with the last known set of values
            \DB::statement("ALTER TABLE roles ADD CONSTRAINT roles_position_check CHECK (position IN ('manager','nurse','superadmin'))");
        } elseif ($driver === 'mysql') {
            \DB::statement("ALTER TABLE `roles` MODIFY `position` ENUM('manager','nurse','superadmin') NULL");
        }
    }
}
