<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('own_revenue_proposal_fuel_needs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_revenue_proposal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('own_revenue_budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('own_revenue_activity_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_fuel_plan_id')->nullable()->constrained('own_revenue_fuel_plans')->restrictOnDelete();
            $table->foreignId('own_revenue_route_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('stable_key');
            $table->string('commission_date_label')->nullable();
            $table->unsignedTinyInteger('operational_month');
            $table->unsignedTinyInteger('budget_month');
            $table->text('reason');
            $table->string('vehicle_model');
            $table->decimal('kilometers_per_liter', 12, 4);
            $table->string('outbound_origin');
            $table->string('outbound_destination');
            $table->decimal('outbound_kilometers', 12, 4);
            $table->string('return_origin')->nullable();
            $table->string('return_destination')->nullable();
            $table->decimal('return_kilometers', 12, 4)->default(0);
            $table->decimal('additional_kilometers', 12, 4)->default(0);
            $table->decimal('total_kilometers', 12, 4);
            $table->decimal('liters', 12, 4);
            $table->decimal('fuel_price', 12, 4);
            $table->unsignedBigInteger('mathematical_amount_cents');
            $table->unsignedBigInteger('rounded_amount_cents');
            $table->unsignedBigInteger('budget_amount_cents');
            $table->unsignedBigInteger('rounding_difference_cents');
            $table->text('override_justification')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['own_revenue_proposal_id', 'stable_key'], 'own_rev_proposal_fuel_stable_unique');
            $table->index(['own_revenue_budget_id', 'own_revenue_activity_id'], 'own_rev_proposal_fuel_budget_activity_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('own_revenue_proposal_fuel_needs');
    }
};
