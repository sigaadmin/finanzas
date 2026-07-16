<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('own_revenue_proposal_cuts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_revenue_proposal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('own_revenue_activity_id')->constrained()->restrictOnDelete();
            $table->string('target_type', 32);
            $table->unsignedBigInteger('target_id');
            $table->string('stable_key');
            $table->string('specific_item_code');
            $table->unsignedTinyInteger('budget_month');
            $table->unsignedBigInteger('available_amount_cents');
            $table->unsignedBigInteger('amount_cents');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(
                ['own_revenue_proposal_id', 'target_type', 'target_id'],
                'own_rev_proposal_cuts_target_unique',
            );
            $table->index(
                ['own_revenue_proposal_id', 'own_revenue_activity_id', 'specific_item_code', 'budget_month'],
                'own_rev_proposal_cuts_projection_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('own_revenue_proposal_cuts');
    }
};
