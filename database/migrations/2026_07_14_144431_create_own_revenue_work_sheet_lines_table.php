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
        Schema::create('own_revenue_work_sheet_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_revenue_budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('own_revenue_import_file_id')->constrained()->restrictOnDelete();
            $table->foreignId('own_revenue_activity_id')->constrained()->restrictOnDelete();
            $table->foreignId('expense_classification_id')->constrained()->restrictOnDelete();
            $table->string('activity_code');
            $table->string('activity_name');
            $table->string('item_name');
            $table->string('specific_item_code');
            $table->string('region_code')->default('02-001');
            $table->string('region_name')->default('Felipe Carrillo Puerto');
            $table->bigInteger('annual_amount_cents');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['own_revenue_budget_id', 'specific_item_code']);
            $table->unique(
                [
                    'own_revenue_import_file_id',
                    'own_revenue_activity_id',
                    'expense_classification_id',
                    'region_code',
                ],
                'own_rev_work_sheet_lines_file_key_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('own_revenue_work_sheet_lines');
    }
};
