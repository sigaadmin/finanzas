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
        Schema::create('own_revenue_abpre_justifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_revenue_budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('own_revenue_import_file_id')->constrained()->restrictOnDelete();
            $table->string('chapter_code');
            $table->string('chapter_name');
            $table->string('specific_item_code');
            $table->string('specific_item_name');
            $table->string('budget_program_code');
            $table->string('budget_program_name');
            $table->string('component_code');
            $table->string('component_name');
            $table->text('goals_impact')->nullable();
            $table->text('justification');
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
        Schema::dropIfExists('own_revenue_abpre_justifications');
    }
};
