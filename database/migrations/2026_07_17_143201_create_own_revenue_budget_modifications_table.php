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
        Schema::create('own_revenue_budget_modifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_revenue_budget_id')->constrained()->restrictOnDelete();
            $table->string('type', 32);
            $table->foreignId('source_line_id')->constrained('own_revenue_modified_budget_lines')->restrictOnDelete();
            $table->foreignId('destination_line_id')->constrained('own_revenue_modified_budget_lines')->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->text('reason');
            $table->unsignedBigInteger('source_balance_before_cents');
            $table->unsignedBigInteger('source_balance_after_cents');
            $table->unsignedBigInteger('destination_balance_before_cents');
            $table->unsignedBigInteger('destination_balance_after_cents');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['own_revenue_budget_id', 'recorded_at']);
            $table->index(['source_line_id', 'recorded_at']);
            $table->index(['destination_line_id', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('own_revenue_budget_modifications');
    }
};
