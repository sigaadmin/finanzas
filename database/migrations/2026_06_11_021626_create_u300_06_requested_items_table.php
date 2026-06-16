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
        Schema::create('u300_requested_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('u300_action_id')->constrained()->cascadeOnDelete();
            $table->foreignId('u300_budget_version_id')->constrained()->cascadeOnDelete();
            $table->string('expense_concept');
            $table->string('expense_item');
            $table->unsignedTinyInteger('period');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_cents');
            $table->unsignedBigInteger('total_cents');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['u300_action_id', 'period']);
            $table->index(['u300_budget_version_id', 'expense_concept']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('u300_requested_items');
    }
};
