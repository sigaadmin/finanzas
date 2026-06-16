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
        Schema::table('u300_budget_lines', function (Blueprint $table) {
            $table->foreignId('expense_classification_id')
                ->nullable()
                ->after('u300_action_id')
                ->constrained()
                ->nullOnDelete();
            $table->string('exercise_month', 3)->nullable()->after('amount_cents');
            $table->text('justification')->nullable()->after('description');

            $table->index(['expense_classification_id', 'exercise_month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('u300_budget_lines', function (Blueprint $table) {
            $table->dropForeign(['expense_classification_id']);
            $table->dropIndex(['expense_classification_id', 'exercise_month']);
            $table->dropColumn(['expense_classification_id', 'exercise_month', 'justification']);
        });
    }
};
