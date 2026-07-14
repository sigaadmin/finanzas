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
        Schema::create('own_revenue_abpre_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_revenue_budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('own_revenue_import_file_id')->constrained()->restrictOnDelete();
            $table->foreignId('expense_classification_id')->constrained()->restrictOnDelete();
            $table->string('responsible_unit_code');
            $table->string('responsible_unit_name');
            $table->string('budget_program_code');
            $table->string('budget_program_name');
            $table->string('component_code');
            $table->string('component_name');
            $table->string('official_activity_code');
            $table->string('official_activity_name');
            $table->string('region_code')->default('02-001');
            $table->string('region_name')->default('Felipe Carrillo Puerto');
            $table->string('specific_expense_concept_code')->nullable();
            $table->string('specific_item_code');
            $table->unsignedBigInteger('annual_amount_cents');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['own_revenue_budget_id', 'specific_item_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('own_revenue_abpre_lines');
    }
};
