<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('own_revenue_proposal_technical_needs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_revenue_proposal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('own_revenue_budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('own_revenue_activity_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_technical_sheet_need_id')->nullable()->constrained('own_revenue_technical_sheet_needs')->restrictOnDelete();
            $table->foreignId('expense_classification_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('stable_key');
            $table->string('specific_item_code');
            $table->string('specific_item_name')->nullable();
            $table->string('chapter_code')->nullable();
            $table->string('chapter_name')->nullable();
            $table->string('sequence')->nullable();
            $table->decimal('quantity', 16, 4);
            $table->string('unit');
            $table->text('description');
            $table->unsignedBigInteger('unit_price_cents')->nullable();
            $table->unsignedBigInteger('reference_amount_cents')->nullable();
            $table->unsignedBigInteger('budget_amount_cents');
            $table->unsignedTinyInteger('budget_month');
            $table->text('impact_on_goals')->nullable();
            $table->string('region_code')->default('02-001');
            $table->string('region_name')->default('Felipe Carrillo Puerto');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['own_revenue_proposal_id', 'stable_key'], 'own_rev_proposal_tech_stable_unique');
            $table->index(['own_revenue_budget_id', 'own_revenue_activity_id'], 'own_rev_proposal_tech_budget_activity_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('own_revenue_proposal_technical_needs');
    }
};
