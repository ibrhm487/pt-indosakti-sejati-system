<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kitchen_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('jug_quantity')->default(0);
            $table->date('preparation_date')->nullable();
            $table->time('preparation_time')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('pending')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('kitchen_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitchen_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('prepared_quantity')->default(0);
            $table->string('condition')->nullable();
            $table->text('notes')->nullable();
            $table->string('photo_path')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('prepared_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_reports');
        Schema::dropIfExists('kitchen_requests');
    }
};
