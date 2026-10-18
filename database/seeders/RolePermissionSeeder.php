<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use App\Support\RoleDefinitions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $permissionIds = [];
            foreach (Permissions::all() as $slug => $meta) {
                $permission = Permission::updateOrCreate(
                    ['slug' => $slug],
                    ['name' => $meta['name'], 'module' => $meta['module']],
                );
                $permissionIds[$slug] = $permission->id;
            }

            $allSlugs = array_keys($permissionIds);

            foreach (RoleDefinitions::all() as $slug => $definition) {
                $role = Role::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => $definition['name'],
                        'description' => $definition['description'],
                        'is_system' => $slug === 'system_admin',
                    ],
                );

                $slugs = $definition['permissions'] === '*'
                    ? $allSlugs
                    : $definition['permissions'];

                $role->permissions()->sync(
                    collect($slugs)->map(fn ($s) => $permissionIds[$s])->all()
                );
            }
        });

        $this->seedUsers();
    }

    private function seedUsers(): void
    {
        $password = env('SEED_PASSWORD', 'password');

        $users = [
            ['Admin Sistem', 'admin@indosakti.com', 'system_admin'],
            ['Permitter Satu', 'permitter@indosakti.com', 'permitter'],
            ['PIC Event', 'pic@indosakti.com', 'pic'],
            ['Tim Lapangan', 'team@indosakti.com', 'team'],
            ['Petugas Gudang', 'warehouse@indosakti.com', 'warehouse'],
            ['Petugas Kitchen', 'kitchen@indosakti.com', 'kitchen'],
            ['Admin Event', 'admin.event@indosakti.com', 'admin_event'],
        ];

        foreach ($users as [$name, $email, $roleSlug]) {
            $role = Role::where('slug', $roleSlug)->first();

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make($password),
                    'is_active' => true,
                    'active_role_id' => $role?->id,
                ],
            );

            if ($role) {
                $user->roles()->syncWithoutDetaching(collect([$role->id])->all());
            }
        }
    }
}
