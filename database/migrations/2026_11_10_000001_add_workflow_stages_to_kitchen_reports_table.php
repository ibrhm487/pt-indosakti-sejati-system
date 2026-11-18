<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kitchen_reports', function (Blueprint $table) {
            $table->timestamp('washing_at')->nullable()->after('washing_photo_path');
            $table->timestamp('cooking_at')->nullable()->after('washing_at');
            $table->string('ready_photo_path')->nullable()->after('cooking_at');
            $table->timestamp('ready_at')->nullable()->after('ready_photo_path');
        });

        Schema::create('kitchen_report_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitchen_report_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('path');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('original_name');
            $table->timestamps();

            $table->index(['kitchen_report_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_report_media');

        Schema::table('kitchen_reports', function (Blueprint $table) {
            $table->dropColumn(['washing_at', 'cooking_at', 'ready_photo_path', 'ready_at']);
        });
    }
};
