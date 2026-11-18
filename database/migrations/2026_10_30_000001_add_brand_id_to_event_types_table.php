<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_types', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        $brands = DB::table('brands')->whereIn('slug', ['milo', 'dancow'])->pluck('id', 'slug');

        $types = [
            'milo-school-sampling' => ['brand' => 'milo', 'name' => 'MILO School Sampling', 'requires_school' => true, 'kitchen_default' => true],
            'milo-instore' => ['brand' => 'milo', 'name' => 'MILO InStore', 'requires_school' => false, 'kitchen_default' => false],
            'dancow-school-sampling' => ['brand' => 'dancow', 'name' => 'DANCOW School Sampling', 'requires_school' => true, 'kitchen_default' => true],
            'dancow-instore' => ['brand' => 'dancow', 'name' => 'DANCOW InStore', 'requires_school' => false, 'kitchen_default' => false],
        ];

        foreach ($types as $slug => $type) {
            $brandId = $brands[$type['brand']] ?? null;

            if (! $brandId) {
                continue;
            }

            DB::table('event_types')->updateOrInsert(
                ['slug' => $slug],
                [
                    'brand_id' => $brandId,
                    'name' => $type['name'],
                    'requires_school' => $type['requires_school'],
                    'kitchen_default' => $type['kitchen_default'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        foreach (['milo', 'dancow'] as $brandSlug) {
            $brandId = $brands[$brandSlug] ?? null;

            if (! $brandId) {
                continue;
            }

            $schoolTypeId = DB::table('event_types')->where('slug', $brandSlug.'-school-sampling')->value('id');
            $inStoreTypeId = DB::table('event_types')->where('slug', $brandSlug.'-instore')->value('id');

            $events = DB::table('events')
                ->where('brand_id', $brandId)
                ->get(['id', 'fjp_id', 'event_type_id']);

            foreach ($events as $event) {
                $requiresSchool = DB::table('event_types')
                    ->where('id', $event->event_type_id)
                    ->value('requires_school');

                $targetTypeId = (int) $requiresSchool === 0 ? $inStoreTypeId : $schoolTypeId;

                if (! $targetTypeId || $event->event_type_id === $targetTypeId) {
                    continue;
                }

                DB::table('events')->where('id', $event->id)->update([
                    'event_type_id' => $targetTypeId,
                    'updated_at' => now(),
                ]);

                if ($event->fjp_id) {
                    DB::table('fjp')
                        ->where('id', $event->fjp_id)
                        ->where('brand_id', $brandId)
                        ->update(['event_type_id' => $targetTypeId, 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('event_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
        });
    }
};
