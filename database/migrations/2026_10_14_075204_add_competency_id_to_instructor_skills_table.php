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
        Schema::table('instructor_skills', function (Blueprint $table) {
            $table->foreignId('competency_id')
                ->nullable()
                ->after('instructor_id')
                ->constrained('competencies')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instructor_skills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('competency_id');
        });
    }
};
