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
        Schema::create('own_revenue_work_sheet_months', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_revenue_work_sheet_line_id')->constrained()->cascadeOnDelete();
            $table->enum('month', array_map(strval(...), range(1, 12)));
            $table->unsignedBigInteger('amount_cents');
            $table->timestamps();
            $table->unique(['own_revenue_work_sheet_line_id', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('own_revenue_work_sheet_months');
    }
};
