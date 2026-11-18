<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<int, string> */
    private array $newPermissions = [
        'admin.system.view',
        'admin.logs.view',
    ];

    /** @var array<int, string> */
    private array $systemRoles = ['system_admin', 'developer'];

    public function up(): void
    {
        DB::table('permissions')->insertOrIgnore([
            [
                'slug' => 'admin.system.view',
                'name' => 'Lihat kesehatan sistem',
                'module' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'admin.logs.view',
                'name' => 'Lihat log aplikasi',
                'module' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', [...$this->newPermissions, 'fjp.status.update'])
            ->pluck('id');

        $roleIds = DB::table('roles')
            ->whereIn('slug', $this->systemRoles)
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $roleIds = DB::table('roles')
            ->whereIn('slug', $this->systemRoles)
            ->pluck('id');

        $fjpPermissionId = DB::table('permissions')
            ->where('slug', 'fjp.status.update')
            ->value('id');

        if ($fjpPermissionId !== null) {
            DB::table('permission_role')
                ->where('permission_id', $fjpPermissionId)
                ->whereIn('role_id', $roleIds)
                ->delete();
        }

        $newPermissionIds = DB::table('permissions')
            ->whereIn('slug', $this->newPermissions)
            ->pluck('id');

        DB::table('permission_role')
            ->whereIn('permission_id', $newPermissionIds)
            ->whereIn('role_id', $roleIds)
            ->delete();

        DB::table('permissions')
            ->whereIn('slug', $this->newPermissions)
            ->delete();
    }
};
