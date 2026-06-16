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
        Schema::create('seq_deposits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('receipt_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('registered_by')->constrained('users')->restrictOnDelete();
            $table->date('deposit_date');
            $table->string('bank_transaction_folio');
            $table->string('deposit_type');
            $table->string('deposit_concept');
            $table->unsignedInteger('amount_pesos');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seq_deposits');
    }
};
