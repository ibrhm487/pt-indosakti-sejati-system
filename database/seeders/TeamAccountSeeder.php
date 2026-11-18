<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TeamAccountSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::where('slug', 'team')->first();

        if (! $role) {
            $this->command?->warn('Role team belum ada. Jalankan RolePermissionSeeder terlebih dahulu.');

            return;
        }

        $password = Hash::make(env('SEED_PASSWORD', 'password'));

        foreach ($this->accounts() as [$name, $position, $email]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name.' ('.$position.')',
                    'password' => $password,
                    'is_active' => true,
                    'active_role_id' => $role->id,
                ],
            );

            $user->roles()->syncWithoutDetaching([$role->id]);
        }
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    private function accounts(): array
    {
        return [
            ['Budi Santoso', 'TL', 'budi.santoso@indosakti.com'],
            ['Rizky Pratama', 'TL', 'rizky.pratama@indosakti.com'],
            ['Andi Saputra', 'TL', 'andi.saputra@indosakti.com'],
            ['Fajar Nugroho', 'TL', 'fajar.nugroho@indosakti.com'],
            ['Dimas Ramadhan', 'TL', 'dimas.ramadhan@indosakti.com'],
            ['Yoga Pangestu', 'TL', 'yoga.pangestu@indosakti.com'],
            ['Hendra Wijaya', 'TL', 'hendra.wijaya@indosakti.com'],
            ['Agus Setiawan', 'TL', 'agus.setiawan@indosakti.com'],
            ['Bayu Laksono', 'TL', 'bayu.laksono@indosakti.com'],
            ['Reza Maulana', 'TL', 'reza.maulana@indosakti.com'],

            ['Siti Aminah', 'SPG', 'siti.aminah@indosakti.com'],
            ['Dewi Lestari', 'SPG', 'dewi.lestari@indosakti.com'],
            ['Rina Wulandari', 'SPG', 'rina.wulandari@indosakti.com'],
            ['Putri Handayani', 'SPG', 'putri.handayani@indosakti.com'],
            ['Ayu Pertiwi', 'SPG', 'ayu.pertiwi@indosakti.com'],
            ['Fitriani', 'SPG', 'fitriani@indosakti.com'],
            ['Melati Kusuma', 'SPG', 'melati.kusuma@indosakti.com'],
            ['Nur Aini', 'SPG', 'nur.aini@indosakti.com'],
            ['Intan Permata', 'SPG', 'intan.permata@indosakti.com'],
            ['Rani Oktaviani', 'SPG', 'rani.oktaviani@indosakti.com'],
        ];
    }
}
