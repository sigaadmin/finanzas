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
        Schema::create('own_revenue_travel_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_revenue_budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('own_revenue_import_file_id')->constrained()->restrictOnDelete();
            $table->foreignId('own_revenue_activity_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('source_row_id')->constrained('own_revenue_import_rows')->restrictOnDelete();
            $table->string('commission_date_label')->nullable();
            $table->unsignedTinyInteger('month');
            $table->text('reason');
            $table->string('person_name');
            $table->string('position');
            $table->string('commission_days');
            $table->string('destination');
            $table->string('per_diem_uma');
            $table->string('lodging_uma');
            $table->string('uma_value');
            $table->unsignedBigInteger('per_diem_amount_cents');
            $table->unsignedBigInteger('lodging_amount_cents');
            $table->unsignedBigInteger('total_amount_cents');
            $table->unsignedBigInteger('flight_amount_cents');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['own_revenue_budget_id', 'own_revenue_activity_id'], 'own_rev_travel_budget_activity_index');
            $table->unique(['own_revenue_import_file_id', 'source_row_id'], 'own_rev_travel_file_source_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('own_revenue_travel_commissions');
    }
};
