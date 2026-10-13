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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 32)->index(); // leave, engine, ai, approval, schedule
            $table->string('state', 32)->index(); // success, fallback, not_applicable, pending, rejected, info
            $table->string('actor'); // e.g. Admin Name, 'Sistem', 'Gemini AI'
            $table->string('title');
            $table->text('description');
            $table->string('subject'); // e.g. 'Pengajuan Izin #1', 'Microsoft Excel - Lab 2'
            $table->foreignId('instructor_leave_id')->nullable()->constrained('instructor_leaves')->nullOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained('schedules')->nullOnDelete();
            $table->string('fingerprint', 64)->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'type']);
            $table->index(['instructor_leave_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
