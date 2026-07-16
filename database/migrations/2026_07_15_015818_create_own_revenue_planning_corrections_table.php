<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('own_revenue_planning_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_revenue_proposal_id')->constrained()->cascadeOnDelete();
            $table->morphs('correctable');
            $table->string('field');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('justification');
            $table->foreignId('corrected_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('corrected_at');
            $table->timestamps();

            $table->index(['own_revenue_proposal_id', 'corrected_at'], 'own_rev_correction_proposal_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('own_revenue_planning_corrections');
    }
};
