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
        Schema::create('own_revenue_modified_budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_revenue_budget_id')->constrained()->restrictOnDelete();
            $table->foreignId('own_revenue_initial_budget_id')->constrained()->restrictOnDelete();
            $table->foreignId('expense_classification_id')->constrained()->restrictOnDelete();
            $table->string('chapter_code', 10);
            $table->string('chapter_name');
            $table->string('specific_item_code', 10);
            $table->string('specific_item_name');
            $table->unsignedTinyInteger('month');
            $table->unsignedBigInteger('initial_amount_cents')->default(0);
            $table->timestamps();

            $table->unique(
                ['own_revenue_budget_id', 'specific_item_code', 'month'],
                'own_revenue_modified_line_item_month_unique',
            );
            $table->index(
                ['own_revenue_budget_id', 'chapter_code', 'month'],
                'own_revenue_modified_line_chapter_month_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('own_revenue_modified_budget_lines');
    }
};
