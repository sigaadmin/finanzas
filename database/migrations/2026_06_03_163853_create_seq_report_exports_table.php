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
        Schema::create('seq_report_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->string('period_month');
            $table->json('filters');
            $table->unsignedInteger('total_pesos');
            $table->unsignedInteger('receipt_count')->default(0);
            $table->timestamp('exported_at')->nullable();
            $table->timestamps();

            $table->index(['period_month', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seq_report_exports');
    }
};
