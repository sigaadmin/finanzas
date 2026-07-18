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
        Schema::create('own_revenue_fuel_funds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_revenue_budget_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('source_expense_dossier_id')->unique()->constrained('own_revenue_expense_dossiers')->restrictOnDelete();
            $table->unsignedBigInteger('acquired_amount_cents');
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('opened_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('own_revenue_fuel_funds');
    }
};
