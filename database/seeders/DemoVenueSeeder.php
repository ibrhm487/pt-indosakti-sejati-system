<?php

namespace Database\Seeders;

use App\Enums\VenueStatus;
use App\Models\Venue;
use Illuminate\Database\Seeder;

class DemoVenueSeeder extends Seeder
{
    /** @var array<int, array{city: string, province: string}> */
    private const AREAS = [
        ['city' => 'Jakarta Pusat', 'province' => 'DKI Jakarta'],
        ['city' => 'Jakarta Selatan', 'province' => 'DKI Jakarta'],
        ['city' => 'Jakarta Timur', 'province' => 'DKI Jakarta'],
        ['city' => 'Jakarta Barat', 'province' => 'DKI Jakarta'],
        ['city' => 'Jakarta Utara', 'province' => 'DKI Jakarta'],
        ['city' => 'Bekasi', 'province' => 'Jawa Barat'],
        ['city' => 'Bandung', 'province' => 'Jawa Barat'],
        ['city' => 'Tangerang', 'province' => 'Banten'],
        ['city' => 'Surabaya', 'province' => 'Jawa Timur'],
        ['city' => 'Yogyakarta', 'province' => 'DI Yogyakarta'],
    ];

    public function run(): void
    {
        $approvedAt = now();
        for ($number = 1; $number <= 20; $number++) {
            $sequence = str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            $area = self::AREAS[($number - 1) % count(self::AREAS)];
            $name = "Venue Contoh {$sequence} - {$area['city']}";
            $address = "Jl. Contoh Venue No. {$sequence}, {$area['city']}";

            Venue::query()
                ->where('name', "[SIMULASI] Venue Non-Sekolah {$sequence} - {$area['city']}")
                ->update(['name' => $name, 'address' => $address]);

            Venue::updateOrCreate(
                ['name' => $name],
                [
                    'npsn' => null,
                    'is_school' => false,
                    'address' => $address,
                    'city' => $area['city'],
                    'province' => $area['province'],
                    'status' => VenueStatus::APPROVED,
                    'approved_at' => $approvedAt,
                ],
            );
        }
    }
}
