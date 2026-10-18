<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_requests', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('notes');
            $table->string('recipient')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('stock_requests', function (Blueprint $table) {
            $table->dropColumn(['photo_path', 'recipient']);
        });
    }
};
