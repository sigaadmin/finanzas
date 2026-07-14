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
        Schema::create('own_revenue_technical_sheet_needs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_revenue_budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('own_revenue_import_file_id')->constrained()->restrictOnDelete();
            $table->foreignId('own_revenue_activity_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('source_row_id')->constrained('own_revenue_import_rows')->restrictOnDelete();
            $table->string('specific_item_code');
            $table->string('sequence')->nullable();
            $table->string('quantity');
            $table->string('unit');
            $table->text('description');
            $table->string('region_code')->default('02-001');
            $table->string('region_name')->default('Felipe Carrillo Puerto');
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedTinyInteger('budget_month');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['own_revenue_budget_id', 'own_revenue_activity_id'], 'own_rev_tech_need_budget_activity_index');
            $table->unique(['own_revenue_import_file_id', 'source_row_id'], 'own_rev_tech_need_file_source_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('own_revenue_technical_sheet_needs');
    }
};
