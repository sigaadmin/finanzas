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
        Schema::create('own_revenue_workbook_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_revenue_initial_budget_id')->constrained()->restrictOnDelete();
            $table->string('format', 32);
            $table->string('storage_disk', 32)->default('local');
            $table->string('storage_path');
            $table->string('file_name');
            $table->string('sha256', 64);
            $table->unsignedBigInteger('total_amount_cents');
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('generated_at');
            $table->timestamps();
            $table->index(['own_revenue_initial_budget_id', 'format']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('own_revenue_workbook_exports');
    }
};
