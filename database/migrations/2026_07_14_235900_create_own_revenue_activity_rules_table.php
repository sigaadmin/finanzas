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
        Schema::create('own_revenue_activity_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_revenue_budget_id')->constrained()->cascadeOnDelete();
            $table->string('format');
            $table->text('group_key');
            $table->char('group_hash', 64);
            $table->json('group_payload');
            $table->foreignId('own_revenue_activity_id')->constrained()->restrictOnDelete();
            $table->string('activity_code');
            $table->string('activity_name');
            $table->string('justification');
            $table->text('justification_note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('deactivated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deactivated_at')->nullable();
            $table->foreignId('replaces_rule_id')->nullable()->constrained('own_revenue_activity_rules')->nullOnDelete();
            $table->timestamps();

            $table->index(
                ['own_revenue_budget_id', 'format', 'group_hash', 'is_active'],
                'own_rev_activity_rules_lookup_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('own_revenue_activity_rules');
    }
};
