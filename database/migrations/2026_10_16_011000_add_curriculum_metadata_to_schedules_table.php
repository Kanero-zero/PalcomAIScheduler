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
        Schema::table('schedules', function (Blueprint $table) {
            $table->foreignId('curriculum_subject_id')->nullable()->after('course_class_id')->constrained('curriculum_subjects')->nullOnDelete();
            $table->integer('meeting_sequence')->nullable()->after('curriculum_subject_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['curriculum_subject_id']);
            $table->dropIndex(['meeting_sequence']);
            $table->dropColumn(['curriculum_subject_id', 'meeting_sequence']);
        });
    }
};
