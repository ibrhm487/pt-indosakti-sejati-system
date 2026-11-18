<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fjp', function (Blueprint $table) {
            $table->string('city', 100)->nullable()->after('address');
            $table->string('province', 100)->nullable()->after('city');
        });

        DB::table('fjp')
            ->whereNotNull('venue_id')
            ->select(['id', 'venue_id'])
            ->orderBy('id')
            ->chunk(100, function ($fjps) {
                foreach ($fjps as $fjp) {
                    $venue = DB::table('venues')
                        ->where('id', $fjp->venue_id)
                        ->first(['city', 'province']);

                    if (! $venue) {
                        continue;
                    }

                    $updates = array_filter([
                        'city' => $venue->city,
                        'province' => $venue->province,
                    ], fn ($value) => $value !== null && $value !== '');

                    if ($updates !== []) {
                        DB::table('fjp')->where('id', $fjp->id)->update($updates);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('fjp', function (Blueprint $table) {
            $table->dropColumn(['city', 'province']);
        });
    }
};
