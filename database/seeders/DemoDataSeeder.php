<?php

namespace Database\Seeders;

use App\Enums\EventStatus;
use App\Enums\KitchenStatus;
use App\Enums\MrvStatus;
use App\Enums\PermitStatus;
use App\Enums\ReportStatus;
use App\Models\Attendance;
use App\Models\Brand;
use App\Models\Event;
use App\Models\EventReport;
use App\Models\EventTeamMember;
use App\Models\EventType;
use App\Models\Fjp;
use App\Models\Inventory;
use App\Models\KitchenRequest;
use App\Models\PermitRequest;
use App\Models\Personnel;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ShiftRate;
use App\Models\StockRequest;
use App\Models\StockRequestItem;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $brands = collect(['MILO', 'KITKAT', 'DANCOW', 'BEAR BRAND'])->mapWithKeys(
            fn ($name) => [$name => Brand::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true, 'quarterly_target_cups' => 10000],
            )]
        );

        $schoolType = EventType::updateOrCreate(
            ['slug' => 'school-sampling'],
            ['name' => 'School Sampling', 'requires_school' => true, 'kitchen_default' => true],
        );
        $mallType = EventType::updateOrCreate(
            ['slug' => 'mall-activation'],
            ['name' => 'Mall Activation', 'requires_school' => false, 'kitchen_default' => false],
        );

        $venues = collect([
            ['name' => 'SDN 01 Menteng', 'npsn' => '20101001', 'is_school' => true, 'city' => 'Jakarta Pusat'],
            ['name' => 'SDN 05 Kebayoran', 'npsn' => '20101002', 'is_school' => true, 'city' => 'Jakarta Selatan'],
            ['name' => 'Grand Indonesia Mall', 'npsn' => null, 'is_school' => false, 'city' => 'Jakarta Pusat'],
        ])->mapWithKeys(function ($data) {
            return [$data['name'] => Venue::updateOrCreate(['name' => $data['name']], $data)];
        });

        for ($i = 1; $i <= 4; $i++) {
            Personnel::updateOrCreate(
                ['name' => "Team Leader $i"],
                ['role' => 'Team Leader', 'is_active' => $i <= 2],
            );
            Personnel::updateOrCreate(
                ['name' => "SPG $i"],
                ['role' => 'SPG', 'is_active' => $i <= 2],
            );
        }

        $categories = collect(['Minuman', 'Alat Promosi'])->mapWithKeys(
            fn ($name) => [$name => ProductCategory::updateOrCreate(['slug' => Str::slug($name)], ['name' => $name])]
        );

        $products = collect([
            ['name' => 'MILO Sachet', 'sku' => 'MILO-SACHET', 'brand' => 'MILO', 'category' => 'Minuman', 'stock' => 1000],
            ['name' => 'KITKAT Mini', 'sku' => 'KITKAT-MINI', 'brand' => 'KITKAT', 'category' => 'Minuman', 'stock' => 600],
            ['name' => 'DANCOW Sachet', 'sku' => 'DANCOW-SACHET', 'brand' => 'DANCOW', 'category' => 'Minuman', 'stock' => 500],
            ['name' => 'Booth Portable', 'sku' => 'BOOTH-PORT', 'brand' => null, 'category' => 'Alat Promosi', 'stock' => 20],
        ])->mapWithKeys(function ($data) use ($brands, $categories) {
            $product = Product::updateOrCreate(
                ['sku' => $data['sku']],
                [
                    'name' => $data['name'],
                    'brand_id' => $data['brand'] ? $brands[$data['brand']]->id : null,
                    'product_category_id' => $categories[$data['category']]->id,
                    'unit' => 'pcs',
                    'minimum_stock' => 50,
                ],
            );

            Inventory::updateOrCreate(
                ['product_id' => $product->id],
                ['qty_on_hand' => $data['stock'], 'qty_reserved' => 0],
            );

            return [$data['name'] => $product];
        });

        ShiftRate::updateOrCreate(['label' => 'Default'], ['rate' => 125000, 'is_active' => true]);

        $pic = User::where('email', 'pic@indosakti.com')->first();
        $permitter = User::where('email', 'permitter@indosakti.com')->first();
        $team = User::where('email', 'team@indosakti.com')->first();
        $adminEvent = User::where('email', 'admin.event@indosakti.com')->first();
        $warehouse = User::where('email', 'warehouse@indosakti.com')->first();

        if ($pic && $team) {
            $this->seedSamplingEvent($brands['MILO'], $schoolType, $venues['SDN 01 Menteng'], $pic, $permitter, $team, $adminEvent, $warehouse, $products, true);
            $this->seedSamplingEvent($brands['KITKAT'], $schoolType, $venues['SDN 05 Kebayoran'], $pic, $permitter, $team, $adminEvent, $warehouse, $products, false);
        }
    }

    private function seedSamplingEvent(
        Brand $brand,
        EventType $type,
        Venue $venue,
        User $pic,
        ?User $permitter,
        User $team,
        ?User $adminEvent,
        ?User $warehouse,
        $products,
        bool $jugRequired,
    ): void {
        $sekolah = 300 + random_int(0, 120);
        $dapodik = $sekolah - random_int(-20, 40);

        $fjp = Fjp::updateOrCreate(
            ['event_name' => $brand->name.' School Sampling Drive'],
            [
                'fjp_number' => Fjp::generateNumber(),
                'brand_id' => $brand->id,
                'event_type_id' => $type->id,
                'venue_id' => $venue->id,
                'school_name' => $venue->name,
                'npsn' => $venue->npsn,
                'jumlah_siswa_sekolah' => $sekolah,
                'jumlah_siswa_dapodik' => $dapodik,
                'dapodik_source' => 'SIMULASI (bukan data resmi Dapodik)',
                'dapodik_synced_at' => now(),
                'event_date' => now()->addDays(7)->toDateString(),
                'start_time' => '08:00',
                'end_time' => '12:00',
                'sampling_target' => 500,
                'jug_required' => $jugRequired,
                'permit_status' => PermitStatus::APPROVED,
                'status' => 'approved',
                'created_by' => $permitter?->id,
            ],
        );

        $event = Event::updateOrCreate(
            ['event_name' => $fjp->event_name],
            [
                'event_code' => Event::generateCode(),
                'fjp_id' => $fjp->id,
                'brand_id' => $brand->id,
                'event_type_id' => $type->id,
                'venue_id' => $venue->id,
                'event_date' => $fjp->event_date,
                'start_time' => '08:00',
                'end_time' => '12:00',
                'sampling_target' => 500,
                'jug_required' => $jugRequired,
                'status' => EventStatus::ONGOING,
                'pic_id' => $pic->id,
                'created_by' => $permitter?->id,
            ],
        );

        PermitRequest::updateOrCreate(
            ['event_id' => $event->id],
            [
                'permit_number' => PermitRequest::generateNumber(),
                'fjp_id' => $fjp->id,
                'venue_id' => $venue->id,
                'deadline' => now()->addDays(3)->toDateString(),
                'assigned_to' => $permitter?->id,
                'status' => PermitStatus::APPROVED,
                'created_by' => $permitter?->id,
                'decided_by' => $pic->id,
                'decided_at' => now(),
            ],
        );

        EventTeamMember::updateOrCreate(
            ['event_id' => $event->id, 'user_id' => $team->id],
            ['position' => 'SPG', 'status' => 'assigned', 'assigned_by' => $pic->id],
        );

        $stockRequest = StockRequest::updateOrCreate(
            ['event_id' => $event->id],
            [
                'mrv_number' => StockRequest::generateNumber(),
                'type' => 'issue',
                'status' => MrvStatus::APPROVED,
                'requested_by' => $pic->id,
                'approved_by' => $warehouse?->id,
                'approved_at' => now(),
            ],
        );

        StockRequestItem::updateOrCreate(
            ['stock_request_id' => $stockRequest->id, 'product_id' => $products['MILO Sachet']->id],
            ['quantity_requested' => 200, 'quantity_issued' => 0],
        );

        if ($jugRequired) {
            KitchenRequest::updateOrCreate(
                ['event_id' => $event->id],
                [
                    'jug_quantity' => 5,
                    'preparation_date' => now()->addDays(6)->toDateString(),
                    'preparation_time' => '14:00',
                    'status' => KitchenStatus::PREPARING,
                ],
            );
        }

        $report = EventReport::updateOrCreate(
            ['event_id' => $event->id],
            [
                'sampling_target' => 500,
                'actual_sampling' => 450,
                'issues' => null,
                'notes' => 'Cuaca mendukung.',
                'status' => ReportStatus::SUBMITTED,
                'submitted_by' => $team->id,
                'submitted_at' => now(),
            ],
        );
        $report->achievement_percentage = $report->calculateAchievement();
        $report->save();

        Attendance::updateOrCreate(
            ['event_id' => $event->id, 'user_id' => $team->id],
            [
                'planned_start' => now()->setTime(8, 0),
                'planned_end' => now()->setTime(12, 0),
                'check_in_at' => now()->setTime(7, 55),
                'check_out_at' => now()->setTime(12, 10),
                'status' => 'complete',
            ],
        );
    }
}
