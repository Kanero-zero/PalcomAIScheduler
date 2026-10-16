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
        Schema::create('class_curriculum_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_class_id')->unique()->constrained('course_classes')->cascadeOnDelete();
            $table->foreignId('source_curriculum_version_id')->nullable()->constrained('curriculum_versions')->nullOnDelete();
            $table->json('snapshot_payload');
            $table->timestamp('captured_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_curriculum_snapshots');
    }
};
