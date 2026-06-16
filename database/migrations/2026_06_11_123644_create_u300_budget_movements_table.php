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
        Schema::create('u300_budget_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('u300_budget_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('type', 24);
            $table->date('movement_date');
            $table->string('concept');
            $table->string('document_reference')->nullable();
            $table->unsignedBigInteger('amount_cents');
            $table->timestamps();

            $table->index(['u300_budget_line_id', 'movement_date']);
            $table->index(['type', 'movement_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('u300_budget_movements');
    }
};
