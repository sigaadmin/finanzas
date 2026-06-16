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
        Schema::create('u300_technical_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('u300_budget_line_id')->constrained()->cascadeOnDelete();
            $table->text('objective')->nullable();
            $table->text('work_description')->nullable();
            $table->text('technical_specs')->nullable();
            $table->string('beneficiaries')->nullable();
            $table->string('scheduled_date')->nullable();
            $table->text('deliverables')->nullable();
            $table->text('delivery_location')->nullable();
            $table->text('supervisor')->nullable();
            $table->text('payment_terms')->nullable();
            $table->timestamps();

            $table->unique('u300_budget_line_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('u300_technical_sheets');
    }
};
