<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<int, string> */
    private array $roleSlugs = ['permitter', 'pic', 'system_admin', 'developer'];

    public function up(): void
    {
        DB::table('permissions')->insertOrIgnore([
            'slug' => 'fjp.assign_team',
            'name' => 'Tentukan tim FJP',
            'module' => 'fjp',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionId = DB::table('permissions')
            ->where('slug', 'fjp.assign_team')
            ->value('id');
        $roleIds = DB::table('roles')
            ->whereIn('slug', $this->roleSlugs)
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
            ->where('slug', 'fjp.assign_team')
            ->value('id');

        if ($permissionId === null) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('slug', $this->roleSlugs)
            ->pluck('id');

        DB::table('permission_role')
            ->where('permission_id', $permissionId)
            ->whereIn('role_id', $roleIds)
            ->delete();

        if (! DB::table('permission_role')->where('permission_id', $permissionId)->exists()) {
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
