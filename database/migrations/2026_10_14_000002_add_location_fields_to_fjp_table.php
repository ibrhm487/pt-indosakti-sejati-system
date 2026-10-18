<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fjp', function (Blueprint $table) {
            $table->text('address')->nullable()->after('npsn');
            $table->string('maps_url', 2048)->nullable()->after('address');
        });

        Schema::table('fjp', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::table('fjp', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('npsn');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });

        Schema::table('fjp', function (Blueprint $table) {
            $table->dropColumn(['address', 'maps_url']);
        });
    }
};
