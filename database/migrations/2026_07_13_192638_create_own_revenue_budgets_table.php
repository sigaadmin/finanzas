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
        Schema::create('own_revenue_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users');
            $table->unsignedSmallInteger('fiscal_year')->unique();
            $table->string('status')->default('draft');
            $table->string('institution_name');
            $table->string('responsible_unit_code');
            $table->string('responsible_unit_name');
            $table->string('budget_program_code');
            $table->string('budget_program_name');
            $table->string('component_code');
            $table->string('component_name');
            $table->string('official_activity_code');
            $table->string('official_activity_name');
            $table->string('region_code')->default('02-001');
            $table->string('region_name')->default('Felipe Carrillo Puerto');
            $table->unsignedBigInteger('estimated_income_cents')->nullable();
            $table->decimal('cut_percentage', 5, 2)->nullable();
            $table->decimal('uma_value', 12, 4)->nullable();
            $table->string('uma_status')->default('pending_review');
            $table->decimal('fuel_price_per_liter', 12, 4)->nullable();
            $table->string('fuel_price_status')->default('pending_review');
            $table->unsignedTinyInteger('fuel_budget_month')->default(4);
            $table->unsignedSmallInteger('cog_source_year')->nullable();
            $table->string('cog_status')->default('pending_confirmation');
            $table->foreignId('cog_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cog_confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'fiscal_year']);
            $table->index(['cog_status', 'cog_source_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('own_revenue_budgets');
    }
};
