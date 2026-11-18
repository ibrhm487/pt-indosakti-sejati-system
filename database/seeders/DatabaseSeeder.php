<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            DemoDataSeeder::class,
            WarehouseRealDataSeeder::class,
            RealSchoolFjpSeeder::class,
            TeamAccountSeeder::class,
            TeamSimulationSeeder::class,
            PayrollSimulationSeeder::class,
            DailyOutputSimulationSeeder::class,
        ]);
    }
}
