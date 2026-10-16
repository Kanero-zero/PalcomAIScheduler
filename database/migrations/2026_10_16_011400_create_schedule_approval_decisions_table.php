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
        Schema::create('schedule_approval_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('schedule_proposals')->cascadeOnDelete();
            $table->unsignedInteger('round')->default(1)->index();
            $table->foreignId('decision_by')->constrained('users')->restrictOnDelete();
            $table->string('decision', 32)->index();
            $table->text('reason')->nullable();
            $table->string('idempotency_token', 64)->unique();
            $table->timestamp('decided_at')->useCurrent()->index();
            $table->timestamps();

            // Riwayat Keputusan Berkelanjutan: Setiap putaran review unik per proposal
            $table->unique(['proposal_id', 'round']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_approval_decisions');
    }
};
