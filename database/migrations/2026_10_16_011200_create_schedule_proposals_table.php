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
        Schema::create('schedule_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_class_id')->nullable()->constrained('course_classes')->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('title', 150);
            $table->string('input_mode', 32)->default('ai_assistant')->index();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('curriculum_version_id')->nullable()->constrained('curriculum_versions')->nullOnDelete();
            $table->string('department', 32)->index();
            $table->string('class_kind', 32)->default('regular')->index();
            $table->integer('student_count')->default(1);
            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->integer('total_meetings');
            $table->integer('session_duration_minutes')->default(120);
            $table->integer('frequency_per_week')->default(2);
            $table->json('preferred_weekdays')->nullable();
            $table->json('preferred_time_window')->nullable();
            $table->foreignId('preferred_instructor_id')->nullable()->constrained('instructors')->nullOnDelete();
            $table->foreignId('preferred_room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->json('required_competency_ids')->nullable();
            $table->json('required_facility_codes')->nullable();
            $table->text('ai_prompt')->nullable();
            // Catatan Urutan Dependensi: selected_option_id disiapkan sebagai kolom nullable.
            // Foreign key ke schedule_proposal_options ditambahkan setelah tabel tersebut dibuat.
            $table->unsignedBigInteger('selected_option_id')->nullable()->index();
            $table->string('status', 32)->default('draft')->index();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_proposals');
    }
};
