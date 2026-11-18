<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->insertOrIgnore([
            'slug' => 'fjp.assign_team_members',
            'name' => 'Membagikan anggota tim FJP',
            'module' => 'fjp',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionId = DB::table('permissions')
            ->where('slug', 'fjp.assign_team_members')
            ->value('id');
        $roleId = DB::table('roles')
            ->where('slug', 'pic')
            ->value('id');

        if ($permissionId === null || $roleId === null) {
            return;
        }

        DB::table('permission_role')->insertOrIgnore([
            'permission_id' => $permissionId,
            'role_id' => $roleId,
        ]);
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('slug', 'fjp.assign_team_members')
            ->value('id');

        if ($permissionId === null) {
            return;
        }

        DB::table('permission_role')
            ->where('permission_id', $permissionId)
            ->delete();

        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
