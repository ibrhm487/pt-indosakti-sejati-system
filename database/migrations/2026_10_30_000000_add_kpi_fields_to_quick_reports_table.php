<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quick_reports', function (Blueprint $table) {
            $table->unsignedInteger('sampling_target')->nullable()->after('photo_path');
            $table->unsignedInteger('sampling_actual')->nullable()->after('sampling_target');
            $table->decimal('selling_target', 15, 2)->nullable()->after('sampling_actual');
            $table->decimal('selling_actual', 15, 2)->nullable()->after('selling_target');
            $table->unsignedInteger('booth_package_1')->nullable()->after('selling_actual');
            $table->unsignedInteger('booth_package_2')->nullable()->after('booth_package_1');
            $table->unsignedInteger('booth_package_3')->nullable()->after('booth_package_2');
            $table->unsignedInteger('store_package_1')->nullable()->after('booth_package_3');
            $table->unsignedInteger('store_package_2')->nullable()->after('store_package_1');
            $table->unsignedInteger('store_package_3')->nullable()->after('store_package_2');
            $table->text('learning')->nullable()->after('store_package_3');
        });
    }

    public function down(): void
    {
        Schema::table('quick_reports', function (Blueprint $table) {
            $table->dropColumn([
                'sampling_target', 'sampling_actual', 'selling_target', 'selling_actual',
                'booth_package_1', 'booth_package_2', 'booth_package_3',
                'store_package_1', 'store_package_2', 'store_package_3', 'learning',
            ]);
        });
    }
};
