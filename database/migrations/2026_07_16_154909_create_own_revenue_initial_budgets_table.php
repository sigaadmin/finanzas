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
        Schema::create('own_revenue_initial_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_revenue_budget_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('own_revenue_proposal_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('total_amount_cents');
            $table->string('source_fingerprint', 64);
            $table->string('authorization_fingerprint', 64);
            $table->json('snapshot');
            $table->foreignId('authorized_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('authorized_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('own_revenue_initial_budgets');
    }
};
