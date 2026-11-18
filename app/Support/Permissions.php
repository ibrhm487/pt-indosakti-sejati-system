<?php

namespace App\Support;

/**
 * Canonical permission catalogue. Every sensitive action must be authorized
 * server-side using one of these slugs.
 */
class Permissions
{
    /** @return array<string, array{name: string, module: string}> */
    public static function all(): array
    {
        $map = [];

        $groups = [
            'dashboard' => ['view' => 'Lihat dashboard'],
            'events' => [
                'view' => 'Lihat event',
                'create' => 'Buat event',
                'update' => 'Ubah event',
                'delete' => 'Hapus event',
                'assign_team' => 'Tugaskan tim',
                'close' => 'Tutup event',
            ],
            'fjp' => [
                'view' => 'Lihat FJP',
                'create' => 'Buat FJP',
                'update' => 'Ubah FJP',
                'status.update' => 'Ubah status FJP',
                'assign_team' => 'Tentukan tim FJP',
                'assign_team_members' => 'Membagikan anggota tim FJP',
                'delete' => 'Hapus FJP',
            ],
            'venues' => [
                'view' => 'Lihat lokasi',
                'approve' => 'Setujui lokasi',
            ],
            'permits' => [
                'view' => 'Lihat perizinan',
                'create' => 'Buat perizinan',
                'update' => 'Ubah perizinan',
                'approve' => 'Setujui perizinan',
                'reject' => 'Tolak perizinan',
            ],
            'warehouse' => [
                'view' => 'Lihat gudang',
                'manage' => 'Kelola produk & stok',
                'request' => 'Buat permintaan stok',
                'approve' => 'Setujui permintaan stok',
                'issue' => 'Keluarkan stok',
                'return' => 'Catat pengembalian',
            ],
            'kitchen' => [
                'view' => 'Lihat kitchen',
                'manage' => 'Kelola tugas kitchen',
            ],
            'reports' => [
                'view' => 'Lihat laporan',
                'create' => 'Buat laporan',
                'verify' => 'Verifikasi laporan',
                'revision' => 'Minta revisi laporan',
            ],
            'quick_reports' => [
                'view' => 'Lihat laporan cepat',
                'create' => 'Buat laporan cepat',
            ],
            'targets' => [
                'manage' => 'Kelola target kuartal',
            ],
            'attendance' => [
                'view' => 'Lihat absensi',
                'create' => 'Isi absensi',
                'manage' => 'Kelola absensi',
                'payroll' => 'Lihat penggajian',
            ],
            'admin' => [
                'users.manage' => 'Kelola pengguna',
                'roles.manage' => 'Kelola role',
                'personnel.manage' => 'Kelola data personel',
                'settings.manage' => 'Kelola pengaturan',
                'audit.view' => 'Lihat audit log',
                'system.view' => 'Lihat kesehatan sistem',
                'logs.view' => 'Lihat log aplikasi',
            ],
        ];

        foreach ($groups as $module => $items) {
            foreach ($items as $action => $name) {
                $slug = $module.'.'.$action;
                $map[$slug] = [
                    'name' => $name,
                    'module' => $module,
                ];
            }
        }

        return $map;
    }

    /** @return array<int, string> */
    public static function slugs(): array
    {
        return array_keys(self::all());
    }

    /**
     * @return array<string, array<int, array{slug: string, name: string}>>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::all() as $slug => $meta) {
            $groups[$meta['module']][] = [
                'slug' => $slug,
                'name' => $meta['name'],
            ];
        }

        return $groups;
    }
}
