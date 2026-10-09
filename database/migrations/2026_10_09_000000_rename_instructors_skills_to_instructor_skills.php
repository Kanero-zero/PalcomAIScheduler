<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('instructors_skills') && ! Schema::hasTable('instructor_skills')) {
            Schema::rename('instructors_skills', 'instructor_skills');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('instructor_skills') && ! Schema::hasTable('instructors_skills')) {
            Schema::rename('instructor_skills', 'instructors_skills');
        }
    }
};
