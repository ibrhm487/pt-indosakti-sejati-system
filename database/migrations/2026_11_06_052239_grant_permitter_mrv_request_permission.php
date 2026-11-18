<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $roleId = DB::table('roles')->where('slug', 'permitter')->value('id');
        $permissionId = DB::table('permissions')->where('slug', 'warehouse.request')->value('id');

        if ($roleId !== null && $permissionId !== null) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $roleId = DB::table('roles')->where('slug', 'permitter')->value('id');
        $permissionId = DB::table('permissions')->where('slug', 'warehouse.request')->value('id');

        if ($roleId !== null && $permissionId !== null) {
            DB::table('permission_role')
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionId)
                ->delete();
        }
    }
};
