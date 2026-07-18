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
        Schema::create('own_revenue_expense_dossier_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_revenue_expense_dossier_id')->constrained()->restrictOnDelete();
            $table->foreignId('own_revenue_expense_requirement_rule_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('evidence_document_id')->nullable()->constrained('own_revenue_expense_dossier_documents')->restrictOnDelete();
            $table->text('exception_reason')->nullable();
            $table->foreignId('exception_evidence_document_id')->nullable()->constrained('own_revenue_expense_dossier_documents')->restrictOnDelete();
            $table->foreignId('acted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->unique(['own_revenue_expense_dossier_id', 'own_revenue_expense_requirement_rule_id'], 'expense_dossier_requirement_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('own_revenue_expense_dossier_requirements');
    }
};
