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
        Schema::create('payment_procedure_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_procedure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('charge_concept_id')->constrained()->restrictOnDelete();
            $table->string('concept_name');
            $table->string('concept_type');
            $table->unsignedInteger('unit_amount_pesos');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedInteger('subtotal_pesos');
            $table->timestamps();

            $table->index(['concept_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_procedure_items');
    }
};
