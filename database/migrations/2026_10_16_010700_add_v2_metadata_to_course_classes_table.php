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
        Schema::table('course_classes', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->after('id')->constrained('programs')->nullOnDelete();
            $table->string('department', 32)->nullable()->after('subject')->index();
            $table->string('kind', 32)->nullable()->default('regular')->after('student_count')->index();
            $table->date('start_date')->nullable()->after('kind');
            $table->date('end_date')->nullable()->after('start_date');
            $table->integer('total_meetings')->nullable()->after('end_date');
            $table->integer('session_duration_minutes')->nullable()->default(120)->after('total_meetings');
            $table->string('status', 32)->nullable()->default('unassigned')->after('session_duration_minutes')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_classes', function (Blueprint $table) {
            $table->dropForeign(['program_id']);
            $table->dropIndex(['department']);
            $table->dropIndex(['kind']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'program_id',
                'department',
                'kind',
                'start_date',
                'end_date',
                'total_meetings',
                'session_duration_minutes',
                'status',
            ]);
        });
    }
};
