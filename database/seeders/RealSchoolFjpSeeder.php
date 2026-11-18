<?php

namespace Database\Seeders;

use App\Enums\PermitStatus;
use App\Enums\VenueStatus;
use App\Models\Brand;
use App\Models\EventType;
use App\Models\Fjp;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RealSchoolFjpSeeder extends Seeder
{
    public function run(): void
    {
        $permitter = User::where('email', 'permitter@indosakti.com')->first();
        $brands = Brand::whereIn('slug', ['milo', 'dancow'])->get()->keyBy('slug');
        $types = EventType::whereIn('slug', ['milo-school-sampling', 'dancow-school-sampling'])
            ->get()
            ->keyBy('slug');

        if (! $brands->has('milo') || ! $brands->has('dancow')) {
            $this->command?->warn('Brand MILO/DANCOW belum ada. Jalankan DemoDataSeeder terlebih dahulu.');

            return;
        }

        $start = now()->startOfDay();

        foreach ($this->schools() as $index => $school) {
            $brandName = $index % 2 === 0 ? 'MILO' : 'DANCOW';
            $brand = $brands->get($brandName === 'MILO' ? 'milo' : 'dancow');
            $type = $types->get(Str::slug($brandName.' school sampling'));

            if (! $type) {
                continue;
            }

            $city = str_replace('Kota ', '', $school['kota']);
            $province = str_replace(['Prov. ', 'D.K.I. '], ['', 'DKI '], $school['provinsi']);
            $mapsUrl = "https://www.google.com/maps?q={$school['lat']},{$school['lng']}";

            $venue = Venue::updateOrCreate(
                ['npsn' => $school['npsn']],
                [
                    'name' => $school['nama'],
                    'is_school' => true,
                    'address' => $school['alamat'].', '.$school['kecamatan'],
                    'city' => $city,
                    'province' => $province,
                    'latitude' => $school['lat'],
                    'longitude' => $school['lng'],
                    'status' => VenueStatus::APPROVED->value,
                ],
            );

            $fjp = Fjp::firstOrNew(['npsn' => $school['npsn']]);

            if (! $fjp->exists) {
                $fjp->fjp_number = Fjp::generateNumber();
            }

            $fjp->fill([
                'event_name' => $brand->name.' School Sampling - '.$school['nama'],
                'brand_id' => $brand->id,
                'event_type_id' => $type->id,
                'venue_id' => $venue->id,
                'school_name' => $school['nama'],
                'address' => $venue->address,
                'city' => $city,
                'province' => $province,
                'maps_url' => $mapsUrl,
                'event_date' => $start->copy()->addWeekdays(3 + $index)->toDateString(),
                'start_time' => '08:00',
                'end_time' => '12:00',
                'sampling_target' => 300,
                'notes' => 'Data referensi sekolah asli. Jumlah siswa belum diisi karena layanan Dapodik sedang gangguan.',
                'jug_required' => $brandName === 'MILO',
                'permit_status' => PermitStatus::APPROVED,
                'status' => Fjp::STATUS_CONFIRM,
                'created_by' => $permitter?->id,
                'dapodik_source' => null,
                'dapodik_synced_at' => null,
            ])->save();
        }
    }

    /**
     * @return array<int, array{npsn: string, nama: string, alamat: string, kecamatan: string, kota: string, provinsi: string, lat: string, lng: string}>
     */
    private function schools(): array
    {
        return [
            ['npsn' => '20104661', 'nama' => 'SDN Petojo Selatan 05 Pg.', 'alamat' => 'Jl. Petojo Encek XIV / 35', 'kecamatan' => 'Kec. Gambir', 'kota' => 'Kota Jakarta Pusat', 'provinsi' => 'Prov. D.K.I. Jakarta', 'lat' => '-6.1729000', 'lng' => '106.8166000'],
            ['npsn' => '20104585', 'nama' => 'SD NEGERI KARTINI 02 PT', 'alamat' => 'Jl. Gotong Royong Gg.E', 'kecamatan' => 'Kec. Sawah Besar', 'kota' => 'Kota Jakarta Pusat', 'provinsi' => 'Prov. D.K.I. Jakarta', 'lat' => '-6.1506000', 'lng' => '106.8315000'],
            ['npsn' => '20104525', 'nama' => 'SDN GALUR 01', 'alamat' => 'Jl. Kampung Rawa Tengah Gg.Kwista RT.014/004', 'kecamatan' => 'Kec. Johar Baru', 'kota' => 'Kota Jakarta Pusat', 'provinsi' => 'Prov. D.K.I. Jakarta', 'lat' => '-6.1773000', 'lng' => '106.8558000'],
            ['npsn' => '20100477', 'nama' => 'SDN Paseban 09 Pg.', 'alamat' => 'Jl. Salemba Tengah I/21', 'kecamatan' => 'Kec. Senen', 'kota' => 'Kota Jakarta Pusat', 'provinsi' => 'Prov. D.K.I. Jakarta', 'lat' => '-6.1932000', 'lng' => '106.8552000'],
            ['npsn' => '20101013', 'nama' => 'SDN CILINCING 06', 'alamat' => 'Jl. Sungai Landak No. 37', 'kecamatan' => 'Kec. Cilincing', 'kota' => 'Kota Jakarta Utara', 'provinsi' => 'Prov. D.K.I. Jakarta', 'lat' => '-6.1025000', 'lng' => '106.9382000'],
            ['npsn' => '20104937', 'nama' => 'SDN Sunter Jaya 01 Pg.', 'alamat' => 'Jl. Sunter Jaya IV A', 'kecamatan' => 'Kec. Tanjung Priok', 'kota' => 'Kota Jakarta Utara', 'provinsi' => 'Prov. D.K.I. Jakarta', 'lat' => '-6.1563000', 'lng' => '106.8670000'],
            ['npsn' => '20100641', 'nama' => 'SDN PENJARINGAN 12 PETANG', 'alamat' => 'Jl. Raya Pluit Selatan No.105', 'kecamatan' => 'Kec. Penjaringan', 'kota' => 'Kota Jakarta Utara', 'provinsi' => 'Prov. D.K.I. Jakarta', 'lat' => '-6.1334000', 'lng' => '106.7810000'],
            ['npsn' => '20100703', 'nama' => 'SDN Rawa Badak Utara 12 Pt.', 'alamat' => 'Jl. Rawa Badak Barat No. 37', 'kecamatan' => 'Kec. Koja', 'kota' => 'Kota Jakarta Utara', 'provinsi' => 'Prov. D.K.I. Jakarta', 'lat' => '-6.1194000', 'lng' => '106.9008000'],
            ['npsn' => '20105476', 'nama' => 'SD NEGERI RAWA BUAYA 05 PT', 'alamat' => 'Jl. Al-Barkah RT.001/03', 'kecamatan' => 'Kec. Cengkareng', 'kota' => 'Kota Jakarta Barat', 'provinsi' => 'Prov. D.K.I. Jakarta', 'lat' => '-6.1651000', 'lng' => '106.7282000'],
            ['npsn' => '20105494', 'nama' => 'SD NEGERI SEMANAN 14 PT', 'alamat' => 'Jl. Semanan Raya No. 55 RW.005 RT.03', 'kecamatan' => 'Kec. Kali Deres', 'kota' => 'Kota Jakarta Barat', 'provinsi' => 'Prov. D.K.I. Jakarta', 'lat' => '-6.1675000', 'lng' => '106.7041000'],
            ['npsn' => '20105307', 'nama' => 'SD NEGERI KEAGUNGAN 06 PT', 'alamat' => 'Jl. Kerajinan No. 44', 'kecamatan' => 'Kec. Taman Sari', 'kota' => 'Kota Jakarta Barat', 'provinsi' => 'Prov. D.K.I. Jakarta', 'lat' => '-6.1541000', 'lng' => '106.8140000'],
            ['npsn' => '20105417', 'nama' => 'SD NEGERI MERUYA UTARA 08 PG', 'alamat' => 'Jl. Meruya Selatan RT.04/04', 'kecamatan' => 'Kec. Kembangan', 'kota' => 'Kota Jakarta Barat', 'provinsi' => 'Prov. D.K.I. Jakarta', 'lat' => '-6.2007000', 'lng' => '106.7302000'],
            ['npsn' => '20103144', 'nama' => 'SD N BANGKA 07 PG', 'alamat' => 'Jl. Kemang Timur XI No. 1B', 'kecamatan' => 'Kec. Mampang Prapatan', 'kota' => 'Kota Jakarta Selatan', 'provinsi' => 'Prov. D.K.I. Jakarta', 'lat' => '-6.2664000', 'lng' => '106.8276000'],
            ['npsn' => '20102400', 'nama' => 'SD NEGERI MENTENG DALAM 08 PETANG', 'alamat' => 'Jl. Flamboyan I RT.0010 RW.09', 'kecamatan' => 'Kec. Tebet', 'kota' => 'Kota Jakarta Selatan', 'provinsi' => 'Prov. D.K.I. Jakarta', 'lat' => '-6.2305000', 'lng' => '106.8409000'],
            ['npsn' => '20106198', 'nama' => 'SD NEGERI SRENGSENG SAWAH 14 PG', 'alamat' => 'Jl. Raya Depok Gg. H. Sibi RT.02/02', 'kecamatan' => 'Kec. Jagakarsa', 'kota' => 'Kota Jakarta Selatan', 'provinsi' => 'Prov. D.K.I. Jakarta', 'lat' => '-6.3500000', 'lng' => '106.8341000'],
            ['npsn' => '20105934', 'nama' => 'SDN Jagakarsa 06 Pg.', 'alamat' => 'Jln. Kedondong RT.002/05 No.16', 'kecamatan' => 'Kec. Jagakarsa', 'kota' => 'Kota Jakarta Selatan', 'provinsi' => 'Prov. D.K.I. Jakarta', 'lat' => '-6.3237000', 'lng' => '106.8180000'],
            ['npsn' => '20108716', 'nama' => 'SDN Kebon Pala 01 Pg.', 'alamat' => 'Jl. Cakrawala No. 01', 'kecamatan' => 'Kec. Makasar', 'kota' => 'Kota Jakarta Timur', 'provinsi' => 'Prov. D.K.I. Jakarta', 'lat' => '-6.2644000', 'lng' => '106.8841000'],
            ['npsn' => '20103346', 'nama' => 'SD NEGERI PULOGEBANG 24 PAGI', 'alamat' => 'Jl. Raya Rawa Kuning', 'kecamatan' => 'Kec. Cakung', 'kota' => 'Kota Jakarta Timur', 'provinsi' => 'Prov. D.K.I. Jakarta', 'lat' => '-6.1949000', 'lng' => '106.9651000'],
            ['npsn' => '20103461', 'nama' => 'SDN Susukan 02 Pg.', 'alamat' => 'Jl. Makmur IV RT.009/02', 'kecamatan' => 'Kec. Ciracas', 'kota' => 'Kota Jakarta Timur', 'provinsi' => 'Prov. D.K.I. Jakarta', 'lat' => '-6.3108000', 'lng' => '106.8657000'],
            ['npsn' => '20108382', 'nama' => 'SDN Utan Kayu Selatan 05', 'alamat' => 'Jl. Kramat Asem Raya No.64', 'kecamatan' => 'Kec. Matraman', 'kota' => 'Kota Jakarta Timur', 'provinsi' => 'Prov. D.K.I. Jakarta', 'lat' => '-6.2032000', 'lng' => '106.8695000'],
        ];
    }
}
