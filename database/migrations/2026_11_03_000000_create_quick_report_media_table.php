<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quick_report_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quick_report_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('path');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('original_name');
            $table->timestamps();

            $table->index(['quick_report_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_report_media');
    }
};
