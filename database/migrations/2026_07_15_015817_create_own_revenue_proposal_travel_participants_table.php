<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('own_revenue_proposal_travel_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_revenue_proposal_travel_commission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('own_revenue_proposal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('own_revenue_budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('own_revenue_activity_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_travel_commission_id')->nullable()->constrained('own_revenue_travel_commissions')->restrictOnDelete();
            $table->foreignId('own_revenue_travel_rate_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('stable_key');
            $table->string('person_name');
            $table->string('position');
            $table->decimal('commission_days', 8, 4);
            $table->decimal('per_diem_uma', 12, 4);
            $table->decimal('lodging_uma', 12, 4);
            $table->unsignedBigInteger('per_diem_amount_cents');
            $table->unsignedBigInteger('lodging_amount_cents');
            $table->unsignedBigInteger('total_amount_cents');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['own_revenue_proposal_id', 'stable_key'],
                'own_rev_proposal_travel_participant_stable_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('own_revenue_proposal_travel_participants');
    }
};
