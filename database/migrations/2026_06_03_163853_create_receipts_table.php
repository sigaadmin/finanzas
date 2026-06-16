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
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_procedure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_procedure_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('folio')->unique();
            $table->string('type');
            $table->string('status')->default('issued');
            $table->unsignedInteger('total_pesos');
            $table->string('amount_in_words');
            $table->string('validation_token')->unique();
            $table->timestamp('issued_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status', 'issued_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
