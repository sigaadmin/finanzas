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
        Schema::create('own_revenue_expense_dossiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_revenue_budget_id')->constrained()->restrictOnDelete();
            $table->foreignId('own_revenue_modified_budget_line_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sequence_number');
            $table->string('folio', 32);
            $table->string('status', 40);
            $table->text('concept');
            $table->unsignedBigInteger('amount_cents');
            $table->string('purchase_responsibility', 20);
            $table->string('external_reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('sufficiency_requested_at')->nullable();
            $table->foreignId('sufficiency_confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('sufficiency_confirmed_at')->nullable();
            $table->timestamps();

            $table->unique(['own_revenue_budget_id', 'sequence_number']);
            $table->unique(['own_revenue_budget_id', 'folio']);
            $table->index(['own_revenue_budget_id', 'status']);
            $table->index(['own_revenue_modified_budget_line_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('own_revenue_expense_dossiers');
    }
};
