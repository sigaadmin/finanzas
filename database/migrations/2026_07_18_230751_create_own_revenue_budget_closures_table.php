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
        Schema::create('own_revenue_budget_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_revenue_budget_id')
                ->unique()
                ->constrained('own_revenue_budgets')
                ->cascadeOnDelete();
            $table->text('note');
            $table->json('snapshot');
            $table->char('fingerprint', 64);
            $table->foreignId('closed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('own_revenue_budget_closures');
    }
};
