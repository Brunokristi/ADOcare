<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddScopeAndRoleIds extends Migration
{
    public function up()
    {
        // Add scope enum to roles table
        if (!Schema::hasColumn('roles', 'scope')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->enum('scope', ['branch', 'company', 'global'])->default('global')->after('position');
            });

            // Backfill sensible defaults from existing positions
            DB::table('roles')->where('position', 'nurse')->update(['scope' => 'branch']);
            DB::table('roles')->where('position', 'manager')->update(['scope' => 'company']);
        }

        // Add user.role_id
        if (!Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedInteger('role_id')->nullable()->after('email');
                $table->foreign('role_id')->references('id')->on('roles')->nullOnDelete();
            });

            // Backfill from user_roles (take the smallest role_id if multiple)
            $rows = DB::select('SELECT user_id, MIN(role_id) AS role_id FROM user_roles GROUP BY user_id');
            foreach ($rows as $r) {
                DB::table('users')->where('id', $r->user_id)->update(['role_id' => $r->role_id]);
            }
        }

        // Add role_id to user_branches pivot for branch-scoped roles
        if (Schema::hasTable('user_branches') && !Schema::hasColumn('user_branches', 'role_id')) {
            Schema::table('user_branches', function (Blueprint $table) {
                $table->unsignedInteger('role_id')->nullable()->after('working_time');
                $table->foreign('role_id')->references('id')->on('roles')->nullOnDelete();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('user_branches', 'role_id')) {
            Schema::table('user_branches', function (Blueprint $table) {
                $table->dropForeign(['role_id']);
                $table->dropColumn('role_id');
            });
        }

        if (Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['role_id']);
                $table->dropColumn('role_id');
            });
        }

        if (Schema::hasColumn('roles', 'scope')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropColumn('scope');
            });
        }
    }
}
