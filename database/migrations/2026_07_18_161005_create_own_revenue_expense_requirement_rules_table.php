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
        Schema::create('own_revenue_expense_requirement_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_revenue_budget_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('target_status', 40);
            $table->string('purchase_responsibility', 20)->nullable();
            $table->string('chapter_code', 10)->nullable();
            $table->string('specific_item_code', 20)->nullable();
            $table->unsignedBigInteger('minimum_amount_cents')->nullable();
            $table->boolean('requires_evidence')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['own_revenue_budget_id', 'target_status', 'is_active'], 'expense_requirement_rule_lookup_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('own_revenue_expense_requirement_rules');
    }
};
