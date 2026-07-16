<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('own_revenue_proposal_travel_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_revenue_proposal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('own_revenue_budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('own_revenue_activity_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_travel_commission_id')->nullable()->constrained('own_revenue_travel_commissions')->restrictOnDelete();
            $table->foreignId('own_revenue_travel_destination_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('stable_key');
            $table->string('commission_date_label')->nullable();
            $table->unsignedTinyInteger('operational_month');
            $table->unsignedTinyInteger('budget_month');
            $table->text('reason');
            $table->string('destination');
            $table->unsignedTinyInteger('food_zone');
            $table->unsignedTinyInteger('lodging_zone');
            $table->decimal('uma_value', 12, 4);
            $table->unsignedBigInteger('flight_amount_cents')->default(0);
            $table->unsignedBigInteger('participants_amount_cents')->default(0);
            $table->unsignedBigInteger('total_amount_cents')->default(0);
            $table->text('override_justification')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['own_revenue_proposal_id', 'stable_key'], 'own_rev_proposal_travel_stable_unique');
            $table->index(['own_revenue_budget_id', 'own_revenue_activity_id'], 'own_rev_proposal_travel_budget_activity_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('own_revenue_proposal_travel_commissions');
    }
};
