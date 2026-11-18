<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->insertOrIgnore([
            'slug' => 'fjp.status.update',
            'name' => 'Ubah status FJP',
            'module' => 'fjp',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionId = DB::table('permissions')
            ->where('slug', 'fjp.status.update')
            ->value('id');

        $roleIds = DB::table('roles')
            ->whereIn('slug', ['pic', 'permitter'])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('permission_role')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('slug', 'fjp.status.update')
            ->value('id');

        if ($permissionId === null) {
            return;
        }

        DB::table('permission_role')
            ->where('permission_id', $permissionId)
            ->whereIn('role_id', DB::table('roles')->whereIn('slug', ['pic', 'permitter'])->select('id'))
            ->delete();

    }
};
