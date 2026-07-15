<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('own_revenue_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_revenue_budget_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status')->default('draft');
            $table->foreignId('based_on_proposal_id')->nullable()->constrained('own_revenue_proposals')->restrictOnDelete();
            $table->foreignId('source_abpre_file_id')->nullable()->constrained('own_revenue_import_files')->restrictOnDelete();
            $table->foreignId('source_work_sheet_file_id')->nullable()->constrained('own_revenue_import_files')->restrictOnDelete();
            $table->foreignId('source_technical_sheet_file_id')->nullable()->constrained('own_revenue_import_files')->restrictOnDelete();
            $table->foreignId('source_fuel_file_id')->nullable()->constrained('own_revenue_import_files')->restrictOnDelete();
            $table->foreignId('source_travel_expenses_file_id')->nullable()->constrained('own_revenue_import_files')->restrictOnDelete();
            $table->char('source_fingerprint', 64)->nullable();
            $table->unsignedBigInteger('total_amount_cents')->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('calculated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['own_revenue_budget_id', 'version_number'], 'own_rev_proposal_budget_version_unique');
            $table->index(['own_revenue_budget_id', 'status'], 'own_rev_proposal_budget_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('own_revenue_proposals');
    }
};
