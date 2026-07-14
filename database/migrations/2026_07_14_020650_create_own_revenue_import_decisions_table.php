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
        Schema::create('own_revenue_import_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_revenue_import_issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('own_revenue_import_row_id')->nullable()->constrained()->cascadeOnDelete();
            $table->json('current_value')->nullable();
            $table->json('proposed_value')->nullable();
            $table->json('resolved_value')->nullable();
            $table->string('resolution');
            $table->text('justification')->nullable();
            $table->foreignId('resolved_by')->constrained('users');
            $table->timestamp('resolved_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('own_revenue_import_decisions');
    }
};
