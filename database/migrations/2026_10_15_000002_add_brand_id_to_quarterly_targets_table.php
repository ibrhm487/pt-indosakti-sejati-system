<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('quarterly_targets', function (Blueprint $table) {
            $table->dropUnique('quarterly_targets_year_quarter_unique');
            $table->foreignId('brand_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->unique(['brand_id', 'year', 'quarter']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quarterly_targets', function (Blueprint $table) {
            $table->dropUnique('quarterly_targets_brand_id_year_quarter_unique');
            $table->dropConstrainedForeignId('brand_id');
            $table->unique(['year', 'quarter']);
        });
    }
};
