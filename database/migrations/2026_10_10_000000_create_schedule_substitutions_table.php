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
        Schema::create('schedule_substitutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_leave_id')->constrained('instructor_leaves')->cascadeOnDelete();
            $table->foreignId('schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->foreignId('original_instructor_id')->constrained('instructors')->restrictOnDelete();
            $table->foreignId('replacement_instructor_id')->nullable()->constrained('instructors')->restrictOnDelete();
            $table->string('status')->default('pending'); // 'pending', 'approved', 'rejected'
            $table->foreignId('decision_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decision_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Mencegah keputusan ganda pada jadwal yang sama untuk izin yang sama (kompatibel SQLite & MySQL)
            $table->unique(['instructor_leave_id', 'schedule_id'], 'uq_leave_schedule_sub');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_substitutions');
    }
};
