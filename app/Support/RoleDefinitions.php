<?php

namespace App\Support;

use App\Enums\RoleSlug;

class RoleDefinitions
{
    /**
     * Map each role to the permission slugs it grants.
     *
     * @return array<string, array{name: string, description: string, permissions: array<int, string>|string}>
     */
    public static function all(): array
    {
        return [
            RoleSlug::DEVELOPER->value => [
                'name' => RoleSlug::DEVELOPER->label(),
                'description' => 'Developer: akses teknis penuh untuk seluruh modul dan administrasi.',
                'permissions' => '*',
            ],
            RoleSlug::SYSTEM_ADMIN->value => [
                'name' => RoleSlug::SYSTEM_ADMIN->label(),
                'description' => 'Administrator sistem: pengguna, master data, audit.',
                'permissions' => '*',
            ],
            RoleSlug::PERMITTER->value => [
                'name' => RoleSlug::PERMITTER->label(),
                'description' => 'Mengelola FJP, menentukan tim, dan mengubah status FJP.',
                'permissions' => [
                    'dashboard.view', 'fjp.view', 'fjp.create', 'fjp.update', 'fjp.delete',
                    'fjp.status.update', 'fjp.assign_team',
                    'events.view', 'targets.manage', 'venues.view', 'warehouse.request',
                ],
            ],
            RoleSlug::PIC->value => [
                'name' => RoleSlug::PIC->label(),
                'description' => 'Koordinator FJP, penugasan tim, dan kesiapan operasional.',
                'permissions' => [
                    'dashboard.view', 'events.view', 'events.create', 'events.update',
                    'events.delete', 'events.assign_team', 'fjp.view', 'fjp.status.update',
                    'fjp.create', 'fjp.update', 'fjp.assign_team_members',
                    'venues.view', 'venues.approve',
                    'warehouse.view', 'warehouse.request', 'kitchen.view',
                    'reports.view', 'reports.create', 'quick_reports.view',
                    'attendance.view', 'attendance.create', 'attendance.manage', 'attendance.payroll',
                ],
            ],
            RoleSlug::TEAM->value => [
                'name' => RoleSlug::TEAM->label(),
                'description' => 'Eksekutor lapangan: absensi dan laporan.',
                'permissions' => [
                    'dashboard.view', 'events.view', 'reports.view', 'reports.create',
                    'quick_reports.view', 'quick_reports.create',
                    'attendance.view', 'attendance.create', 'kitchen.view',
                ],
            ],
            RoleSlug::WAREHOUSE->value => [
                'name' => RoleSlug::WAREHOUSE->label(),
                'description' => 'Gudang: stok, MRV, pengembalian.',
                'permissions' => [
                    'dashboard.view', 'events.view', 'warehouse.view', 'warehouse.manage',
                    'warehouse.request', 'warehouse.approve', 'warehouse.issue', 'warehouse.return',
                ],
            ],
            RoleSlug::KITCHEN->value => [
                'name' => RoleSlug::KITCHEN->label(),
                'description' => 'Kitchen: tugas JUG untuk event yang membutuhkan.',
                'permissions' => [
                    'dashboard.view', 'events.view', 'kitchen.view', 'kitchen.manage',
                ],
            ],
            RoleSlug::ADMIN_EVENT->value => [
                'name' => RoleSlug::ADMIN_EVENT->label(),
                'description' => 'Verifikasi laporan & penutupan event (bukan super admin).',
                'permissions' => [
                    'dashboard.view', 'events.view', 'events.close',
                    'fjp.view',
                    'reports.view', 'reports.verify', 'reports.revision',
                    'quick_reports.view', 'attendance.view', 'attendance.payroll',
                ],
            ],
        ];
    }
}
