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
        Schema::create('schedule_proposal_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('schedule_proposals')->cascadeOnDelete();
            $table->string('option_label', 8)->default('A');
            $table->json('proposed_schedules_payload');
            $table->integer('conflicts_count')->default(0);
            $table->integer('score')->default(100);
            $table->text('rationale')->nullable();
            $table->timestamps();

            // Integritas Opsi: Satu proposal tidak dapat memiliki opsi kembar dengan label sama
            $table->unique(['proposal_id', 'option_label']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_proposal_options');
    }
};
