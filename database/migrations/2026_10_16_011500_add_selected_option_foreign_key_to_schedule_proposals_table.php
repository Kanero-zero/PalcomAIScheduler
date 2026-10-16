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
        Schema::table('schedule_proposals', function (Blueprint $table) {
            $table->foreign('selected_option_id')
                ->references('id')
                ->on('schedule_proposal_options')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedule_proposals', function (Blueprint $table) {
            $table->dropForeign(['selected_option_id']);
        });
    }
};
