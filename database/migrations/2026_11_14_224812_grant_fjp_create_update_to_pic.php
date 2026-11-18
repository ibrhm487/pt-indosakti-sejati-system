<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            ['fjp.create', 'Buat FJP'],
            ['fjp.update', 'Ubah FJP'],
        ] as [$slug, $name]) {
            DB::table('permissions')->insertOrIgnore([
                'slug' => $slug,
                'name' => $name,
                'module' => 'fjp',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $permissionId = DB::table('permissions')->where('slug', $slug)->value('id');
            $picRoleId = DB::table('roles')->where('slug', 'pic')->value('id');

            if ($permissionId !== null && $picRoleId !== null) {
                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $picRoleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('slug', ['fjp.create', 'fjp.update'])
            ->pluck('id');
        $picRoleId = DB::table('roles')->where('slug', 'pic')->value('id');

        if ($picRoleId === null || $permissionIds->isEmpty()) {
            return;
        }

        DB::table('permission_role')
            ->where('role_id', $picRoleId)
            ->whereIn('permission_id', $permissionIds)
            ->delete();
    }
};
