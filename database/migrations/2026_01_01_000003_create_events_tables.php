<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fjp', function (Blueprint $table) {
            $table->id();
            $table->string('fjp_number')->unique();
            $table->string('event_name');
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();

            $table->string('school_name')->nullable();
            $table->string('npsn', 20)->nullable();

            // Two independent student-count versions (AGENT.MD §8).
            $table->unsignedInteger('jumlah_siswa_sekolah')->nullable();
            $table->unsignedInteger('jumlah_siswa_dapodik')->nullable();
            $table->string('dapodik_source')->nullable();
            $table->timestamp('dapodik_synced_at')->nullable();

            $table->date('event_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedInteger('sampling_target')->nullable();
            $table->text('notes')->nullable();

            $table->boolean('jug_required')->default(false);
            $table->string('permit_status')->default('draft')->index();
            $table->text('rejection_reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->string('status')->default('draft')->index();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('permit_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fjp_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fjp_id')->constrained('fjp')->cascadeOnDelete();
            $table->string('category')->default('other');
            $table->string('original_name');
            $table->string('path');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('event_code')->unique();
            $table->string('event_name');
            $table->foreignId('fjp_id')->nullable()->constrained('fjp')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();
            $table->date('event_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedInteger('sampling_target')->default(0);
            $table->text('notes')->nullable();
            $table->boolean('jug_required')->default(false);
            $table->string('status')->default('draft')->index();
            $table->foreignId('pic_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('event_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('personnel_id')->nullable()->constrained('personnel')->nullOnDelete();
            $table->string('position')->default('SPG');
            $table->string('status')->default('assigned');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
            $table->unique(['event_id', 'personnel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_team_members');
        Schema::dropIfExists('events');
        Schema::dropIfExists('fjp_attachments');
        Schema::dropIfExists('fjp');
    }
};
