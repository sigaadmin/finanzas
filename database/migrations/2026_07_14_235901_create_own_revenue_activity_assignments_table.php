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
        Schema::create('own_revenue_activity_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_revenue_budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('own_revenue_import_file_id')->constrained()->restrictOnDelete();
            $table->foreignId('own_revenue_activity_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('assignable_type');
            $table->unsignedBigInteger('assignable_id');
            $table->foreignId('previous_activity_id')->nullable()->constrained('own_revenue_activities')->restrictOnDelete();
            $table->foreignId('own_revenue_activity_id')->constrained()->restrictOnDelete();
            $table->string('activity_code');
            $table->string('activity_name');
            $table->string('mode');
            $table->text('group_key');
            $table->char('group_hash', 64);
            $table->string('justification');
            $table->text('justification_note')->nullable();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamps();

            $table->index(
                ['assignable_type', 'assignable_id', 'assigned_at'],
                'own_rev_activity_assignable_history_index',
            );
            $table->index(
                ['own_revenue_budget_id', 'own_revenue_import_file_id'],
                'own_rev_activity_assignment_file_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('own_revenue_activity_assignments');
    }
};
