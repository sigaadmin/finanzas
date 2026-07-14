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
        Schema::create('own_revenue_fuel_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_revenue_budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('own_revenue_import_file_id')->constrained()->restrictOnDelete();
            $table->foreignId('own_revenue_activity_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('source_row_id')->constrained('own_revenue_import_rows')->restrictOnDelete();
            $table->string('commission_date_label')->nullable();
            $table->unsignedTinyInteger('month');
            $table->text('reason');
            $table->string('vehicle_model');
            $table->string('kilometers_per_liter')->nullable();
            $table->string('outbound_origin');
            $table->string('outbound_destination');
            $table->string('outbound_kilometers');
            $table->string('return_origin')->nullable();
            $table->string('return_destination')->nullable();
            $table->string('return_kilometers')->nullable();
            $table->string('liters');
            $table->string('fuel_price');
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['own_revenue_budget_id', 'own_revenue_activity_id'], 'own_rev_fuel_plan_budget_activity_index');
            $table->unique(['own_revenue_import_file_id', 'source_row_id'], 'own_rev_fuel_plan_file_source_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('own_revenue_fuel_plans');
    }
};
